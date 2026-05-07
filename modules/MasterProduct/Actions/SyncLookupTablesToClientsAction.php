<?php

namespace Modules\MasterProduct\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\Analytics\Services\TenantConnectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SyncLookupTablesToClientsAction
{
    private const TABLES_MAP = [
        'master_product_families'   => 'product_families',
        'master_product_categories' => 'product_categories',
        'master_product_class_3'    => 'product_class_3',
        'master_product_class_4'    => 'product_class_4',
        'master_product_units'      => 'product_units',
    ];

    public function __construct(
        private TenantConnectionService $tenantService
    ) {}

    public function execute(): array
    {
        $clients = $this->tenantService->resolveClients();
        $results = [
            'tenants_processed' => 0,
            'tables_synced'     => 0,
            'errors'            => [],
        ];

        // 1. Cargar datos del HUB una sola vez
        $hubData = [];
        foreach (self::TABLES_MAP as $hubTable => $tenantTable) {
            $hubData[$hubTable] = DB::table($hubTable)->get();
        }

        // 2. Procesar cada Tenant
        $tenantResults = $this->tenantService->forEachTenant($clients, function ($client) use ($hubData) {
            $syncedCount = 0;

            foreach (self::TABLES_MAP as $hubTable => $tenantTable) {
                // Asegurar estructura
                $this->ensureTableExists($tenantTable, $hubTable);

                // Sincronizar datos (Truncate + Insert para estas tablas de catálogo)
                // Nota: Usamos transacciones o simplemente limpieza total ya que son tablas pequeñas
                DB::connection('tenant')->table($tenantTable)->truncate();
                
                $rows = $hubData[$hubTable]->map(function($row) {
                    $data = (array)$row;
                    unset($data['id']); // Dejamos que el Tenant genere sus propios IDs o use los mismos si no hay conflictos
                    return $data;
                })->toArray();

                if (!empty($rows)) {
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::connection('tenant')->table($tenantTable)->insert($chunk);
                    }
                }
                $syncedCount++;
            }

            return ['synced' => $syncedCount];
        });

        foreach ($tenantResults['results'] as $res) {
            $results['tenants_processed']++;
            $results['tables_synced'] += $res['data']['synced'] ?? 0;
        }

        $results['errors'] = $tenantResults['errors'];

        return $results;
    }

    private function ensureTableExists(string $tenantTable, string $hubTable): void
    {
        if (Schema::connection('tenant')->hasTable($tenantTable)) {
            return;
        }

        // Crear tabla dinámicamente basada en la estructura del HUB (simplificada)
        Schema::connection('tenant')->create($tenantTable, function ($table) use ($hubTable) {
            $table->id();
            
            switch ($hubTable) {
                case 'master_product_families':
                    $table->string('cl1_code', 20)->unique();
                    $table->string('cl1_name', 100)->nullable();
                    break;
                case 'master_product_categories':
                    $table->string('cl2_code', 20)->unique();
                    $table->string('cl1_code', 20)->nullable();
                    $table->string('cl2_name', 100)->nullable();
                    break;
                case 'master_product_class_3':
                    $table->string('cl3_code', 30)->unique();
                    $table->string('cl2_code', 20)->nullable();
                    $table->string('cl3_name', 150)->nullable();
                    break;
                case 'master_product_class_4':
                    $table->string('cl4_code', 60)->unique();
                    $table->string('cl3_code', 30)->nullable();
                    $table->string('cl4_name', 200)->nullable();
                    $table->string('brand_code', 20)->nullable();
                    $table->string('segment_code', 20)->nullable();
                    break;
                case 'master_product_units':
                    $table->string('pro_code', 20);
                    $table->string('unt_code', 5);
                    $table->decimal('pru_multiply_by', 12, 4)->nullable();
                    $table->string('pru_divide_by', 20)->nullable();
                    $table->string('pru_bar_code', 30)->nullable();
                    $table->index(['pro_code', 'unt_code']);
                    break;
            }

            $table->timestamps();
            $table->softDeletes();
        });
    }
}
