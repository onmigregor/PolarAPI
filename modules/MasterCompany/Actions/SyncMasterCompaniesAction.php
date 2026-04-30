<?php

namespace Modules\MasterCompany\Actions;

use Modules\MasterCompany\Models\MasterCompanyLogin;
use Modules\MasterCompany\Models\MasterCompanyTerritory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMasterCompaniesAction
{
    public function execute(): array
    {
        $results = [
            'logins'      => 0,
            'territories' => 0,
            'errors'      => [],
        ];

        try {
            $db = DB::connection('productos_polar');

            // 1. Sync Logins
            $logins = $db->table('companies_logins')->get();
            foreach ($logins as $login) {
                if (empty($login->lgn_code)) continue;
                MasterCompanyLogin::updateOrCreate(
                    ['lgn_code' => $login->lgn_code],
                    [
                        'lgn_name'    => $login->lgn_name,
                        'brc_code'    => $login->brc_code,
                        'lgn_phone'   => $login->lgn_phone,
                        'lgn_street1' => $login->lgn_street1,
                        'lgn_street2' => $login->lgn_street2,
                        'lgn_street3' => $login->lgn_street3,
                        'srg_code'    => $login->srg_code,
                    ]
                );
                $results['logins']++;
            }

            // 2. Sync Territories
            $territories = $db->table('companies_territories')->get();
            foreach ($territories as $territory) {
                if (empty($territory->try_code)) continue;
                MasterCompanyTerritory::updateOrCreate(
                    ['try_code' => $territory->try_code],
                    [
                        'brc_code'  => $territory->brc_code,
                        'lgn_code'  => $territory->lgn_code,
                        'try_name'  => $territory->try_name,
                        'try_email' => $territory->try_email,
                    ]
                );
                $results['territories']++;
            }

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => $e->getMessage(),
            ];
            Log::error('SyncMasterCompanies error: ' . $e->getMessage());
        }

        return $results;
    }
}
