<?php

namespace Modules\Report\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Analytics\Services\TenantConnectionService;
use Modules\Report\DataTransferObjects\ExportSalesCsvFilterData;

class ExportSalesWithGiftsBatchAction
{
    public array $errors = [];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    /**
     * Extrae ÚNICAMENTE las ventas que tienen obsequios asociados en el rango indicado.
     */
    public function execute(ExportSalesCsvFilterData $filters, string $table = 'company_routes'): array
    {
        $this->errors = [];
        $materialsMap = DB::table('master_materiales')->pluck('untcode', 'material')->toArray();
        $cepOcasional = DB::table('master_generals')->where('code', 'CLTE_OCASIONAL_CEP')->value('value');

        $clients = $this->tenantService->resolveClients(null, $table);
        $allRows = [];

        $this->tenantService->forEachTenant($clients, function ($client) use (&$allRows, $filters, $materialsMap, $cepOcasional) {
            $routeCode = strtoupper($client->route_name ?? '');
            $cep = str_pad((string)($client->cep ?? ''), 10, '0', STR_PAD_LEFT);

            // Obtener ventas que tienen al menos un obsequio en seguimiento_cajas_promocion
            $giftSaleIds = DB::connection('tenant')
                ->table('seguimiento_cajas_promocion')
                ->pluck('id_venta')
                ->unique()
                ->toArray();

            if (empty($giftSaleIds)) {
                return;
            }

            $queryBase = DB::connection('tenant')
                ->table('ventaspxc as v')
                ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
                ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                ->leftJoin('clientes as c', 'v.IdCliente', '=', 'c.IdCliente')
                ->whereIn('v.IdVenta', $giftSaleIds)
                ->where('v.eliminado', 0)
                ->where('vd.eliminado', 0);

            if ($filters->start_date && $filters->end_date) {
                $queryBase->whereBetween('v.Fecha', [$filters->start_date . ' 00:00:00', $filters->end_date . ' 23:59:59']);
            } elseif ($filters->start_date) {
                $queryBase->whereDate('v.Fecha', $filters->start_date);
            }

            $hasDistTable = Schema::connection('tenant')->hasTable('lotes_distribucion_entregas');
            if ($hasDistTable) {
                $queryBase->leftJoin('lotes_distribucion_entregas as lde', 'v.IdVenta', '=', 'lde.IdVenta');
            }

            $queryBase->select(
                'v.IdVenta',
                'v.Fecha',
                'v.IdCliente',
                'vd.cantidad',
                'p.codigoSKU',
                'c.RIF',
                'c.cep as client_cep'
            );

            if ($hasDistTable) {
                $queryBase->addSelect('lde.reaCode');
            }

            $salesData = $queryBase->get();

            foreach ($salesData as $row) {
                $motivo = $row->reaCode ?? '';
                $sku = $row->codigoSKU ?? '';
                $um = $materialsMap[$sku] ?? 'PZA';

                $clientCep = !empty($row->client_cep) ? $row->client_cep : $cepOcasional;
                if (!empty($clientCep)) {
                    $clientCep = str_pad((string)$clientCep, 10, '0', STR_PAD_LEFT);
                } else {
                    $clientCep = '';
                }

                $allRows[] = [
                    'fq_redi'       => $cep,
                    'cep'           => $clientCep,
                    'fecha'         => Carbon::parse($row->Fecha)->format('d.m.Y'),
                    'deudor'        => $row->IdCliente,
                    'doc_fq_redi'   => $row->IdVenta,
                    'material'      => $row->codigoSKU,
                    'cantidad'      => $row->cantidad,
                    'um'            => $um,
                    'rif_ci_clte'   => $row->RIF ?? '',
                    'cl_doc'        => 'FVTA',
                    'motivo'        => $motivo,
                ];
            }
        });

        return $allRows;
    }

    /**
     * Genera el archivo comprimido ZIP con las ventas de obsequios
     */
    public function generateZipReport(array $rows, string $disk = 'sftp_ventas', string $filenamePrefix = 'VENTAS_OBSEQUIOS_ESPECIAL'): string
    {
        $headers = [
            'FQ/REDI', 'Fecha Creacion', 'Deudor', 'Doc FQ/REDI',
            'material', 'Cantidad', 'UM', 'RIF_CI_CLTE', 'Cl. Doc', 'Motivo'
        ];

        $csvLines = [];
        $csvLines[] = implode(';', $headers);

        foreach ($rows as $r) {
            $csvLines[] = implode(';', [
                $r['fq_redi'],
                $r['fecha'],
                $r['deudor'],
                $r['doc_fq_redi'],
                $r['material'],
                $r['cantidad'],
                $r['um'],
                $r['rif_ci_clte'],
                $r['cl_doc'],
                $r['motivo'],
            ]);
        }

        $txtContent = implode("\r\n", $csvLines) . "\r\n";
        $dateSuffix = now()->format('Ymd_His');
        $txtFilename = "{$filenamePrefix}_{$dateSuffix}.txt";
        $zipFilename = "{$filenamePrefix}_{$dateSuffix}.ZIP";

        // Crear ZIP en memoria
        $zip = new \ZipArchive();
        $tempZipPath = tempnam(sys_get_temp_dir(), 'zip');
        
        if ($zip->open($tempZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString($txtFilename, $txtContent);
            $zip->close();
        }

        $zipBinary = file_get_contents($tempZipPath);
        @unlink($tempZipPath);

        if (config('app.env') === 'local') {
            if (!file_exists(storage_path('ftp'))) {
                mkdir(storage_path('ftp'), 0777, true);
            }
            file_put_contents(storage_path("ftp/{$zipFilename}"), $zipBinary);
        } else {
            Storage::disk($disk)->put($zipFilename, $zipBinary);
        }

        return $zipFilename;
    }
}
