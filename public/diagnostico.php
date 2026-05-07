<?php
/**
 * DIAGNOSTICO DE REPORTES VIA WEB - HUB POLAR
 */

use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tenantService = app(TenantConnectionService::class);

header('Content-Type: text/plain');

echo "==========================================\n";
echo "   DIAGNOSTICO DE REPORTES - HUB POLAR    \n";
echo "==========================================\n\n";

// 1. Verificar Rutas en el HUB
try {
    $totalRoutes = CompanyRoute::count();
    $activeRoutes = CompanyRoute::where('is_active', true)->count();

    echo "1. ESTADO DE RUTAS EN EL HUB:\n";
    echo " - Total de rutas registradas: $totalRoutes\n";
    echo " - Rutas marcadas como ACTIVAS (is_active=1): $activeRoutes\n";

    if ($activeRoutes == 0) {
        echo " !!! ALERTA: No hay rutas activas. El reporte SIEMPRE saldrá vacío.\n";
    }
} catch (\Exception $e) {
    echo "ERROR al consultar rutas: " . $e->getMessage() . "\n";
}
echo "\n";

// 2. Analizar Clientes/Tenants
echo "2. ANALISIS DE TENANTS (Primeros 5 activos):\n";
try {
    $clients = CompanyRoute::where('is_active', true)->take(5)->get();

    if ($clients->isEmpty()) {
        echo " - No hay clientes activos para analizar.\n";
    }

    foreach ($clients as $client) {
        echo "------------------------------------------\n";
        echo "Tenant: {$client->db_name} (Ruta: {$client->code})\n";
        
        try {
            $tenantService->connect($client);
            
            $ventasCount = DB::connection('tenant')->table('ventaspxc')->count();
        $productosCount = DB::connection('tenant')->table('productos')->count();
        
        echo " - Ventas en la tabla ventaspxc: $ventasCount\n";
        
        if ($ventasCount > 0) {
            $minDate = DB::connection('tenant')->table('ventaspxc')->min('Fecha');
            $maxDate = DB::connection('tenant')->table('ventaspxc')->max('Fecha');
            echo "   * RANGO DE FECHAS EN DB: Desde [$minDate] hasta [$maxDate]\n";
        }
        
        echo " - Productos en catálogo: $productosCount\n";
            
            // Simular el JOIN del reporte de Ventas CON FILTROS
            $joinVentas = DB::connection('tenant')
                ->table('ventaspxc as v')
                ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
                ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                ->where('p.codigoSKU', 'NOT LIKE', '%OBS%')
                ->where('vd.producto', 'NOT LIKE', '%OBSEQUIO%')
                ->count();
            
            echo " - Registros que pasan el filtro de Ventas (CON FILTROS TEXTO): $joinVentas\n";

            // Verificar Plan Táctico (Obsequios)
            $hasRpt = \Illuminate\Support\Facades\Schema::connection('tenant')->hasTable('recepcion_plan_tactico');
            echo " - Tabla recepcion_plan_tactico: " . ($hasRpt ? "EXISTE" : "NO EXISTE") . "\n";
            
            if ($hasRpt) {
                $rptCount = DB::connection('tenant')->table('recepcion_plan_tactico')->count();
                echo " - Registros en Plan Táctico: $rptCount\n";
                
                $joinObsq = DB::connection('tenant')
                    ->table('recepcion_plan_tactico as rpt')
                    ->join('ventaspxc as v', 'rpt.IdVenta', '=', 'v.IdVenta')
                    ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
                    ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
                    ->count();
                echo " - Obsequios que pasan el JOIN completo: $joinObsq\n";

                if ($joinObsq == 0 && $rptCount > 0) {
                    echo " !!! ANALISIS DE DESFASE EN OBSEQUIOS:\n";
                    $sampleRpts = DB::connection('tenant')->table('recepcion_plan_tactico')->take(3)->get();
                    foreach ($sampleRpts as $sr) {
                        $existsInVentas = DB::connection('tenant')->table('ventaspxc')->where('IdVenta', $sr->IdVenta)->exists();
                        $existsInDetalle = DB::connection('tenant')->table('ventas_detalle')->where('IdVenta', $sr->IdVenta)->exists();
                        echo "   * ID Venta [{$sr->IdVenta}]: En Ventas: " . ($existsInVentas ? "SI" : "NO") . " | En Detalle: " . ($existsInDetalle ? "SI" : "NO") . "\n";
                    }
                }
            }

        } catch (\Exception $e) {
            echo " - ERROR de conexión: " . $e->getMessage() . "\n";
        }
    }
} catch (\Exception $e) {
    echo "ERROR general de diagnóstico: " . $e->getMessage() . "\n";
}

echo "\n==========================================\n";
echo "FIN DEL DIAGNOSTICO\n";
