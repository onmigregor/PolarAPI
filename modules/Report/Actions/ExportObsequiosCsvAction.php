<?php

namespace Modules\Report\Actions;

use Modules\Analytics\Services\TenantConnectionService;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ExportObsequiosCsvAction
{
    private const GENERIC_CEP_CODE = 'PV12345678';

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
            $tenantRows = [];

            // Solo procesar si existe la tabla de plan táctico
            if (!Schema::connection('tenant')->hasTable('recepcion_plan_tactico')) {
                return [];
            }

            $query = DB::connection('tenant')
                ->table('recepcion_plan_tactico as rpt')
                ->join('ventaspxc as v', 'rpt.IdVenta', '=', 'v.IdVenta')
                ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
                ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                ->leftJoin('clientes as c', 'v.IdCliente', '=', 'c.IdCliente')
                ->where('vd.eliminado', 0)
                ->where('v.eliminado', 0)
                ->where(function($q) {
                    $q->where('p.codigoSKU', 'LIKE', '%OBS%')
                      ->orWhere(DB::raw('UPPER(vd.producto)'), 'LIKE', '%OBSEQUIO%');
                });

            if ($isRange) {
                $query->whereBetween('rpt.fecha', [$filters->start_date . ' 00:00:00', $filters->end_date . ' 23:59:59']);
            } else {
                $query->whereDate('rpt.fecha', $filters->start_date);
            }

            $results = $query->select(
                'v.IdVenta',
                'rpt.fecha as rpt_fecha',
                'rpt.obsequios as rpt_cantidad',
                'p.codigoSKU',
                'p.unidadesporcaja',
                'c.RIF',
                'c.cep as client_cep',
                'c.tp1_code as tp1code',
                'v.IdCliente'
            )->get();

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
                    'tp1code'       => $row->tp1code ?? '',
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
