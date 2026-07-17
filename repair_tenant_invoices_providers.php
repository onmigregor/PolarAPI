<?php
/**
 * Script de Reparación Histórica de Proveedores en Tenants (Producción/Local)
 * 
 * Este script asocia las facturas registradas como idproveedor = 0 (POLAR)
 * con el proveedor correcto basado en el codigo_polar_negocio de master_invoices.
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$mapping = [
    'CYM'  => ['name' => 'CERVECERIA POLAR C.A.', 'id' => 11],
    'C001' => ['name' => 'CERVECERIA POLAR C.A.', 'id' => 11],
    'PCV'  => ['name' => 'PEPSI-COLA VENEZUELA, C.A.', 'id' => 12],
    'R200' => ['name' => 'PEPSI-COLA VENEZUELA, C.A.', 'id' => 12],
    'APC'  => ['name' => 'ALIMENTOS POLAR COMERCIAL, C.A.', 'id' => 13],
    '0702' => ['name' => 'ALIMENTOS POLAR COMERCIAL, C.A.', 'id' => 13],
    '702'  => ['name' => 'ALIMENTOS POLAR COMERCIAL, C.A.', 'id' => 13],
];

echo "=== INICIANDO SCRIPT DE REPARACIÓN HISTÓRICA DE PROVEEDORES ===\n\n";

// 1. Obtener todos los tenants activos
$tenants = DB::table('company_routes')
    ->where('is_active', true)
    ->whereNotNull('db_name')
    ->get();

echo "Se encontraron " . $tenants->count() . " tenants activos para escanear.\n\n";

foreach ($tenants as $tenant) {
    $dbName = $tenant->db_name;
    echo "Auditando tenant: {$tenant->name} (BD: {$dbName})...\n";

    try {
        // Configurar conexión dinámica
        Config::set('database.connections.tenant.database', $dbName);
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Verificar si la tabla compras existe en este tenant
        $hasTable = DB::connection('tenant')->select("SHOW TABLES LIKE 'compras'");
        if (empty($hasTable)) {
            echo "  [Aviso] La tabla 'compras' no existe en el tenant {$dbName}. Saltando.\n\n";
            continue;
        }

        // Obtener facturas con idproveedor = 0 o nombre = POLAR
        $affectedInvoices = DB::connection('tenant')->table('compras')
            ->where('idproveedor', 0)
            ->orWhere('proveedor', 'POLAR')
            ->get();

        if ($affectedInvoices->isEmpty()) {
            echo "  ✅ Sin facturas afectadas en este tenant.\n\n";
            continue;
        }

        echo "  [Encontradas] " . $affectedInvoices->count() . " facturas para reparar.\n";

        $repairedCount = 0;
        foreach ($affectedInvoices as $invoice) {
            $nroFactura = $invoice->nrofactura;

            // Buscar en master_invoices el codigo_polar_negocio correspondiente
            $masterInvoice = DB::table('master_invoices')
                ->where('no_factura', $nroFactura)
                ->first();

            if (!$masterInvoice) {
                echo "    ⚠️ Factura {$nroFactura}: No se encontró en la tabla central master_invoices. No se puede corregir.\n";
                continue;
            }

            $code = strtoupper(trim($masterInvoice->codigo_polar_negocio));
            
            if (!isset($mapping[$code])) {
                echo "    ⚠️ Factura {$nroFactura}: Código de negocio central '{$code}' no está mapeado en la lista de proveedores. Saltando.\n";
                continue;
            }

            $correctProvider = $mapping[$code];

            // REALIZAR LA ACTUALIZACIÓN EN EL TENANT (Solo se ejecutará si se corre el script)
            DB::connection('tenant')->table('compras')
                ->where('idcompra', $invoice->idcompra)
                ->update([
                    'idproveedor' => $correctProvider['id'],
                    'proveedor'   => $correctProvider['name']
                ]);

            echo "    ✅ Factura {$nroFactura} corregida: Mapeado código '{$code}' -> {$correctProvider['name']} (ID: {$correctProvider['id']})\n";
            $repairedCount++;
        }

        echo "  Total reparadas en {$dbName}: {$repairedCount} facturas.\n\n";

    } catch (\Exception $e) {
        echo "  ❌ Error procesando tenant {$dbName}: " . $e->getMessage() . "\n\n";
    }
}

echo "=== SCRIPT DE REPARACIÓN FINALIZADO ===\n";
