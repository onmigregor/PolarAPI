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
    private const GENERIC_CEP_CODE = 'PV12345678';

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

            // Solo procesar si existe la tabla de plan táctico
            if (!Schema::connection('tenant')->hasTable('recepcion_plan_tactico')) {
                return [];
            }

            // PASO 1: Join Base (Gifts + Sales + Details + Products)
            $queryBase = DB::connection('tenant')
                ->table('recepcion_plan_tactico as rpt')
                ->join('ventaspxc as v', 'rpt.IdVenta', '=', 'v.IdVenta')
                ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
                ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                ->where('vd.eliminado', 0)
                ->where('v.eliminado', 0);
            
            $countBase = (clone $queryBase)->count();
            Log::error("      [DETECTIVE-OBS] Cliente $routeCode - Join Base: $countBase registros.");

            // PASO 2: Filtros de Obsequio (Texto)
            $queryBase->where(function($q) {
                    $q->where('p.codigoSKU', 'LIKE', '%OBS%')
                      ->orWhere(DB::raw('UPPER(vd.producto)'), 'LIKE', '%OBSEQUIO%');
                });
            
            $countObsq = (clone $queryBase)->count();
            Log::error("      [DETECTIVE-OBS] Cliente $routeCode - Tras Filtro Texto Obsequio: $countObsq registros.");

            // PASO 3: Filtro de Fecha
            if ($isRange) {
                $queryBase->whereBetween('rpt.fecha', [$filters->start_date, $filters->end_date]);
            } else {
                $queryBase->whereDate('rpt.fecha', $filters->start_date);
            }

            $countFinal = (clone $queryBase)->count();
            Log::error("      [DETECTIVE-OBS] Cliente $routeCode - TRAS FILTRO FECHA: $countFinal registros.");

            $results = $queryBase->leftJoin('clientes as c', 'v.IdCliente', '=', 'c.IdCliente')
            ->select(
                'v.IdVenta',
                'rpt.fecha as rpt_fecha',
                'rpt.obsequios as rpt_cantidad',
                'p.codigoSKU',
                'p.unidadesporcaja',
                'c.RIF',
                'c.cep as client_cep',
                'v.IdCliente'
            );

            $results = $results->get();

            foreach ($results as $row) {
                $um = ((int)$row->unidadesporcaja === 1) ? 'UND' : 'CJS';
                
                $clientCep = !empty($row->client_cep) ? $row->client_cep : $row->IdCliente;
                if (strlen((string)$clientCep) !== 10) {
                    $clientCep = self::GENERIC_CEP_CODE;
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
