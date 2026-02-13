<?php
declare(strict_types=1);

namespace Modules\MasterGroup\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterGroup\Models\MasterGroup;
use Modules\MasterGroup\Models\External\ExtGroup;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMasterGroupsAction
{
    private const UNIT_MAPPING = [
        'CERVECERIA' => 'L',
        'MALTIN' => 'L',
        'SANGRIA' => 'L',
        'PCV' => 'L',
        'POMAR' => 'L',
        'PEPSICO' => 'L',
        'APC' => 'KG',
        'PG' => 'KG',
        'BIGOTT' => 'KG',
    ];

    public function execute(): array
    {
        $clients = CompanyRoute::where('is_active', true)->get();
        $syncedCount = 0;
        $errors = [];

        foreach ($clients as $client) {
            try {
                // Configure tenant connection
                Config::set('database.connections.tenant.database', $client->db_name);
                DB::purge('tenant');

                // Fetch distinct groups from external database
                $groups = ExtGroup::on('tenant')
                    ->select('nameGroup')
                    ->distinct()
                    ->whereNotNull('nameGroup')
                    ->where('nameGroup', '<>', '')
                    ->get();

                foreach ($groups as $group) {
                    $groupName = trim($group->nameGroup);
                    $unitType = $this->mapUnitType($groupName);

                    MasterGroup::updateOrCreate(
                        ['name' => $groupName],
                        ['unit_type' => $unitType]
                    );

                    $syncedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Error syncing groups for client {$client->name}: " . $e->getMessage());
                $errors[] = [
                    'client' => $client->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced_count' => $syncedCount,
            'clients_processed' => $clients->count(),
            'errors' => $errors,
        ];
    }

    private function mapUnitType(string $groupName): string
    {
        $upperGroupName = strtoupper($groupName);

        return self::UNIT_MAPPING[$upperGroupName] ?? 'OTHER';
    }
}
