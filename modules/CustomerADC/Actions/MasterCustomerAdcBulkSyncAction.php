<?php

namespace Modules\CustomerADC\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Modules\CustomerADC\Models\MasterAdcPolar;

class MasterCustomerAdcBulkSyncAction
{
    private const CREATE_ADC_POLAR_SQL = "
        CREATE TABLE IF NOT EXISTS `adc_polar` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `fq_redi` varchar(255) DEFAULT NULL,
            `cus_code` varchar(255) DEFAULT NULL,
            `marca` varchar(255) DEFAULT NULL,
            `no_serie` varchar(255) DEFAULT NULL,
            `no_serial` varchar(255) DEFAULT NULL,
            `no_activo` varchar(255) DEFAULT NULL,
            `empresa` varchar(255) DEFAULT NULL,
            `estado` varchar(255) DEFAULT NULL,
            `tipo_activo` varchar(255) DEFAULT NULL,
            `es_propio` tinyint(1) DEFAULT 0,
            `imagen` varchar(100) DEFAULT '',
            `ubicacion_imagen` varchar(255) DEFAULT '',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_no_serie_polar` (`no_serie`),
            KEY `idx_cus_code_polar` (`cus_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    private const CREATE_TENANT_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `adc_datos` (
            `id_adc` int(11) NOT NULL AUTO_INCREMENT,
            `IdCliente` bigint(20) NOT NULL,
            `serial` varchar(100) NOT NULL,
            `no_activo` varchar(100) DEFAULT NULL,
            `modelo` varchar(100) DEFAULT NULL,
            `condicion` varchar(50) DEFAULT 'CONSISTENTE',
            `descripcion` text DEFAULT NULL,
            `es_propio` tinyint(1) DEFAULT 0,
            `pertenece_a` varchar(60) NOT NULL DEFAULT 'POLAR',
            `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
            `imagen` varchar(30) NOT NULL DEFAULT '',
            `ubicacion_imagen` varchar(100) NOT NULL DEFAULT '',
            PRIMARY KEY (`id_adc`),
            UNIQUE KEY `idx_serial` (`serial`),
            KEY `idx_id_cliente` (`IdCliente`),
            CONSTRAINT `fk_adc_cliente` FOREIGN KEY (`IdCliente`) REFERENCES `clientes` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    public function execute(array $data)
    {
        Log::info("Iniciando sincronización masiva de Equipos ADC (Hacia adc_polar y adc_datos)");

        // 1. Mapear data del Admin a la estructura de la Maestra Central
        $syncData = array_map(function($item) {
            return [
                'cus_code'    => $item['cus_code'] ?? null,
                'serial'      => $item['no_serie'] ?? null,
                'no_activo'   => $item['no_activo'] ?? null,
                'no_serial'   => $item['no_serial'] ?? null,
                'modelo'      => $item['marca'] ?? null,
                'descripcion' => $item['tipo_activo'] ?? null,
                'condicion'   => strtoupper($item['estado'] ?? 'CONSISTENTE'),
                'es_propio'   => 0,
                'pertenece_a' => $item['empresa'] ?? 'POLAR',
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }, $data);

        // 2. Upsert masivo en la tabla maestra central
        $this->upsertMasterData($syncData);

        // 3. Obtener la data con su respectivo tenant (db_name)
        $recordsWithTenants = DB::table('master_adc_datos_polar as adc')
            ->join('master_client_polar as clients', 'clients.cus_code', '=', 'adc.cus_code')
            ->join('company_routes as routes', 'routes.id', '=', 'clients.company_route_id')
            ->select('adc.*', 'routes.db_name')
            ->get()
            ->groupBy('db_name');

        Log::info("Equipos ADC agrupados por tenant: " . count($recordsWithTenants) . " tenants encontrados.");

        $results = [];

        // 4. Distribuir a cada tenant
        foreach ($recordsWithTenants as $dbName => $records) {
            try {
                $this->syncToTenant($dbName, $records->toArray());
                $results[$dbName] = 'Success';
            } catch (\Exception $e) {
                Log::error("Error sincronizando ADC al tenant {$dbName}: " . $e->getMessage());
                $results[$dbName] = 'Error: ' . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'tenants_processed' => count($results),
            'details' => $results
        ];
    }

    protected function upsertMasterData(array $syncData)
    {
        $chunks = array_chunk($syncData, 500);
        foreach ($chunks as $chunk) {
            MasterAdcPolar::upsert($chunk, ['serial'], [
                'cus_code', 'no_activo', 'no_serial', 'modelo', 'descripcion', 'condicion', 'pertenece_a', 'updated_at'
            ]);
        }
    }

    protected function syncToTenant(string $dbName, array $records)
    {
        config(['database.connections.tenant_sync' => array_merge(
            config('database.connections.mysql'),
            ['database' => $dbName]
        )]);
        
        DB::purge('tenant_sync');
        $tenantConnection = DB::connection('tenant_sync');

        // A. Asegurar ambas tablas
        try {
            $tenantConnection->statement(self::CREATE_ADC_POLAR_SQL);
            $tenantConnection->statement(self::CREATE_TENANT_TABLE_SQL);
            
            // Parche para no_activo en tabla legacy si ya existía
            $columns = $tenantConnection->select("SHOW COLUMNS FROM `adc_datos` LIKE 'no_activo'");
            if (empty($columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `no_activo` varchar(100) DEFAULT NULL AFTER `serial` ");
            }
        } catch (\Exception $e) {
            Log::warning("Tenant {$dbName}: Error al crear/verificar tablas: " . $e->getMessage());
        }

        // B. Mapa de IdCliente
        $cusCodes = array_unique(array_column($records, 'cus_code'));
        $clientMap = $tenantConnection->table('clientes')->whereIn('cep', $cusCodes)->pluck('IdCliente', 'cep');

        // C. Preparar registros para ambas tablas
        $polarRecords = [];
        $datosRecords = [];

        foreach ($records as $record) {
            $record = (array)$record;
            
            // Data para adc_polar (Maestra Limpia)
            $polarRecords[] = [
                'fq_redi'     => $record['fq_redi'] ?? null,
                'cus_code'    => $record['cus_code'],
                'marca'       => $record['modelo'],
                'no_serie'    => $record['serial'],
                'no_serial'   => $record['no_serial'] ?? null,
                'no_activo'   => $record['no_activo'],
                'empresa'     => $record['pertenece_a'],
                'estado'      => $record['condicion'],
                'tipo_activo' => $record['descripcion'],
                'created_at'  => $record['created_at'],
                'updated_at'  => $record['updated_at'],
            ];

            // Data para adc_datos (Compatibilidad App)
            if (isset($clientMap[$record['cus_code']])) {
                $datosRecords[] = [
                    'IdCliente'   => $clientMap[$record['cus_code']],
                    'serial'      => $record['serial'],
                    'no_activo'   => $record['no_activo'],
                    'modelo'      => $record['modelo'],
                    'condicion'   => $record['condicion'],
                    'descripcion' => $record['descripcion'],
                    'pertenece_a' => $record['pertenece_a'],
                    'fecha_registro' => $record['created_at'],
                ];
            }
        }

        // D. Upsert masivo en ambas tablas
        if (!empty($polarRecords)) {
            $chunks = array_chunk($polarRecords, 500);
            foreach ($chunks as $chunk) {
                $tenantConnection->table('adc_polar')->upsert($chunk, ['no_serie'], [
                    'fq_redi', 'cus_code', 'marca', 'no_serial', 'no_activo', 'empresa', 'estado', 'tipo_activo', 'updated_at'
                ]);
            }
        }

        if (!empty($datosRecords)) {
            $chunks = array_chunk($datosRecords, 500);
            foreach ($chunks as $chunk) {
                $tenantConnection->table('adc_datos')->upsert($chunk, ['serial'], [
                    'IdCliente', 'no_activo', 'modelo', 'condicion', 'descripcion', 'pertenece_a'
                ]);
            }
        }
    }
}
