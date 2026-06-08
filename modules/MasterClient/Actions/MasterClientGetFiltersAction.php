<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class MasterClientGetFiltersAction
{
    public function execute(array $filters = []): array
    {
        $activeTenants = CompanyRoute::where('is_active', true)->whereNotNull('db_name')->get();

        $tp1Options = [];
        $tp2Options = [];
        $citOptions = [];

        foreach ($activeTenants as $tenant) {
            try {
                Config::set('database.connections.tenant.database', $tenant->db_name);
                DB::purge('tenant');

                $tenT1 = DB::connection('tenant')->table('clientes')
                    ->distinct()
                    ->whereNotNull('tp1_code')
                    ->where('tp1_code', '!=', '')
                    ->pluck('tp1_code')
                    ->toArray();
                $tp1Options = array_merge($tp1Options, $tenT1);

                $tenT2 = DB::connection('tenant')->table('clientes')
                    ->distinct()
                    ->whereNotNull('TipoCliente')
                    ->where('TipoCliente', '!=', '')
                    ->pluck('TipoCliente')
                    ->toArray();
                $tp2Options = array_merge($tp2Options, $tenT2);

                $tenT3 = DB::connection('tenant')->table('clientes')
                    ->distinct()
                    ->whereNotNull('segmento')
                    ->where('segmento', '!=', '')
                    ->pluck('segmento')
                    ->toArray();
                $citOptions = array_merge($citOptions, $tenT3);
            } catch (\Exception $e) {
                // Ignore tenant errors
            }
        }

        $tp1Options = array_unique($tp1Options);
        sort($tp1Options);

        $tp2Options = array_unique($tp2Options);
        sort($tp2Options);

        $citOptions = array_unique($citOptions);
        sort($citOptions);

        $tp1Results = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], $tp1Options);

        $tp2Results = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], $tp2Options);

        $citResults = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], $citOptions);

        return [
            'tp1_codes' => $tp1Results,
            'tp2_codes' => $tp2Results,
            'cit_codes' => $citResults,
        ];
    }
}
