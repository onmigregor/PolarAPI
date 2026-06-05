<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Illuminate\Support\Facades\DB;

class MasterClientGetFiltersAction
{
    public function execute(array $filters = []): array
    {
        $tp1 = $filters['tp1_code'] ?? null;
        $tp2 = $filters['tp2_code'] ?? null;

        // Query TP1 codes
        $tp1Query = DB::table('master_client_polar')
            ->select('tp1_code')
            ->distinct()
            ->whereNotNull('tp1_code')
            ->where('tp1_code', '!=', '');
        
        $tp1Results = $tp1Query->pluck('tp1_code')->map(fn($code) => [
            'code' => $code,
            'name' => $code
        ])->toArray();

        // Query TP2 codes (Branches) joined with master_clients_type2 for names
        $tp2Query = DB::table('master_client_polar as mc')
            ->leftJoin('master_clients_type2 as t2', 'mc.tp2_code', '=', 't2.tp2_code')
            ->select('mc.tp2_code as code', DB::raw('COALESCE(t2.tp2_name, mc.tp2_code) as name'))
            ->distinct()
            ->whereNotNull('mc.tp2_code')
            ->where('mc.tp2_code', '!=', '');

        if ($tp1) {
            $tp2Query->where('mc.tp1_code', $tp1);
        }
        
        $tp2Results = $tp2Query->get()->map(fn($row) => [
            'code' => $row->code,
            'name' => $row->name ?: $row->code
        ])->toArray();

        // Query CIT codes (Cities/Regions) joined with regions for names
        $citQuery = DB::table('master_client_polar as mc')
            ->leftJoin('regions as r', 'mc.cit_code', '=', 'r.citCode')
            ->select('mc.cit_code as code', DB::raw('COALESCE(r.citName, mc.cit_code) as name'))
            ->distinct()
            ->whereNotNull('mc.cit_code')
            ->where('mc.cit_code', '!=', '');

        if ($tp1) {
            $citQuery->where('mc.tp1_code', $tp1);
        }
        if ($tp2) {
            $citQuery->where('mc.tp2_code', $tp2);
        }

        $citResults = $citQuery->get()->map(fn($row) => [
            'code' => $row->code,
            'name' => $row->name ?: $row->code
        ])->toArray();

        return [
            'tp1_codes' => $tp1Results,
            'tp2_codes' => $tp2Results,
            'cit_codes' => $citResults,
        ];
    }
}
