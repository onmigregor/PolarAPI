<?php

namespace Modules\Report\Actions;

use Modules\Analytics\Services\TenantConnectionService;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExportObsequiosCsvAction
{

    public array $errors = [];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(ExportSalesCsvFilterData $filters): array
    {
        $clients = $this->tenantService->resolveClients();
        $isRange = !empty($filters->start_date) && !empty($filters->end_date);
        
        $allRows = [];

        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($filters, $isRange) {
            $cep = $client->cep ?? '';
            $routeCode = $client->codigo ?? 'N/A';
            $tenantRows = [];

            // Obtener cep_ocasional desde la tabla configuraciones del tenant
            $cepOcasional = null;
            try {
                $configVal = DB::connection('tenant')
                    ->table('configuraciones')
                    ->where('clave_configuracion', 'cep_ocasional')
                    ->value('valor_configuracion');

                if (!empty($configVal)) {
                    $cepOcasional = $configVal;
                }
            } catch (\Exception $e) {
                // Silently fallback if table/key is not found
            }

            // Solo procesar si existe la tabla de seguimiento de cajas
            if (!Schema::connection('tenant')->hasTable('seguimiento_cajas_promocion')) {
                return [];
            }

            // PASO 1: Join Base (Gifts + Sales + Products)
            $queryBase = DB::connection('tenant')
                ->table('seguimiento_cajas_promocion as scp')
                ->join('ventaspxc as v', 'scp.id_venta', '=', 'v.IdVenta')
                ->join('productos as p', 'scp.codigoSKU', '=', 'p.codigoSKU')
                ->where('v.eliminado', 0)
                ->where('scp.status', 'entregado');
            
            $countBase = (clone $queryBase)->count();
            Log::error("      [DETECTIVE-OBS] Cliente $routeCode - Join Base: $countBase registros.");

            // PASO 3: Filtro de Fecha
            if ($isRange) {
                $queryBase->whereBetween('scp.fecha_entrega_cliente', [$filters->start_date . ' 00:00:00', $filters->end_date . ' 23:59:59']);
            } else {
                $queryBase->whereDate('scp.fecha_entrega_cliente', $filters->start_date);
            }

            $countFinal = (clone $queryBase)->count();
            Log::error("      [DETECTIVE-OBS] Cliente $routeCode - TRAS FILTRO FECHA: $countFinal registros.");

            $results = $queryBase->leftJoin('clientes as c', 'scp.id_cliente', '=', 'c.IdCliente')
            ->select(
                'v.IdVenta',
                'scp.fecha_entrega_cliente as rpt_fecha',
                'scp.cajas_entregadas as rpt_cantidad',
                'p.codigoSKU',
                'p.unidadesporcaja',
                'c.RIF',
                'c.cep as client_cep',
                'v.IdCliente'
            );

            $results = $results->get();

            foreach ($results as $row) {
                $um = ((int)$row->unidadesporcaja === 1) ? 'UND' : 'CJS';
                
                $clientCep = !empty($row->client_cep) ? $row->client_cep : $cepOcasional;
                if (!empty($clientCep)) {
                    $clientCep = str_pad((string)$clientCep, 10, '0', STR_PAD_LEFT);
                } else {
                    $clientCep = '';
                }

                $tenantRows[] = [
                    'fq_redi'       => $cep,
                    'cep'           => $clientCep,
                    'fecha'         => Carbon::parse($row->rpt_fecha)->format('d-m-Y'),
                    'deudor'        => $row->IdCliente,
                    'doc_fq_redi'   => $row->IdVenta,
                    'material'      => $row->codigoSKU,
                    'cantidad'      => $row->rpt_cantidad,
                    'um'            => $um,
                    'rif_ci_clte'   => $row->RIF ?? '',
                    'cl_doc'        => 'OBSQ',
                    'motivo'        => '',
                ];
            }

            return $tenantRows;
        });

        foreach ($tenantResults['results'] as $res) {
            if (!empty($res['data'])) {
                $allRows = array_merge($allRows, $res['data']);
            }
        }

        return $allRows;
    }
}
