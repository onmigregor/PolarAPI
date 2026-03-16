<?php

namespace Modules\MasterProduct\Console;

use Illuminate\Console\Command;
use Modules\MasterProduct\Actions\SyncMasterProductsAction;

class SyncMasterProducts extends Command
{
    protected $signature = 'products:sync';
    protected $description = 'Sincronizar categorías (cl1, cl2, cl3) y unidades desde productosPolarApi';

    public function handle(SyncMasterProductsAction $action): int
    {
        $this->info('Iniciando inyección de jerarquías y unidades desde MAESTRO...');

        $result = $action->execute();

        $this->info("Sincronización completada (origen: polar-productos_api):");
        $this->line("- Unidades sincr.:     {$result['units']}");
        $this->line("- Familias (cl1):      {$result['families']}");
        $this->line("- Categorías (cl2):    {$result['categories']}");
        $this->line("- Clase 3 (cl3):       {$result['class3']}");
        $this->line("- Prod. enriquecidos:  {$result['products']}");
        $this->line("- Prod. Unidades:      {$result['product_units']}");

        if (!empty($result['errors'])) {
            $this->warn('Ocurrieron errores durante la sincronización:');
            foreach ($result['errors'] as $error) {
                $this->error("{$error['source']}: {$error['error']}");
            }
        }

        return Command::SUCCESS;
    }
}
