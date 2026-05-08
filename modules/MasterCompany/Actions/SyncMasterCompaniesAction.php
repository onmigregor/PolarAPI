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
            'branches'    => 0,
            'logins'      => 0,
            'login_links' => 0,
            'territories' => 0,
            'errors'      => [],
        ];

        try {
            $db = DB::connection('productos_polar');

            // 1. Sync Branches (Sucursales)
            $branches = $db->table('companies_branches')->get();
            foreach ($branches as $branch) {
                if (empty($branch->brc_code)) continue;
                \Modules\MasterCompany\Models\MasterCompanyBranch::updateOrCreate(
                    ['brc_code' => $branch->brc_code],
                    [
                        'brc_name' => $branch->brc_name,
                        'brc_general_header1' => $branch->brc_general_header1,
                        'reg_code' => $branch->reg_code,
                    ]
                );
                $results['branches']++;
            }

            // 2. Sync Logins
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

            // 3. Sync Login-Branch Links
            $links = $db->table('companies_login_branches')->get();
            foreach ($links as $link) {
                \Modules\MasterCompany\Models\MasterCompanyLoginBranch::updateOrCreate(
                    ['lgn_code' => $link->lgn_code, 'brc_code' => $link->brc_code],
                    []
                );
                $results['login_links']++;
            }

            // 4. Sync Territories
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
