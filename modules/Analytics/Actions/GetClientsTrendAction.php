<?php

namespace Modules\Analytics\Actions;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GetClientsTrendAction
{
    public function execute(): array
    {
        // 1. Obtener totales actuales
        $totalSapPolar = DB::table('master_client_polar')
            ->whereNotNull('cus_code')
            ->where('cus_code', '!=', '')
            ->count();

        $totalSmartFq = DB::table('master_client_polar')
            ->where(function($q) {
                $q->whereNull('cus_code')->orWhere('cus_code', '');
            })
            ->count();

        // Variación (Diferencia absoluta y porcentual)
        $variationNum = abs($totalSapPolar - $totalSmartFq);
        $totalCount = $totalSapPolar + $totalSmartFq;
        $variationPct = $totalCount > 0 ? ($variationNum / $totalCount) * 100 : 0;

        // 2. Generar el historial de los últimos 6 meses (cálculo acumulativo)
        // Idioma en español para los nombres de meses si Carbon está configurado
        Carbon::setLocale('es');

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        $firstMonth = $months[0];
        $baselineDate = Carbon::parse($firstMonth . '-01 00:00:00');

        $runningCoded = DB::table('master_client_polar')
            ->where('created_at', '<', $baselineDate)
            ->whereNotNull('cus_code')
            ->where('cus_code', '!=', '')
            ->count();

        $runningUncoded = DB::table('master_client_polar')
            ->where('created_at', '<', $baselineDate)
            ->where(function($q) {
                $q->whereNull('cus_code')->orWhere('cus_code', '');
            })
            ->count();

        // Agrupación de registros mensuales dentro del rango de 6 meses
        $monthlyData = DB::table('master_client_polar')
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw("COUNT(CASE WHEN cus_code IS NOT NULL AND cus_code != '' THEN 1 END) as coded_count"),
                DB::raw("COUNT(CASE WHEN cus_code IS NULL OR cus_code = '' THEN 1 END) as uncoded_count")
            )
            ->where('created_at', '>=', $baselineDate)
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $trend = [];
        foreach ($months as $m) {
            $monthData = $monthlyData->get($m);
            if ($monthData) {
                $runningCoded += $monthData->coded_count;
                $runningUncoded += $monthData->uncoded_count;
            }

            // Nombre del mes en español
            $monthName = Carbon::parse($m . '-01')->translatedFormat('M Y');
            $monthName = ucfirst($monthName);

            $trend[] = [
                'month' => $monthName,
                'raw_month' => $m,
                'smart_fq' => $runningUncoded, // Smart FQ = Sin Código
                'sap_polar' => $runningCoded,   // SAP Polar = Con Código
            ];
        }

        return [
            'success' => true,
            'data' => [
                'totals' => [
                    'smart_fq' => $totalSmartFq,
                    'sap_polar' => $totalSapPolar,
                    'variation_num' => $variationNum,
                    'variation_pct' => round($variationPct, 2),
                ],
                'trend' => $trend
            ]
        ];
    }
}
