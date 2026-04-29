<?php

namespace Modules\MasterGeneral\Actions;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\MasterGeneral\Models\MasterGeneral;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterGeneralBulkSyncAction
{
    private const CREATE_TENANT_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `generales` (
            `reaCode` varchar(100) NOT NULL,
            `reaName` varchar(100) DEFAULT NULL,
            `reaNoVisit` tinyint(1) DEFAULT '0',
            `reaNoSale` tinyint(1) DEFAULT '0',
            `reaNoCollect` tinyint(1) DEFAULT '0',
            `reaNoDelivery` tinyint(1) DEFAULT '0',
            `reaNoReturnPickUp` tinyint(1) DEFAULT '0',
            `reaDeliveryDifference` tinyint(1) DEFAULT '0',
            `reaReturn` tinyint(1) DEFAULT '0',
            `reaDamageReturn` tinyint(1) DEFAULT '0',
            `reaNoInventory` tinyint(1) DEFAULT '0',
            `reaPercentageAcknoledgment` decimal(11,2) DEFAULT NULL,
            `reaStatus` tinyint(1) DEFAULT '1',
            `reaAsset` tinyint(1) DEFAULT '0',
            `reaBouncedCheck` tinyint(1) DEFAULT '0',
            `reaNoCollectionArdocument` tinyint(1) DEFAULT '0',
            `reaNoBarCodeReading` tinyint(1) DEFAULT '0',
            `reaHeader` tinyint(1) DEFAULT NULL,
            `reaCancelInvoice` tinyint(1) DEFAULT '0',
            `reaHos` tinyint(1) DEFAULT '0',
            `deleted` tinyint(1) DEFAULT '0',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`reaCode`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    public function execute(array $data): array
    {
        Log::channel('single')->info("=== INICIO DE SINCRONIZACIÓN DE MOTIVOS (GENERALES) ===");
        
        $results = [
            'master_synced' => 0,
            'pushed_to_tenants' => 0,
            'tenants_processed' => 0,
            'errors' => [],
        ];

        if (empty($data)) {
            Log::channel('single')->warning("No se recibieron motivos para sincronizar.");
            return $results;
        }

        $fillable = (new MasterGeneral)->getFillable();
        $updateColumns = array_diff($fillable, ['reaCode']);
        $updateColumns[] = 'updated_at';

        $syncData = array_map(function($item) use ($fillable) {
            $row = array_intersect_key($item, array_flip($fillable));
            $row['created_at'] = now();
            $row['updated_at'] = now();
            return $row;
        }, $data);

        // 1. Guardar en Master
        try {
            MasterGeneral::upsert($syncData, ['reaCode'], $updateColumns);
            $results['master_synced'] = count($syncData);
            Log::channel('single')->info("Master Table: Upsert exitoso de " . count($syncData) . " motivos.");
        } catch (\Exception $e) {
            Log::channel('single')->error("Error en Master Table: " . $e->getMessage());
            $results['errors'][] = "Master Error: " . $e->getMessage();
        }

        // 2. Obtener Tenants únicos (Los motivos se envían a TODOS)
        $tenants = CompanyRoute::whereNotNull('db_name')
            ->distinct()
            ->pluck('db_name')
            ->toArray();

        foreach ($tenants as $dbName) {
            try {
                Config::set('database.connections.tenant.database', $dbName);
                DB::purge('tenant');

                // A. Health Check: Validar si la base de datos es accesible
                try {
                    DB::connection('tenant')->getPdo();
                } catch (\Exception $e) {
                    Log::channel('single')->warning("Tenant {$dbName}: Base de datos inaccesible. Saltando... Error: " . $e->getMessage());
                    $results['errors'][] = "Tenant {$dbName}: DB Unreachable";
                    continue; // Saltamos al siguiente tenant de inmediato
                }

                // B. Asegurar Tabla
                DB::connection('tenant')->statement(self::CREATE_TENANT_TABLE_SQL);

                // C. UPSERT en Tenant
                DB::connection('tenant')
                    ->table('generales')
                    ->upsert($syncData, ['reaCode'], $updateColumns);

                $results['pushed_to_tenants'] += count($syncData);
                $results['tenants_processed']++;
                
                Log::channel('single')->info("Tenant {$dbName}: Sincronización de motivos exitosa.");

            } catch (\Exception $e) {
                Log::channel('single')->error("Error en Tenant {$dbName}: " . $e->getMessage());
                $results['errors'][] = "Tenant {$dbName}: " . $e->getMessage();
            }
        }

        Log::channel('single')->info("=== FIN DE SINCRONIZACIÓN DE MOTIVOS ===");
        return $results;
    }
}
