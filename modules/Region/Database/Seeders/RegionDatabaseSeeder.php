<?php

namespace Modules\Region\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionDatabaseSeeder extends Seeder
{
    public function run()
    {
        DB::table('regions')->updateOrInsert(
            ['id' => 1],
            [
                'citCode' => 'CT001',
                'citName' => 'Santiago Centro',
                'staCode' => 'ST01',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
