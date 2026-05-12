<?php

namespace Modules\MasterProduct\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\CompanyRoute\Models\CompanyRoute;

class EnsureProductClassificationColumnsExistAction
{
    public function execute(): array
    {
        Log::info("=== INICIO: Verificación de Estructura Class1-4 en Tenants ===");
        
        $tenants = CompanyRoute::where('is_active', true)->get();
        $results = [
            'checked' => 0,
            'updated' => 0,
            'errors'  => 0,
            'details' => []
        ];

        foreach ($tenants as $tenant) {
            try {
                config(['database.connections.tenant.database' => $tenant->db_name]);
                DB::purge('tenant');

                if (!Schema::connection('tenant')->hasTable('productos')) {
                    Log::warning("Tenant {$tenant->name}: Tabla 'productos' no existe. Saltando.");
                    continue;
                }

                $columnsToAdd = [];
                $neededColumns = ['class1', 'class2', 'class3', 'class4'];
                
                foreach ($neededColumns as $col) {
                    if (!Schema::connection('tenant')->hasColumn('productos', $col)) {
                        $columnsToAdd[] = $col;
                    }
                }

                if (empty($columnsToAdd)) {
                    Log::info("Tenant {$tenant->name} ({$tenant->db_name}): Estructura Class1-4 ya está completa.");
                    $results['details'][] = "Tenant {$tenant->name}: OK (Ya existían)";
                } else {
                    Log::info("Tenant {$tenant->name} ({$tenant->db_name}): Creando campos faltantes: " . implode(', ', $columnsToAdd));
                    
                    Schema::connection('tenant')->table('productos', function ($table) use ($columnsToAdd) {
                        foreach ($columnsToAdd as $col) {
                            $table->string($col, 100)->nullable();
                        }
                    });

                    Log::info("Tenant {$tenant->name}: Estructura actualizada con éxito.");
                    $results['updated']++;
                    $results['details'][] = "Tenant {$tenant->name}: ACTUALIZADO (Campos creados: " . implode(', ', $columnsToAdd) . ")";
                }

                $results['checked']++;

            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("Tenant {$tenant->name}: Error al verificar/crear estructura: " . $e->getMessage());
                $results['details'][] = "Tenant {$tenant->name}: ERROR - " . $e->getMessage();
            }
        }

        Log::info("=== FIN: Verificación de Estructura. Revisados: {$results['checked']}, Actualizados: {$results['updated']}, Errores: {$results['errors']} ===");
        
        return $results;
    }
}
