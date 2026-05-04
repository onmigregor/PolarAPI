<?php

namespace Modules\CompanyRoute\Console;

use Illuminate\Console\Command;
use Modules\CompanyRoute\Actions\SyncRouteMetadataAction;

class SyncRouteMetadata extends Command
{
    protected $signature = 'routes:sync-metadata';

    protected $description = 'Sincroniza metadatos de dirección y sub-región desde el origen al HUB';

    public function handle(SyncRouteMetadataAction $action): int
    {
        $this->info('Iniciando sincronización de metadatos de rutas...');

        $result = $action->execute();

        $this->table(['Métrica', 'Valor'], [
            ['Rutas actualizadas en HUB', $result['hub_updated']],
            ['Tenants procesados', $result['tenants_processed']],
            ['Errores', count($result['errors'])],
        ]);

        if (!empty($result['errors'])) {
            $this->warn('Detalle de errores:');
            foreach ($result['errors'] as $error) {
                $this->error($error);
            }
        }

        $this->info('Proceso completado.');
        return 0;
    }
}
