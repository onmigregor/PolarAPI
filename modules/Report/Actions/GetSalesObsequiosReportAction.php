<?php

namespace Modules\Report\Actions;

use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use App\Helpers\EnsureSftpTrackingColumnsHelper;

class GetSalesObsequiosReportAction
{
    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    /**
     * Ejecuta la consulta multitenant para obtener ventas y obsequios con su estado de envío SFTP.
     *
     * @param array $filters Filtros recibidos del request
     * @return array ['summary' => [...], 'ventas' => [...], 'obsequios' => [...]]
     */
    public function execute(array $filters): array
    {
        $tenantId       = $filters['tenant_id'] ?? null;
        $startDate      = $filters['start_date'] ?? null;
        $endDate        = $filters['end_date'] ?? null;
        $sftpStartDate  = $filters['sftp_start_date'] ?? null;
        $sftpEndDate    = $filters['sftp_end_date'] ?? null;
        $onlyPending    = filter_var($filters['only_pending'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $page           = (int) ($filters['page'] ?? 1);
        $perPage        = min((int) ($filters['per_page'] ?? 50), 200);

        // Resolver clientes (tenants)
        $clientsQuery = CompanyRoute::where('is_active', true);
        if ($tenantId) {
            $clientsQuery->where('id', $tenantId);
        }
        $clients = $clientsQuery->get();

        $allVentas = [];
        $allObsequios = [];
        $summaryVentasEnviadas = 0;
        $summaryVentasPendientes = 0;
        $summaryObsequiosEnviados = 0;
        $summaryObsequiosPendientes = 0;

        $tenantResult = $this->tenantService->forEachTenant($clients, function ($client) use (
            $startDate, $endDate, $sftpStartDate, $sftpEndDate, $onlyPending,
            &$summaryVentasEnviadas, &$summaryVentasPendientes,
            &$summaryObsequiosEnviados, &$summaryObsequiosPendientes
        ) {
            EnsureSftpTrackingColumnsHelper::ensureColumnsForCurrentTenantConnection();

            $ventas = $this->queryVentas($client, $startDate, $endDate, $sftpStartDate, $sftpEndDate, $onlyPending);
            $obsequios = $this->queryObsequios($client, $startDate, $endDate, $sftpStartDate, $sftpEndDate, $onlyPending);

            // Acumular contadores de summary
            foreach ($ventas as $v) {
                if (!empty($v['fecha_envio_sftp'])) {
                    $summaryVentasEnviadas++;
                } else {
                    $summaryVentasPendientes++;
                }
            }
            foreach ($obsequios as $o) {
                if (!empty($o['fecha_envio_sftp'])) {
                    $summaryObsequiosEnviados++;
                } else {
                    $summaryObsequiosPendientes++;
                }
            }

            return [
                'ventas'    => $ventas,
                'obsequios' => $obsequios,
            ];
        });

        // Consolidar resultados de todos los tenants
        foreach ($tenantResult['results'] as $result) {
            if (!empty($result['data']['ventas'])) {
                $allVentas = array_merge($allVentas, $result['data']['ventas']);
            }
            if (!empty($result['data']['obsequios'])) {
                $allObsequios = array_merge($allObsequios, $result['data']['obsequios']);
            }
        }

        // Ordenar por fecha descendente
        usort($allVentas, fn($a, $b) => strcmp($b['fecha_venta'] ?? '', $a['fecha_venta'] ?? ''));
        usort($allObsequios, fn($a, $b) => strcmp($b['fecha_entrega'] ?? '', $a['fecha_entrega'] ?? ''));

        // Paginación manual sobre los resultados consolidados
        $totalVentas = count($allVentas);
        $totalObsequios = count($allObsequios);

        return [
            'summary' => [
                'ventas_enviadas'       => $summaryVentasEnviadas,
                'ventas_pendientes'     => $summaryVentasPendientes,
                'obsequios_enviados'    => $summaryObsequiosEnviados,
                'obsequios_pendientes'  => $summaryObsequiosPendientes,
            ],
            'ventas' => [
                'data'  => $allVentas,
                'total' => $totalVentas,
            ],
            'obsequios' => [
                'data'  => $allObsequios,
                'total' => $totalObsequios,
            ],
            'meta' => [
                'page'      => $page,
                'per_page'  => $perPage,
            ],
            'errors' => $tenantResult['errors'] ?? [],
        ];
    }

    /**
     * Consulta ventas de un tenant con filtros aplicados.
     */
    private function queryVentas(
        $client, ?string $startDate, ?string $endDate,
        ?string $sftpStartDate, ?string $sftpEndDate, bool $onlyPending
    ): array {
        if (!Schema::connection('tenant')->hasTable('ventaspxc')) {
            return [];
        }

        $hasSftpColumn = Schema::connection('tenant')->hasColumn('ventaspxc', 'fecha_envio_sftp');

        $query = DB::connection('tenant')
            ->table('ventaspxc as v')
            ->leftJoin('clientes as c', 'v.IdCliente', '=', 'c.IdCliente')
            ->where('v.eliminado', 0);

        // Filtro de fecha de operación
        if ($startDate && $endDate) {
            $query->whereBetween('v.Fecha', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } elseif ($startDate) {
            $query->whereDate('v.Fecha', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('v.Fecha', '<=', $endDate);
        }

        if ($hasSftpColumn) {
            // Filtro de fecha de envío SFTP
            if ($sftpStartDate && $sftpEndDate) {
                $query->whereBetween('v.fecha_envio_sftp', [$sftpStartDate . ' 00:00:00', $sftpEndDate . ' 23:59:59']);
            } elseif ($sftpStartDate) {
                $query->where('v.fecha_envio_sftp', '>=', $sftpStartDate . ' 00:00:00');
            } elseif ($sftpEndDate) {
                $query->where('v.fecha_envio_sftp', '<=', $sftpEndDate . ' 23:59:59');
            }

            // Solo pendientes
            if ($onlyPending) {
                $query->whereNull('v.fecha_envio_sftp');
            }
        } elseif ($onlyPending) {
            // Si no tiene la columna, todos son pendientes por definición
            // no filtramos nada extra
        }

        $selectColumns = [
            'v.IdVenta',
            'v.Fecha as fecha_venta',
            'v.IdCliente',
            'c.Cliente as nombre_cliente',
            'c.RIF',
            'v.MontoFactura',
        ];

        if ($hasSftpColumn) {
            $selectColumns[] = 'v.fecha_envio_sftp';
        } else {
            $selectColumns[] = DB::raw('NULL as fecha_envio_sftp');
        }

        $results = $query->select($selectColumns)->limit(5000)->get();

        $tenantName = $client->name ?? $client->code ?? 'N/A';
        $tenantId = $client->id;

        return $results->map(function ($row) use ($tenantName, $tenantId) {
            return [
                'tenant_id'         => $tenantId,
                'tenant_name'       => $tenantName,
                'id_venta'          => $row->IdVenta,
                'fecha_venta'       => $row->fecha_venta,
                'id_cliente'        => $row->IdCliente,
                'nombre_cliente'    => $row->nombre_cliente,
                'rif'               => $row->RIF,
                'total_venta'       => $row->MontoFactura,
                'fecha_envio_sftp'  => $row->fecha_envio_sftp,
            ];
        })->toArray();
    }

    /**
     * Consulta obsequios de un tenant con filtros aplicados.
     */
    private function queryObsequios(
        $client, ?string $startDate, ?string $endDate,
        ?string $sftpStartDate, ?string $sftpEndDate, bool $onlyPending
    ): array {
        if (!Schema::connection('tenant')->hasTable('seguimiento_cajas_promocion')) {
            return [];
        }

        $hasSftpColumn = Schema::connection('tenant')->hasColumn('seguimiento_cajas_promocion', 'fecha_envio_sftp');

        $query = DB::connection('tenant')
            ->table('seguimiento_cajas_promocion as scp')
            ->leftJoin('ventaspxc as v', 'scp.id_venta', '=', 'v.IdVenta')
            ->leftJoin('productos as p', 'scp.codigoSKU', '=', 'p.codigoSKU')
            ->leftJoin('clientes as c', 'scp.id_cliente', '=', 'c.IdCliente')
            ->where('scp.status', 'entregado');

        // Filtro de fecha de operación (fecha de entrega del obsequio)
        if ($startDate && $endDate) {
            $query->whereBetween('scp.fecha_entrega_cliente', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } elseif ($startDate) {
            $query->whereDate('scp.fecha_entrega_cliente', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('scp.fecha_entrega_cliente', '<=', $endDate);
        }

        if ($hasSftpColumn) {
            // Filtro de fecha de envío SFTP
            if ($sftpStartDate && $sftpEndDate) {
                $query->whereBetween('scp.fecha_envio_sftp', [$sftpStartDate . ' 00:00:00', $sftpEndDate . ' 23:59:59']);
            } elseif ($sftpStartDate) {
                $query->where('scp.fecha_envio_sftp', '>=', $sftpStartDate . ' 00:00:00');
            } elseif ($sftpEndDate) {
                $query->where('scp.fecha_envio_sftp', '<=', $sftpEndDate . ' 23:59:59');
            }

            // Solo pendientes
            if ($onlyPending) {
                $query->whereNull('scp.fecha_envio_sftp');
            }
        }

        $selectColumns = [
            'scp.id as obsq_id',
            'scp.id_venta as id_venta',
            'scp.fecha_entrega_cliente as fecha_entrega',
            'scp.cajas_entregadas as cantidad',
            'scp.codigoSKU',
            DB::raw('COALESCE(p.producto, scp.nombre_producto_obsequio) as nombre_producto'),
            'scp.id_cliente',
            'c.Cliente as nombre_cliente',
            'c.RIF',
        ];

        if ($hasSftpColumn) {
            $selectColumns[] = 'scp.fecha_envio_sftp';
        } else {
            $selectColumns[] = DB::raw('NULL as fecha_envio_sftp');
        }

        $results = $query->select($selectColumns)->limit(5000)->get();

        $tenantName = $client->name ?? $client->code ?? 'N/A';
        $tenantId = $client->id;

        return $results->map(function ($row) use ($tenantName, $tenantId) {
            return [
                'tenant_id'         => $tenantId,
                'tenant_name'       => $tenantName,
                'obsq_id'           => $row->obsq_id,
                'id_venta'          => $row->id_venta,
                'fecha_entrega'     => $row->fecha_entrega,
                'cantidad'          => $row->cantidad,
                'codigo_sku'        => $row->codigoSKU,
                'nombre_producto'   => $row->nombre_producto,
                'id_cliente'        => $row->id_cliente,
                'nombre_cliente'    => $row->nombre_cliente,
                'rif'               => $row->RIF,
                'fecha_envio_sftp'  => $row->fecha_envio_sftp,
            ];
        })->toArray();
    }
}
