<?php
/**
 * SUPER DIAGNOSTICO DE REPORTES - HUB POLAR
 * Analiza paso a paso el embudo de datos para encontrar el error.
 */

use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');
echo "==========================================================\n";
echo "       SUPER DIAGNOSTICO DE INTEGRIDAD - HUB POLAR        \n";
echo "==========================================================\n\n";

$tenantService = app(TenantConnectionService::class);

// 1. HUB CHECK
echo "1. VERIFICACION DEL HUB:\n";
$activeClients = CompanyRoute::where('is_active', true)->get();
echo " - Rutas activas encontradas: " . $activeClients->count() . "\n\n";

if ($activeClients->isEmpty()) {
    echo "!!! ERROR CRITICO: No hay rutas activas en la tabla company_routes.\n";
    exit;
}

// 2. TENANT CHECK
echo "2. ANALISIS DETALLADO POR TENANT:\n";
foreach ($activeClients->take(3) as $client) {
    echo "----------------------------------------------------------\n";
    echo "CLIENTE: {$client->name} | DB: {$client->db_name}\n";
    
    try {
        $tenantService->connect($client);
        
        // PASO A: Base
        $totalVentas = DB::connection('tenant')->table('ventaspxc')->count();
        echo " [PASO A] Ventas brutas en ventaspxc: $totalVentas\n";
        
        if ($totalVentas == 0) {
            echo "          - No hay ventas registradas para este cliente.\n";
            continue;
        }

        // PASO B: Filtro Eliminado
        $ventasNoEliminadas = DB::connection('tenant')->table('ventaspxc')->where('eliminado', 0)->count();
        echo " [PASO B] Ventas con eliminado=0: $ventasNoEliminadas\n";
        if ($ventasNoEliminadas == 0) {
            echo "          !!! TODAS las ventas están marcadas como eliminadas.\n";
        }

        // PASO C: Filtro de Fecha (Abril 2026 como ejemplo)
        $ventasAbril = DB::connection('tenant')->table('ventaspxc')
            ->whereBetween('Fecha', ['2026-04-01', '2026-04-30'])
            ->count();
        echo " [PASO C] Ventas en el rango 2026-04-01 al 2026-04-30: $ventasAbril\n";
        
        if ($ventasAbril == 0) {
            $lastVenta = DB::connection('tenant')->table('ventaspxc')->max('Fecha');
            echo "          - No hay ventas en Abril. La última venta registrada es del: [$lastVenta]\n";
        }

        // PASO D: Integridad con Detalles y Productos
        $joinBase = DB::connection('tenant')
            ->table('ventaspxc as v')
            ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
            ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
            ->count();
        echo " [PASO D] Registros que pasan el JOIN (Venta+Detalle+Producto): $joinBase\n";
        
        $rawDetalles = DB::connection('tenant')->table('ventas_detalle')->count();
        if ($joinBase < $rawDetalles && $rawDetalles > 0) {
            echo "          !!! Hay " . ($rawDetalles - $joinBase) . " detalles que no encuentran su Producto. Posible falla de sincronización.\n";
        }

        // PASO E: Filtros de Texto (OBS)
        $conFiltrosTexto = DB::connection('tenant')
            ->table('ventaspxc as v')
            ->join('ventas_detalle as vd', 'v.IdVenta', '=', 'vd.IdVenta')
            ->join('productos as p', 'vd.idproducto', '=', 'p.idproducto')
            ->where('p.codigoSKU', 'NOT LIKE', '%OBS%')
            ->where('vd.producto', 'NOT LIKE', '%OBSEQUIO%')
            ->count();
        echo " [PASO E] Registros tras filtros de texto (NOT LIKE %OBS%): $conFiltrosTexto\n";

        // PASO F: Análisis de Obsequios
        echo " [PASO F] Analizando Obsequios:\n";
        $obsqTotal = DB::connection('tenant')->table('recepcion_plan_tactico')->count();
        echo "          - Registros en recepcion_plan_tactico: $obsqTotal\n";
        
        if ($obsqTotal > 0) {
            $obsqJoin = DB::connection('tenant')
                ->table('recepcion_plan_tactico as rpt')
                ->join('ventaspxc as v', 'rpt.IdVenta', '=', 'v.IdVenta')
                ->count();
            echo "          - Obsequios que encuentran su Venta Padre: $obsqJoin\n";
            
            if ($obsqJoin < $obsqTotal) {
                echo "          !!! ERROR: Hay " . ($obsqTotal - $obsqJoin) . " obsequios huérfanos (IdVenta no existe en ventaspxc).\n";
            }
        }

    } catch (\Exception $e) {
        echo " !!! ERROR DE CONEXION/SQL: " . $e->getMessage() . "\n";
    }
}

echo "\n==========================================================\n";
echo "               FIN DEL SUPER DIAGNOSTICO                  \n";
echo "==========================================================\n";
