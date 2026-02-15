<?php

namespace Modules\CompanyRoute\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CompanyRoute\Models\CompanyRoute;

class CompanyRouteDatabaseSeeder extends Seeder
{
    public function run()
    {
        $clients = [
            [
                'id' => 1,
                'code' => 'CL001',
                'name' => 'Distribuidora i0512',
                'route_name' => 'I0512',
                'rif' => 'J123456789-7',
                'description' => 'supermercado',
                'fiscal_address' => 'ccs',
                'region_id' => 1,
                'db_name' => 'www_i0512',
                'is_active' => 1,
            ],
            [
                'id' => 2,
                'code' => 'CL002',
                'name' => 'Distribuidora ZANJILI',
                'route_name' => 'ZANJILI',
                'rif' => 'J123456789-7',
                'description' => 'supermercado',
                'fiscal_address' => 'ccs',
                'region_id' => 1,
                'db_name' => 'www_zanjili',
                'is_active' => 1,
            ],
        ];

        foreach ($clients as $client) {
            CompanyRoute::updateOrCreate(
                ['id' => $client['id']],
                $client
            );
        }
    }
}
