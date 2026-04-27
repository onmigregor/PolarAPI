<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Modules\MasterProduct\Actions\SyncMasterProductsFromJsonAction;

class ImportProductsFromJson extends Command
{
    protected $signature = 'products:import-json {file : Path to the JSON file relative to workspace root}';
    protected $description = 'Importa y enriquece el Maestro de Productos desde un archivo JSON granular';

    public function handle(SyncMasterProductsFromJsonAction $action): int
    {
        $filePath = $this->argument('file');
        
        // Ensure absolute path if relative provided
        if (!file_exists($filePath)) {
            $filePath = base_path('../' . $filePath);
        }

        $this->info("Iniciando importación desde: {$filePath}");

        $result = $action->execute($filePath);

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
        }

        $this->info("Resumen de Importación:");
        $this->line("- Total en JSON:   {$result['total_in_json']}");
        $this->line("- Creados:         {$result['created']}");
        $this->line("- Actualizados:    {$result['updated']}");
        
        $this->info("¡Proceso completado!");

        return Command::SUCCESS;
    }
}
