<?php
/**
 * INVESTIGADOR DE OBSEQUIOS - HUB POLAR
 */

use Modules\Analytics\Services\TenantConnectionService;
use Modules\CompanyRoute\Models\CompanyRoute;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');
echo "==========================================================\n";
echo "        INVESTIGACION DE OBSEQUIOS - TENANT 09101         \n";
echo "==========================================================\n\n";

$tenantService = app(TenantConnectionService::class);
$client = CompanyRoute::where('db_name', 'www_v09101p')->first();

if (!$client) {
    echo "ERROR: No se encontró el tenant www_v09101p.\n";
    exit;
}

try {
    $tenantService->connect($client);
    
    $obsequios = DB::connection('tenant')->table('recepcion_plan_tactico')->get();
    echo "Total registros en recepcion_plan_tactico: " . $obsequios->count() . "\n\n";

    foreach ($obsequios as $index => $obsq) {
        echo "OBSEQUIO #" . ($index + 1) . ":\n";
        echo " - ID Venta: {$obsq->IdVenta}\n";
        echo " - Fecha: {$obsq->fecha}\n";
        echo " - Cantidad: {$obsq->obsequios}\n";

        // 1. Verificar Venta
        $venta = DB::connection('tenant')->table('ventaspxc')->where('IdVenta', $obsq->IdVenta)->first();
        echo "   * ¿Existe la Venta Padre?: " . ($venta ? "SI (Fecha Venta: {$venta->Fecha})" : "NO") . "\n";

        // 2. Verificar Detalles
        $detalles = DB::connection('tenant')->table('ventas_detalle')->where('IdVenta', $obsq->IdVenta)->get();
        echo "   * ¿Tiene detalles de productos?: " . ($detalles->count() > 0 ? "SI (" . $detalles->count() . " items)" : "NO") . "\n";

        foreach ($detalles as $det) {
            $prod = DB::connection('tenant')->table('productos')->where('idproducto', $det->idproducto)->first();
            $esObsequio = str_contains(strtoupper($prod->codigoSKU ?? ''), 'OBS') || str_contains(strtoupper($det->producto ?? ''), 'OBSEQUIO');
            echo "     - Item: " . ($prod->codigoSKU ?? 'S/K') . " | " . ($det->producto ?? 'S/N') . " | ¿Es Obsequio?: " . ($esObsequio ? "SI" : "NO") . "\n";
        }
        echo "----------------------------------------------------------\n";
    }

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
