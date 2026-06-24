<?php

namespace Modules\Analytics\Actions;

use Illuminate\Support\Facades\DB;

class GetPortfolioVariationAction
{
    /**
     * Fetch SAP vs Smart FQ customer count variation grouped by territory/route.
     */
    public function execute(): array
    {
        $rawRecords = DB::table('master_client_polar')
            ->join('company_routes', 'master_client_polar.company_route_id', '=', 'company_routes.id')
            ->select(
                'company_routes.route_name as territory_code',
                'company_routes.name as territory_name',
                'company_routes.db_name',
                DB::raw("COUNT(CASE WHEN master_client_polar.cus_code IS NOT NULL AND master_client_polar.cus_code != '' THEN 1 END) as sap_count"),
                DB::raw("COUNT(CASE WHEN master_client_polar.cus_code IS NULL OR master_client_polar.cus_code = '' THEN 1 END) as smart_fq_count")
            )
            ->whereNotNull('company_routes.db_name')
            ->groupBy('company_routes.id')
            ->get();

        $data = [];
        $totalSap = 0;
        $totalSmartFq = 0;

        foreach ($rawRecords as $rec) {
            $sap = (int)$rec->sap_count;
            $smartFq = (int)$rec->smart_fq_count;
            $variation = abs($sap - $smartFq);

            $totalSap += $sap;
            $totalSmartFq += $smartFq;

            $data[] = [
                'territory' => $rec->territory_name ?? $rec->territory_code,
                'db_name' => $rec->db_name,
                'sap' => $sap,
                'smart_fq' => $smartFq,
                'variation' => $variation,
            ];
        }

        $totalPortfolio = $totalSap + $totalSmartFq;
        
        foreach ($data as &$item) {
            $itemTotal = $item['sap'] + $item['smart_fq'];
            $item['percentage'] = $totalPortfolio > 0 ? round(($itemTotal / $totalPortfolio) * 100, 2) : 0;
        }

        return [
            'success' => true,
            'data' => [
                'totals' => [
                    'sap' => $totalSap,
                    'smart_fq' => $totalSmartFq,
                    'variation' => abs($totalSap - $totalSmartFq),
                    'total' => $totalPortfolio,
                ],
                'territories' => $data
            ]
        ];
    }
}
