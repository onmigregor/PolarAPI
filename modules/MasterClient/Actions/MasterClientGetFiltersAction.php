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

        $tp1Names = \Modules\MasterClient\Models\MasterClientType1::pluck('tp1_name', 'tp1_code')->toArray();

        $tp1Results = array_map(fn($val) => [
            'code' => $val,
            'name' => $tp1Names[$val] ?? $val
        ], $tp1Options);

        $tp2Results = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], $tp2Options);

        $citResults = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], $citOptions);

        // 4 nuevos filtros adicionales (desde la tabla central de company_routes)
        $fqOptions = CompanyRoute::whereNotNull('cep')
            ->where('cep', '!=', '')
            ->distinct()
            ->pluck('cep')
            ->toArray();
        $fqResults = array_map(function($val) {
            $padded = str_pad((string)$val, 10, '0', STR_PAD_LEFT);
            return [
                'code' => $padded,
                'name' => $padded
            ];
        }, array_unique($fqOptions));
        usort($fqResults, fn($a, $b) => strcmp($a['code'], $b['code']));

        $vgOptions = CompanyRoute::whereNotNull('address_street2')
            ->where('address_street2', '!=', '')
            ->distinct()
            ->pluck('address_street2')
            ->toArray();
        $vgResults = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], array_unique($vgOptions));
        usort($vgResults, fn($a, $b) => strcmp($a['code'], $b['code']));

        $officeOptions = CompanyRoute::whereNotNull('address_street1')
            ->where('address_street1', '!=', '')
            ->distinct()
            ->pluck('address_street1')
            ->toArray();
        $officeResults = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], array_unique($officeOptions));
        usort($officeResults, fn($a, $b) => strcmp($a['code'], $b['code']));

        $territoryOptions = CompanyRoute::whereNotNull('subregion_code')
            ->where('subregion_code', '!=', '')
            ->distinct()
            ->pluck('subregion_code')
            ->toArray();
        $territoryResults = array_map(fn($val) => [
            'code' => $val,
            'name' => $val
        ], array_unique($territoryOptions));
        usort($territoryResults, fn($a, $b) => strcmp($a['code'], $b['code']));

        return [
            'tp1_codes' => $tp1Results,
            'tp2_codes' => $tp2Results,
            'cit_codes' => $citResults,
            'fq_codes' => $fqResults,
            'vendor_groups' => $vgResults,
            'offices' => $officeResults,
            'territories' => $territoryResults,
        ];
    }
}
