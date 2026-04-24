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
            `serial` varchar(100) NOT NULL,
            `modelo` varchar(100) DEFAULT NULL,
            `condicion` varchar(50) DEFAULT 'FUNCIONAL',
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
        Log::info("Iniciando sincronización masiva de Equipos ADC (Con Integridad Referencial)");

        // 1. Mapear data del Admin a la estructura de la Maestra Central
        $syncData = array_map(function($item) {
            return [
                'cus_code'    => $item['cus_code'] ?? null,
                'serial'      => $item['no_serie'] ?? null,
                'modelo'      => $item['marca'] ?? null,
                'descripcion' => $item['tipo_activo'] ?? null,
                'condicion'   => 'FUNCIONAL',
                'es_propio'   => 0,
                'pertenece_a' => 'POLAR',
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

        // 4. Distribuir a cada tenant con el cruce de IdCliente
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
                'cus_code', 'modelo', 'descripcion', 'updated_at'
            ]);
        }
    }

    protected function syncToTenant(string $dbName, array $records)
    {
        // Configurar conexión al tenant
        config(['database.connections.tenant_sync' => array_merge(
            config('database.connections.mysql'),
            ['database' => $dbName]
        )]);
        
        DB::purge('tenant_sync');
        $tenantConnection = DB::connection('tenant_sync');

        // A. Asegurar tabla adc_datos (con FK a clientes)
        try {
            $tenantConnection->statement(self::CREATE_TENANT_TABLE_SQL);
        } catch (\Exception $e) {
            // Si la tabla ya existe pero no tiene la FK, intentamos añadirla
            if (!str_contains($e->getMessage(), 'already exists')) {
                Log::warning("Tenant {$dbName}: Error al crear/verificar tabla adc_datos: " . $e->getMessage());
            }
        }

        // B. Obtener mapa de IdCliente del Tenant usando el código (columna 'cep')
        $cusCodes = array_unique(array_column($records, 'cus_code'));
        $clientMap = $tenantConnection->table('clientes')
            ->whereIn('cep', $cusCodes)
            ->pluck('IdCliente', 'cep');

        // C. Transformar data para el tenant
        $tenantRecords = [];
        foreach ($records as $record) {
            $record = (array)$record;
            $cusCode = $record['cus_code'];

            if (!isset($clientMap[$cusCode])) {
                continue;
            }

            $tenantRecords[] = [
                'IdCliente'      => $clientMap[$cusCode],
                'serial'         => $record['serial'],
                'modelo'         => $record['modelo'],
                'condicion'      => 'FUNCIONAL',
                'descripcion'    => $record['descripcion'],
                'es_propio'      => 0,
                'pertenece_a'    => 'POLAR',
                'fecha_registro' => $record['created_at'] ?? now(),
                'imagen'         => '',
                'ubicacion_imagen' => '',
            ];
        }

        // D. Upsert masivo en el tenant
        if (!empty($tenantRecords)) {
            $chunks = array_chunk($tenantRecords, 500);
            foreach ($chunks as $chunk) {
                $tenantConnection->table('adc_datos')->upsert($chunk, ['serial'], [
                    'IdCliente', 'modelo', 'descripcion', 'condicion', 'es_propio', 'pertenece_a'
                ]);
            }
        }
    }
}
