<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixMasterDuplicates extends Command
{
    protected $signature = 'master:fix-duplicates {--dry-run : Only show what would be fixed}';
    protected $description = 'Identifica y unifica registros duplicados en las tablas maestras del HUB por espacios o mayúsculas.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $logDate = now()->format('Y-m-d');
        $auditLogFile = storage_path("logs/master_fix_duplicates_{$logDate}.log");

        $startMsg = "[" . now()->toDateTimeString() . "] Iniciando saneamiento nocturno de tablas maestras..." . ($dryRun ? " [DRY RUN]" : "");
        $this->info($startMsg);
        file_put_contents($auditLogFile, $startMsg . PHP_EOL, FILE_APPEND);

        $totalFixed = 0;

        // 1. Master Products
        $totalFixed += $this->fixTable('master_products', 'sku', $dryRun, $auditLogFile);

        // 2. Master Product Units (Composite Key)
        $totalFixed += $this->fixCompositeTable('master_product_units', ['pro_code', 'unt_code'], $dryRun, $auditLogFile);

        // 3. Master Products Price
        $totalFixed += $this->fixCompositeTable('master_products_price_polar', ['material', 'lgnstreet1'], $dryRun, $auditLogFile);

        $endMsg = "[" . now()->toDateTimeString() . "] ¡Saneamiento completado! Total de registros desduplicados: {$totalFixed}";
        $this->info($endMsg);
        file_put_contents($auditLogFile, $endMsg . PHP_EOL . str_repeat('-', 80) . PHP_EOL, FILE_APPEND);

        Log::channel('single')->info("master:fix-duplicates completado. Total arreglados: {$totalFixed}. Informe en: {$auditLogFile}");

        return Command::SUCCESS;
    }

    private function fixTable($tableName, $idColumn, $dryRun, $auditLogFile): int
    {
        $this->comment("Revisando tabla: {$tableName} por columna: {$idColumn}");

        $duplicates = DB::table($tableName)
            ->select(DB::raw("TRIM({$idColumn}) as clean_id"), DB::raw("COUNT(*) as total"))
            ->groupBy(DB::raw("TRIM({$idColumn})"))
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $msg = "- No se encontraron duplicados por espacios en {$tableName}.";
            $this->info($msg);
            file_put_contents($auditLogFile, "  " . $msg . PHP_EOL, FILE_APPEND);
            return 0;
        }

        $fixedCount = 0;

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
                $detail = "  [DRY RUN] Tabla: {$tableName} | Clave: '{$cleanId}' | Mantiene ID {$keepId} | Eliminaría IDs: " . implode(', ', $idsToDelete);
                $this->line($detail);
                file_put_contents($auditLogFile, $detail . PHP_EOL, FILE_APPEND);
            } else {
                DB::table($tableName)->whereIn('id', $idsToDelete)->delete();
                DB::table($tableName)->where('id', $keepId)->update([$idColumn => $cleanId]);
                $detail = "  [FIXED] Tabla: {$tableName} | Clave unificada: '{$cleanId}' | Conservado ID {$keepId} | Eliminados IDs: " . implode(', ', $idsToDelete);
                $this->line($detail);
                file_put_contents($auditLogFile, $detail . PHP_EOL, FILE_APPEND);
                $fixedCount += count($idsToDelete);
            }
        }

        return $fixedCount;
    }

    private function fixCompositeTable($tableName, array $columns, $dryRun, $auditLogFile): int
    {
        $this->comment("Revisando tabla: {$tableName} (Clave compuesta)");

        $colSql = implode(', ', array_map(fn($c) => "TRIM({$c}) as {$c}", $columns));
        $groupBySql = implode(', ', array_map(fn($c) => "TRIM({$c})", $columns));

        $duplicates = DB::table($tableName)
            ->select(DB::raw("{$colSql}"), DB::raw("COUNT(*) as total"))
            ->groupBy(DB::raw("{$groupBySql}"))
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $msg = "- No se encontraron duplicados en {$tableName}.";
            $this->info($msg);
            file_put_contents($auditLogFile, "  " . $msg . PHP_EOL, FILE_APPEND);
            return 0;
        }

        $fixedCount = 0;

        foreach ($duplicates as $dup) {
            $query = DB::table($tableName);
            $values = [];
            $keyDesc = [];
            foreach ($columns as $col) {
                $cleanVal = trim($dup->{$col} ?? '');
                $query->whereRaw("TRIM({$col}) = ?", [$cleanVal]);
                $values[$col] = $cleanVal;
                $keyDesc[] = "{$col}='{$cleanVal}'";
            }

            $records = $query->orderBy('updated_at', 'desc')->get();
            $keepId = $records->first()->id;
            $idsToDelete = $records->slice(1)->pluck('id')->toArray();

            $strKey = implode(' + ', $keyDesc);

            if ($dryRun) {
                $detail = "  [DRY RUN] Tabla: {$tableName} | Clave: [{$strKey}] | Mantiene ID {$keepId} | Eliminaría IDs: " . implode(', ', $idsToDelete);
                $this->line($detail);
                file_put_contents($auditLogFile, $detail . PHP_EOL, FILE_APPEND);
            } else {
                DB::table($tableName)->whereIn('id', $idsToDelete)->delete();
                DB::table($tableName)->where('id', $keepId)->update($values);
                $detail = "  [FIXED] Tabla: {$tableName} | Clave unificada: [{$strKey}] | Conservado ID {$keepId} | Eliminados IDs: " . implode(', ', $idsToDelete);
                $this->line($detail);
                file_put_contents($auditLogFile, $detail . PHP_EOL, FILE_APPEND);
                $fixedCount += count($idsToDelete);
            }
        }

        return $fixedCount;
    }
}
