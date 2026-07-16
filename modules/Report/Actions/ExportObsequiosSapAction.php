<?php

namespace Modules\Report\Actions;

use Modules\Analytics\Services\TenantConnectionService;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExportObsequiosSapAction
{

    // Valores FIJOS (columnas ROJAS) - No se modifican jamás
    private const CLASE_PEDIDO = 'YCPC';
    private const ORG_VENTA = 'C001';
    private const CANAL_DISTRIBUCION = 'C3';
    private const SECTOR = 'C1';
    private const MOTIVO = 'PB2';
    private const CONDICION_PAGO = 'ZB05';
    private const CENTRO = 'A012';
    private const INDICADOR_UTILIZACION = '01';
    private const NUMERO_MATERIAL_CLIENTE = 'MATERIAL-CLTE';
    private const ELEMENTO_PEP = 'PM-26-OC-CVE-P005-P01-01';

    public array $errors = [];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    /**
     * Genera las filas del CSV en formato SAP SmartFq (29 columnas).
     */
    public function execute(ExportSalesCsvFilterData $filters, string $table = 'company_routes'): array
    {
        $clients = $this->tenantService->resolveClients(null, $table);
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

            // Query base: Obsequios + Ventas + Productos
            $queryBase = DB::connection('tenant')
                ->table('seguimiento_cajas_promocion as scp')
                ->join('ventaspxc as v', 'scp.id_venta', '=', 'v.IdVenta')
                ->join('productos as p', 'scp.codigoSKU', '=', 'p.codigoSKU')
                ->leftJoin('clientes as c', 'scp.id_cliente', '=', 'c.IdCliente')
                ->where('v.eliminado', 0)
                ->where('scp.status', 'entregado');

            // Filtro de fecha
            if ($isRange) {
                $queryBase->whereBetween('scp.fecha_entrega_cliente', [$filters->start_date . ' 00:00:00', $filters->end_date . ' 23:59:59']);
            } else {
                $queryBase->whereDate('scp.fecha_entrega_cliente', $filters->start_date);
            }

            $results = $queryBase->select(
                'scp.fecha_entrega_cliente as rpt_fecha',
                'scp.cajas_entregadas as rpt_cantidad',
                'p.codigoSKU',
                'p.unidadesporcaja',
                'c.cep as client_cep',
                'v.IdCliente',
                'v.IdVenta'
            )->get();

            Log::info("ExportObsequiosSap: Tenant {$routeCode} - {$results->count()} obsequios encontrados.");

            foreach ($results as $row) {
                // Solicitante: usar cep del cliente, si no existe usar genérico, formateado a 10 dígitos (SAP)
                $solicitante = !empty($row->client_cep) ? $row->client_cep : $cepOcasional;
                if (!empty($solicitante)) {
                    $solicitante = str_pad((string)$solicitante, 10, '0', STR_PAD_LEFT);
                } else {
                    $solicitante = '';
                }

                // Fecha en formato SAP (dd.mm.yyyy)
                $fechaSap = Carbon::parse($row->rpt_fecha)->format('d.m.Y');

                // Material (SKU) - si no tiene SKU, saltar este registro
                $material = $row->codigoSKU;
                if (empty($material)) {
                    continue;
                }

                // Cantidad de obsequios
                $cantidad = $row->rpt_cantidad;

                // Construir la fila con las 29 columnas en el orden exacto del formato
                $tenantRows[] = [
                    self::CLASE_PEDIDO,           // 1.  Clase de pedido (ROJO)
                    self::ORG_VENTA,              // 2.  Organización de Venta (ROJO)
                    self::CANAL_DISTRIBUCION,      // 3.  Canal de Distribución (ROJO)
                    self::SECTOR,                  // 4.  Sector (ROJO)
                    $solicitante,                  // 5.  Solicitante (AMARILLO)
                    $fechaSap,                     // 6.  Fecha de Pedido (AMARILLO)
                    $fechaSap,                     // 7.  Fecha de Precio (AMARILLO)
                    $fechaSap,                     // 8.  Fe.prestac.servicios (AMARILLO)
                    self::MOTIVO,                  // 9.  Motivo (ROJO)
                    $row->IdVenta,                 // 10. Num Pedido del cliente (IdVenta)
                    '',                            // 11. Contabilidad-Referencia (VACÍO)
                    $material,                     // 12. Material (AMARILLO)
                    $cantidad,                     // 13. Cantidad (AMARILLO)
                    '',                            // 14. UNIDAD MEDIDA (VACÍO)
                    '',                            // 15. CONDICION Precio (VACÍO)
                    '',                            // 16. PRECIO (VACÍO)
                    $fechaSap,                     // 17. Fecha de Entrega (AMARILLO)
                    $fechaSap,                     // 18. Válido desde (AMARILLO)
                    $fechaSap,                     // 19. Fin Validez Oferta (AMARILLO)
                    self::CONDICION_PAGO,          // 20. Condición de Pago (ROJO)
                    self::CENTRO,                  // 21. Centro (ROJO)
                    '',                            // 22. Función Interlocutor (VACÍO)
                    '',                            // 23. Interlocutor (VACÍO)
                    '',                            // 24. Texto de Cabecera (VACÍO)
                    '',                            // 25. Nombre del Responsable (VACÍO)
                    self::INDICADOR_UTILIZACION,   // 26. Indicador de Utilización (ROJO)
                    self::NUMERO_MATERIAL_CLIENTE, // 27. Número de material del cliente (ROJO)
                    self::ELEMENTO_PEP,            // 28. Elemento PEP (ROJO)
                ];
            }

            return $tenantRows;
        });

        foreach ($tenantResults['results'] as $res) {
            if (!empty($res['data'])) {
                $allRows = array_merge($allRows, $res['data']);
            }
        }

        $this->errors = $tenantResults['errors'] ?? [];

        Log::info("ExportObsequiosSap: Total filas generadas: " . count($allRows));

        return $allRows;
    }

    /**
     * Genera el contenido CSV completo (cabecera + filas).
     */
    public function generateCsvContent(array $rows): string
    {
        // Cabecera exacta del formato SAP (29 columnas)
        $headers = [
            'Clase de pedido',
            'Organizacion de Venta',
            'Canal de Distribucion',
            'Sector',
            'Solicitante',
            'Fecha de Pedido',
            'Fecha de Precio',
            'Fe.prestac.servicios',
            'Motivo',
            'Num Pedido del cliente',
            'Contabilidad-Referencia',
            'Material',
            'Cantidad',
            'UNIDAD MEDIDA',
            'CONDICION Precio',
            'PRECIO',
            'Fecha de Entrega',
            'Valido desde',
            'Fin Validez Oferta',
            'Condicion de Pago',
            'Centro',
            'Funcion Interlocutor',
            'Interlocutor',
            'Texto de Cabecera',
            'Nombre del Responsable del Pedido',
            'Indicador de Utilizacion Cabecera Pedido',
            'Numero de material del cliente',
            'Elemento PEP',
        ];

        $csvContent = implode(',', $headers) . "\r\n";

        foreach ($rows as $row) {
            $csvContent .= implode(',', $row) . "\r\n";
        }

        return $csvContent;
    }
}
