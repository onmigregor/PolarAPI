<?php

namespace Modules\CustomerADC\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Modules\CustomerADC\Models\MasterAdcPolar;

class MasterCustomerAdcBulkSyncAction
{
    public function execute(array $data)
    {
        Log::info("Iniciando sincronización masiva de Equipos ADC (API Central)");

        // 1. Normalizar data y añadir timestamps locales de la API
        $syncData = array_map(function($item) {
            return [
                'fq_redi'     => $item['fq_redi'] ?? null,
                'cus_code'    => $item['cus_code'] ?? null,
                'marca'       => $item['marca'] ?? null,
                'no_serie'    => $item['no_serie'] ?? null,
                'no_serial'   => $item['no_serial'] ?? null,
                'no_activo'   => $item['no_activo'] ?? null,
                'empresa'     => $item['empresa'] ?? null,
                'estado'      => $item['estado'] ?? null,
                'tipo_activo' => $item['tipo_activo'] ?? null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }, $data);

        // 2. Upsert masivo en la tabla maestra
        $this->upsertMasterData($syncData);

        // 3. Obtener la data con su respectivo tenant (db_name)
        // Usamos TRIM para normalizar los ceros a la izquierda en cus_code
        $recordsWithTenants = DB::table('master_adc_polar as adc')
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
            MasterAdcPolar::upsert($chunk, ['no_serie'], [
                'fq_redi', 'cus_code', 'marca', 'no_serial', 'no_activo', 'empresa', 'estado', 'tipo_activo', 'updated_at'
            ]);
        }
    }

    protected function syncToTenant(string $dbName, array $records)
    {
        // Cambiar conexión al tenant
        config(['database.connections.tenant_sync' => array_merge(
            config('database.connections.mysql'),
            ['database' => $dbName]
        )]);
        
        DB::purge('tenant_sync');
        $tenantConnection = DB::connection('tenant_sync');

        // Verificar/Crear tabla adc_polar en el tenant
        if (!Schema::connection('tenant_sync')->hasTable('adc_polar')) {
            Schema::connection('tenant_sync')->create('adc_polar', function ($table) {
                $table->id();
                $table->string('fq_redi')->nullable();
                $table->string('cus_code')->nullable()->index();
                $table->string('marca')->nullable();
                $table->string('no_serie')->nullable()->unique();
                $table->string('no_serial')->nullable();
                $table->string('no_activo')->nullable();
                $table->string('empresa')->nullable();
                $table->string('estado')->nullable();
                $table->string('tipo_activo')->nullable();
                $table->timestamps();
            });
            Log::info("Tabla 'adc_polar' creada en tenant: {$dbName}");
        }

        // Limpiar campos extras que vienen del JOIN (db_name, id original de master, etc.)
        $cleanRecords = array_map(function ($item) {
            $record = (array)$item;
            unset($record['id']);
            unset($record['db_name']);
            return $record;
        }, $records);

        // Upsert masivo en el tenant
        $tenantConnection->table('adc_polar')->upsert($cleanRecords, ['no_serie'], [
            'fq_redi', 'cus_code', 'marca', 'no_serial', 'no_activo', 'empresa', 'estado', 'tipo_activo', 'updated_at'
        ]);
    }
}
