<?php

namespace Modules\CustomerADC\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Modules\CustomerADC\Models\MasterAdcPolar;

class MasterCustomerAdcBulkSyncAction
{
    private const CREATE_TENANT_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `adc_datos` (
            `id_adc` int(11) NOT NULL AUTO_INCREMENT,
            `IdCliente` bigint(20) NOT NULL,
            `no_serie` varchar(100) NOT NULL,
            `no_activo` varchar(100) DEFAULT NULL,
            `no_serial` varchar(255) DEFAULT NULL,
            `modelo` varchar(100) DEFAULT NULL,
            `condicion` varchar(50) DEFAULT NULL,
            `descripcion` text DEFAULT NULL,
            `es_propio` tinyint(1) DEFAULT 0,
            `pertenece_a` varchar(60) NOT NULL DEFAULT 'POLAR',
            `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
            `imagen` varchar(100) NOT NULL DEFAULT '',
            `ubicacion_imagen` varchar(255) NOT NULL DEFAULT '',
            `fq_redi` varchar(255) DEFAULT NULL,
            `cus_code` varchar(255) DEFAULT NULL,
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id_adc`),
            UNIQUE KEY `idx_no_serie` (`no_serie`),
            KEY `idx_id_cliente` (`IdCliente`),
            KEY `idx_cus_code` (`cus_code`),
            CONSTRAINT `fk_adc_cliente` FOREIGN KEY (`IdCliente`) REFERENCES `clientes` (`IdCliente`) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    public function execute(array $data)
    {
        Log::info("Iniciando sincronización masiva de Equipos ADC (Hacia adc_datos)");

        // Asegurar que la columna condicion de la maestra permita NULL
        try {
            DB::statement("ALTER TABLE `master_adc_datos_polar` MODIFY COLUMN `condicion` varchar(50) DEFAULT NULL");
        } catch (\Exception $e) {
            Log::debug("No se pudo alterar master_adc_datos_polar.condicion: " . $e->getMessage());
        }

        // 1. Mapear data del Admin a la estructura de la Maestra Central (condicion permite NULL)
        $syncData = array_map(function($item) {
            $paddedCusCode = ltrim((string)($item['cus_code'] ?? ''), '0');
            return [
                'cus_code'    => $paddedCusCode,
                'serial'      => $item['no_serie'] ?? null,
                'no_activo'   => $item['no_activo'] ?? null,
                'no_serial'   => $item['no_serial'] ?? null,
                'modelo'      => $item['marca'] ?? null,
                'descripcion' => $item['tipo_activo'] ?? null,
                'condicion'   => !empty($item['estado']) ? strtoupper($item['estado']) : null,
                'es_propio'   => 0,
                'pertenece_a' => $item['empresa'] ?? 'POLAR',
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }, $data);

        // 2. Upsert masivo en la tabla maestra central
        $this->upsertMasterData($syncData);

        // 3. Obtener la data con su respectivo tenant (db_name) y fq_redi
        // Cargamos mapa de rutas: cep (que es el REDI) -> db_name
        $routeMap = DB::table('company_routes')->pluck('db_name', 'cep')->toArray();

        // Mapear serial -> [db_name, fq_redi] desde el payload actual del request
        $adcTenantMap = [];
        foreach ($data as $item) {
            $serial = $item['no_serie'] ?? null;
            $redi = $item['fq_redi'] ?? null;
            if ($serial && $redi) {
                $adcTenantMap[$serial] = [
                    'db_name' => $routeMap[$redi] ?? null,
                    'fq_redi' => $redi
                ];
            }
        }

        // Obtener todos los registros de la tabla maestra central
        $allAdc = DB::table('master_adc_datos_polar')->get();

        // Agruparlos por db_name
        $recordsWithTenants = [];

        // Para resolver por BD (fallback), precargamos los clientes con su db_name y cep de la ruta
        $hasMasterClients = DB::table('master_clients')->count() > 0;
        $clientDbMap = [];
        if ($hasMasterClients) {
            $clientDbMap = DB::table('master_clients as clients')
                ->join('company_routes as routes', 'routes.id', '=', 'clients.company_route_id')
                ->select(DB::raw('CAST(clients.cep AS UNSIGNED) as cep_num'), 'routes.db_name', 'routes.cep as route_cep')
                ->get()
                ->keyBy('cep_num')
                ->map(fn($item) => (array)$item)
                ->toArray();
        } else {
            $clientDbMap = DB::table('master_client_polar as clients')
                ->join('company_routes as routes', 'routes.id', '=', 'clients.company_route_id')
                ->select(DB::raw('CAST(clients.cus_code AS UNSIGNED) as cep_num'), 'routes.db_name', 'routes.cep as route_cep')
                ->get()
                ->keyBy('cep_num')
                ->map(fn($item) => (array)$item)
                ->toArray();
        }

        foreach ($allAdc as $record) {
            $serial = $record->serial;
            $dbName = $adcTenantMap[$serial]['db_name'] ?? null;
            $fqRedi = $adcTenantMap[$serial]['fq_redi'] ?? null;

            if (!$dbName) {
                // Fallback por cliente
                $cusCodeNum = (int)$record->cus_code;
                $clientInfo = $clientDbMap[$cusCodeNum] ?? null;
                if ($clientInfo) {
                    $dbName = $clientInfo['db_name'];
                    $fqRedi = $clientInfo['route_cep'];
                }
            }

            if ($dbName) {
                $recordArray = (array)$record;
                $recordArray['fq_redi'] = $fqRedi;
                $recordsWithTenants[$dbName][] = $recordArray;
            }
        }

        Log::info("Equipos ADC agrupados por tenant: " . count($recordsWithTenants) . " tenants encontrados.");

        $results = [];

        // 4. Distribuir a cada tenant
        foreach ($recordsWithTenants as $dbName => $records) {
            try {
                $this->syncToTenant($dbName, $records);
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

        // A. Asegurar estructura unificada de adc_datos
        try {
            // Crear adc_datos si no existe
            $tenantConnection->statement(self::CREATE_TENANT_TABLE_SQL);
            
            // Parche/Actualización dinámica de columnas si la tabla ya existía
            $columns = array_map(function($col) {
                return strtolower($col->Field);
            }, $tenantConnection->select("SHOW COLUMNS FROM `adc_datos`"));

            // Si existe la columna 'serial' (antigua), renombrarla a 'no_serie'
            if (in_array('serial', $columns) && !in_array('no_serie', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` CHANGE COLUMN `serial` `no_serie` varchar(100) NOT NULL");
                // Actualizar la lista de columnas en memoria
                $columns[] = 'no_serie';
                $columns = array_values(array_filter($columns, function($c) { return $c !== 'serial'; }));
            }

            if (!in_array('no_serie', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `no_serie` varchar(100) NOT NULL AFTER `IdCliente` ");
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD UNIQUE KEY `idx_no_serie` (`no_serie`)");
            }
            if (!in_array('no_activo', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `no_activo` varchar(100) DEFAULT NULL AFTER `no_serie` ");
            }
            if (!in_array('no_serial', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `no_serial` varchar(255) DEFAULT NULL AFTER `no_activo` ");
            }
            if (!in_array('fq_redi', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `fq_redi` varchar(255) DEFAULT NULL AFTER `no_serial` ");
            }
            if (!in_array('cus_code', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `cus_code` varchar(255) DEFAULT NULL AFTER `fq_redi` ");
            }
            if (!in_array('created_at', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `created_at` timestamp NULL DEFAULT NULL ");
            }
            if (!in_array('updated_at', $columns)) {
                $tenantConnection->statement("ALTER TABLE `adc_datos` ADD COLUMN `updated_at` timestamp NULL DEFAULT NULL ");
            }

            // Asegurar que condicion sea nullable
            $tenantConnection->statement("ALTER TABLE `adc_datos` MODIFY COLUMN `condicion` varchar(50) DEFAULT NULL");
        } catch (\Exception $e) {
            Log::warning("Tenant {$dbName}: Error al migrar/verificar tabla adc_datos: " . $e->getMessage());
        }

        // B. Mapa de IdCliente (usamos cep sin ceros a la izquierda)
        $clientMap = $tenantConnection->table('clientes')
            ->select('IdCliente', 'cep')
            ->get()
            ->keyBy(function($c) {
                return ltrim((string)$c->cep, '0');
            })
            ->map(function($c) {
                return $c->IdCliente;
            });
 
        // C. Preparar registros para la tabla unificada adc_datos
        $datosRecords = [];
 
        foreach ($records as $record) {
            $record = (array)$record;
            $paddedCusCode = ltrim((string)$record['cus_code'], '0');

            // Data para adc_datos (Única Tabla de Inventario y Compatibilidad con App)
            if (isset($clientMap[$paddedCusCode])) {
                $datosRecords[] = [
                    'IdCliente'   => $clientMap[$paddedCusCode],
                    'cus_code'    => $paddedCusCode,
                    'fq_redi'     => $record['fq_redi'] ?? null,
                    'no_serie'    => $record['serial'], // El serial del HUB/Admin va a no_serie
                    'no_activo'   => $record['no_activo'],
                    'no_serial'   => $record['no_serial'] ?? null,
                    'modelo'      => $record['modelo'],
                    'descripcion' => $record['descripcion'],
                    'pertenece_a' => $record['pertenece_a'],
                    'fecha_registro' => $record['created_at'],
                    'created_at'  => $record['created_at'],
                    'updated_at'  => $record['updated_at'],
                ];
            }
        }

        // D. Upsert masivo en la tabla única adc_datos
        if (!empty($datosRecords)) {
            $chunks = array_chunk($datosRecords, 500);
            foreach ($chunks as $chunk) {
                $tenantConnection->table('adc_datos')->upsert($chunk, ['no_serie'], [
                    'IdCliente', 'cus_code', 'fq_redi', 'no_activo', 'no_serial', 'modelo', 'descripcion', 'pertenece_a', 'updated_at'
                ]);
            }
        }
    }
}
