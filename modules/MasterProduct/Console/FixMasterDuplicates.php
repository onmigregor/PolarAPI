<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMasterDuplicates extends Command
{
    protected $signature = 'master:fix-duplicates {--dry-run : Only show what would be fixed}';
    protected $description = 'Identifica y unifica registros duplicados en las tablas maestras del HUB por espacios o mayúsculas.';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info("Iniciando saneamiento de tablas maestras...");

        // 1. Master Products
        $this->fixTable('master_products', 'sku', $dryRun);

        // 2. Master Product Units (Composite Key)
        $this->fixCompositeTable('master_product_units', ['pro_code', 'unt_code'], $dryRun);

        // 3. Master Products Price
        $this->fixCompositeTable('master_products_price_polar', ['material', 'lgnstreet1'], $dryRun);

        $this->info("¡Saneamiento completado!");
    }

    private function fixTable($tableName, $idColumn, $dryRun)
    {
        $this->comment("Revisando tabla: {$tableName} por columna: {$idColumn}");

        $duplicates = DB::table($tableName)
            ->select(DB::raw("TRIM({$idColumn}) as clean_id"), DB::raw("COUNT(*) as total"))
            ->groupBy(DB::raw("TRIM({$idColumn})"))
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info("- No se encontraron duplicados por espacios en {$tableName}.");
            return;
        }

        foreach ($duplicates as $dup) {
            $cleanId = $dup->clean_id;
            $this->warn("- Detectado: '{$cleanId}' tiene {$dup->total} variaciones.");

            $records = DB::table($tableName)
                ->whereRaw("TRIM({$idColumn}) = ?", [$cleanId])
                ->orderBy('updated_at', 'desc')
                ->get();

            $keepId = $records->first()->id;
            $idsToDelete = $records->slice(1)->pluck('id')->toArray();

            if ($dryRun) {
                $this->line("  [DRY RUN] Se mantendría ID {$keepId} y se borrarían: " . implode(', ', $idsToDelete));
            } else {
                DB::table($tableName)->whereIn('id', $idsToDelete)->delete();
                DB::table($tableName)->where('id', $keepId)->update([$idColumn => $cleanId]);
                $this->line("  [FIXED] Unificado en ID {$keepId}.");
            }
        }
    }

    private function fixCompositeTable($tableName, array $columns, $dryRun)
    {
        $this->comment("Revisando tabla: {$tableName} (Clave compuesta)");

        $colSql = implode(', ', array_map(fn($c) => "TRIM({$c})", $columns));
        $groupBySql = implode(', ', array_map(fn($c) => "TRIM({$c})", $columns));

        $duplicates = DB::table($tableName)
            ->select(DB::raw("{$colSql}"), DB::raw("COUNT(*) as total"))
            ->groupBy(DB::raw("{$groupBySql}"))
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info("- No se encontraron duplicados en {$tableName}.");
            return;
        }

        foreach ($duplicates as $dup) {
            $query = DB::table($tableName);
            $values = [];
            foreach ($columns as $col) {
                $cleanVal = trim($dup->{$col} ?? '');
                $query->whereRaw("TRIM({$col}) = ?", [$cleanVal]);
                $values[$col] = $cleanVal;
            }

            $records = $query->orderBy('updated_at', 'desc')->get();
            $keepId = $records->first()->id;
            $idsToDelete = $records->slice(1)->pluck('id')->toArray();

            if ($dryRun) {
                $this->line("  [DRY RUN] Se mantendría ID {$keepId} y se borrarían: " . implode(', ', $idsToDelete));
            } else {
                DB::table($tableName)->whereIn('id', $idsToDelete)->delete();
                DB::table($tableName)->where('id', $keepId)->update($values);
                $this->line("  [FIXED] Unificado en ID {$keepId}.");
            }
        }
    }
}
