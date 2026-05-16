<?php

namespace Modules\Report\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\CompanyRoute\Models\CompanyRoute;

class ExportCustomerConsolidatedAction
{
    /**
     * Ejecuta la consolidación de clientes de todos los tenants activos.
     */
    public function execute(): array
    {
        $tenants = CompanyRoute::where('is_active', 1)->get();
        $allData = [];

        Log::info("Iniciando consolidación de CLIENTES desde " . $tenants->count() . " tenants.");

        foreach ($tenants as $tenant) {
            try {
                $dbName = $tenant->db_name;
                
                // Verificar si la base de datos existe
                $dbExists = DB::connection('mysql')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                if (empty($dbExists)) {
                    Log::warning("Tenant DB {$dbName} no existe físicamente. Saltando...");
                    continue;
                }

                // Configurar conexión dinámica
                config(['database.connections.tenant_report_cust' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $dbName]
                )]);
                DB::purge('tenant_report_cust');
                $tenantConn = DB::connection('tenant_report_cust');

                // Verificar si la tabla clientes existe
                $tableExists = !empty($tenantConn->select("SHOW TABLES LIKE 'clientes'"));
                if (!$tableExists) {
                    Log::warning("Tabla 'clientes' no existe en {$dbName}. Saltando...");
                    continue;
                }

                // Verificar columnas existentes para evitar errores SQL
                $columns = array_column($tenantConn->select("SHOW COLUMNS FROM clientes"), 'Field');
                
                // Definir mapeo con fallbacks
                $selectFields = [
                    DB::raw("'{$tenant->cep}' as fq_redi"),
                    'cep as codigo_cliente',
                    'Cliente as nombre',
                    'RIF as rif',
                    in_array('tp1_code', $columns) ? 'tp1_code as tipo_cliente' : 'TipoCliente as tipo_cliente',
                    'Direccion as direccion',
                    'Ruta as ruta',
                    'TelefonoContacto as telefono',
                    'email',
                    'PersonaContacto as contacto',
                    'latitud',
                    'longitud',
                    in_array('con_code', $columns) ? 'con_code as condicion_pago' : DB::raw("'' as condicion_pago"),
                    in_array('prc_code_for_sale', $columns) ? 'prc_code_for_sale as lista_precios' : DB::raw("'' as lista_precios"),
                    in_array('brc_code', $columns) ? 'brc_code as sucursal' : DB::raw("'' as sucursal"),
                    'status as estado'
                ];

                $data = $tenantConn->table('clientes')->select($selectFields)->get();

                foreach ($data as $row) {
                    $allData[] = (array)$row;
                }

            } catch (\Exception $e) {
                Log::error("Error extrayendo CLIENTES del tenant {$tenant->db_name}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("Consolidación de clientes finalizada. Total registros: " . count($allData));
        return $allData;
    }
}
