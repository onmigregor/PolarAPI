<?php

namespace Modules\Report\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CompanyRoute\Models\CompanyRoute;

class ExportAdcConsolidatedAction
{
    public function execute(string $table = 'company_routes'): array
    {
        $tenants = CompanyRoute::from($table)->where('is_active', 1)->get();
        $allData = [];

        Log::info("Iniciando consolidación de ADC desde " . $tenants->count() . " tenants.");

        foreach ($tenants as $tenant) {
            try {
                // Configurar conexión dinámica al tenant
                $dbName = $tenant->db_name;
                
                // Verificar si la tabla existe en este tenant antes de consultar
                $tableExists = DB::connection('mysql')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                if (empty($tableExists)) continue;

                config(['database.connections.tenant_report' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $dbName]
                )]);
                DB::purge('tenant_report');
                $tenantConn = DB::connection('tenant_report');

                // Consultar tabla única de inventario adc_polar
                $hasTableDatos = !empty($tenantConn->select("SHOW TABLES LIKE 'adc_polar'"));
                if (!$hasTableDatos) continue;

                // Resolver dinámicamente si la columna es 'no_serie' o 'serial' (legacy)
                $columns = array_map(function($col) {
                    return strtolower($col->Field);
                }, $tenantConn->select("SHOW COLUMNS FROM `adc_polar`"));
                $serialCol = in_array('no_serie', $columns) ? 'no_serie' : 'serial';

                $data = $tenantConn->table('adc_polar as adc')
                    ->join('clientes as c', 'c.IdCliente', '=', 'adc.IdCliente')
                    ->select([
                        DB::raw("'{$tenant->cep}' as fq_redi"),
                        'c.cep as id_customer',
                        'adc.modelo as marca',
                        "adc.{$serialCol} as no_serie",
                        'adc.no_activo',
                        'adc.pertenece_a as empresa',
                        'adc.condicion as estado',
                        'adc.descripcion as tipo_activo',
                        'adc.condicion',
                        'adc.updated_at'
                    ])
                    ->get();

                foreach ($data as $row) {
                    $row = (array)$row;
                    
                    // Lógica de condición: CONSISTENTE -> 1, INCONSISTENTE -> 0
                    $condicionText = strtoupper(trim($row['condicion'] ?? ''));
                    $row['condicion_val'] = ($condicionText === 'CONSISTENTE') ? '1' : '0';

                    $allData[] = $row;
                }

            } catch (\Exception $e) {
                Log::warning("Error extrayendo ADC del tenant {$tenant->db_name}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("Consolidación finalizada. Total registros: " . count($allData));
        return $allData;
    }
}
