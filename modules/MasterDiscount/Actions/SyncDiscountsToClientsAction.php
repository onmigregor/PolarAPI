<?php

namespace Modules\MasterDiscount\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Modules\CompanyRoute\Models\CompanyRoute;

/**
 * SyncDiscountsToClientsAction
 *
 * Distribuye los descuentos del HUB maestro hacia las bases de datos de cada Tenant.
 * Crea las tablas necesarias si no existen y realiza la carga limpia de los datos segmentados por ruta.
 */
class SyncDiscountsToClientsAction
{
    private const CREATE_DESCUENTOS_TABLE = "
        CREATE TABLE IF NOT EXISTS `descuentos_polar` (
            `did_code` varchar(200) NOT NULL COMMENT 'Código único del detalle del descuento',
            `dis_code` varchar(200) NOT NULL COMMENT 'Código del descuento padre',
            `dis_name` varchar(255) DEFAULT NULL COMMENT 'Nombre del descuento',
            `did_name` varchar(255) DEFAULT NULL COMMENT 'Nombre del detalle',
            `rot_code_customer` varchar(50) DEFAULT NULL COMMENT 'Ruta específica (NULL = todas)',
            `cus_code` varchar(50) DEFAULT NULL COMMENT 'Cliente específico (NULL = todos)',
            `did_since` date DEFAULT NULL COMMENT 'Fecha inicio vigencia',
            `did_until` date DEFAULT NULL COMMENT 'Fecha fin vigencia',
            `synced_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Última sincronización',
            PRIMARY KEY (`did_code`),
            KEY `idx_dis_code` (`dis_code`),
            KEY `idx_rot_code_customer` (`rot_code_customer`),
            KEY `idx_cus_code` (`cus_code`),
            KEY `idx_vigencia` (`did_since`, `did_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    private const CREATE_PRODUCTOS_TABLE = "
        CREATE TABLE IF NOT EXISTS `descuentos_polar_productos` (
            `dlp_code` varchar(200) NOT NULL COMMENT 'Código único del producto en descuento',
            `did_code` varchar(200) NOT NULL COMMENT 'FK al detalle de descuento',
            `dis_code` varchar(200) NOT NULL COMMENT 'FK al descuento padre',
            `pro_code` varchar(50) DEFAULT NULL COMMENT 'SKU del producto',
            `cl1code` varchar(50) DEFAULT NULL,
            `cl2code` varchar(50) DEFAULT NULL,
            `cl3code` varchar(50) DEFAULT NULL,
            `cl4code` varchar(50) DEFAULT NULL,
            `pro_code_ingredient` varchar(50) DEFAULT NULL,
            `quo_code` varchar(50) DEFAULT NULL,
            `con_code` varchar(50) DEFAULT NULL,
            `unt_code` varchar(10) DEFAULT NULL COMMENT 'Unidad de medida',
            `dlp_required` varchar(10) DEFAULT NULL,
            `dlp_discount` decimal(15,4) DEFAULT NULL,
            `dlp_discount_percentage` decimal(15,4) DEFAULT NULL,
            `dlp_discount_amount` decimal(15,4) DEFAULT NULL,
            `dlp_required_quantity` decimal(15,4) DEFAULT NULL,
            `dlp_required_quantity_amount` decimal(15,4) DEFAULT NULL,
            `dlp_base_from_taken_for_discou` varchar(255) DEFAULT NULL,
            `dlp_pallet_discount` varchar(255) DEFAULT NULL,
            `dlp_minimum` decimal(15,4) DEFAULT NULL,
            `dlp_quantity1` decimal(15,4) DEFAULT NULL,
            `dlp_min_discount1` decimal(15,4) DEFAULT NULL,
            `dlp_quantity2` decimal(15,4) DEFAULT NULL,
            `dlp_min_discount2` decimal(15,4) DEFAULT NULL,
            `dlp_quantity3` decimal(15,4) DEFAULT NULL,
            `dlp_min_discount3` decimal(15,4) DEFAULT NULL,
            `dlp_quantity4` decimal(15,4) DEFAULT NULL,
            `dlp_min_discount4` decimal(15,4) DEFAULT NULL,
            `dlp_quantity5` decimal(15,4) DEFAULT NULL,
            `dlp_min_discount5` decimal(15,4) DEFAULT NULL,
            `dlp_max_discount1` decimal(15,4) DEFAULT NULL,
            `dlp_max_discount2` decimal(15,4) DEFAULT NULL,
            `dlp_max_discount3` decimal(15,4) DEFAULT NULL,
            `dlp_max_discount4` decimal(15,4) DEFAULT NULL,
            `dlp_max_discount5` decimal(15,4) DEFAULT NULL,
            `dlp_max_discount6` decimal(15,4) DEFAULT NULL,
            `dlp_global_discount_amount` decimal(15,4) DEFAULT NULL,
            `synced_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Última sincronización',
            PRIMARY KEY (`dlp_code`),
            KEY `idx_did_code` (`did_code`),
            KEY `idx_dis_code` (`dis_code`),
            KEY `idx_pro_code` (`pro_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    public function execute(): array
    {
        Log::channel('single')->info("=== INICIO: Distribución de Descuentos HUB → Tenants ===");

        $results = [
            'tenants_processed'    => 0,
            'tenants_skipped'      => 0,
            'discounts_synced'     => 0,
            'products_synced'      => 0,
            'discounts_deleted'    => 0,
            'errors'               => [],
        ];

        // 1. Obtener Tenants activos
        $prefix = config('tenants.prefix', 'www_');
        $suffix = config('tenants.suffix', 'p');
        $tenants = CompanyRoute::where('is_active', true)
            ->where('db_name', 'LIKE', "{$prefix}v%{$suffix}")
            ->get();

        if ($tenants->isEmpty()) {
            Log::warning("SyncDiscountsToClients: No se encontraron Tenants activos con formato {$prefix}v*{$suffix}");
            return $results;
        }

        Log::info("SyncDiscountsToClients: Encontrados {$tenants->count()} Tenants activos");

        // Cargar mapa de clientes a códigos de ruta desde la tabla maestra de clientes
        $clients = DB::table('master_client_polar as clients')
            ->join('company_routes as routes', 'routes.id', '=', 'clients.company_route_id')
            ->select('clients.cus_code as cep', 'routes.code as route_code', 'routes.db_name')
            ->get();
            
        $clientToRouteMap = [];
        foreach ($clients as $c) {
            $cleanedCep = ltrim($c->cep, '0');
            $cRotCode = $this->extractRotCode($c->route_code, $c->db_name);
            if ($cRotCode) {
                $clientToRouteMap[$cleanedCep] = strtolower($cRotCode);
            }
        }

        // 2. Cargar datos maestros en memoria
        $masterDiscounts = DB::table('master_discounts')->get()->keyBy('dis_code');
        $masterDetails = DB::table('master_discount_details')->get();
        $masterProducts = DB::table('master_discount_detail_products')->get();
        $masterRoutes = DB::table('master_discount_detail_routes')->get();

        Log::info("SyncDiscountsToClients: HUB tiene {$masterDiscounts->count()} descuentos, {$masterDetails->count()} detalles, {$masterProducts->count()} productos");

        $productsByDetail = $masterProducts->groupBy('did_code');

        // 3. Procesar cada Tenant
        foreach ($tenants as $tenant) {
            try {
                $rotCode = $this->extractRotCode($tenant->code, $tenant->db_name);

                if (!$rotCode) {
                    Log::warning("SyncDiscountsToClients: No se pudo extraer rot_code de '{$tenant->code}', saltando");
                    $results['tenants_skipped']++;
                    continue;
                }

                $rotCodeLower = strtolower($rotCode);
                $isC3Branch = (str_contains(strtolower($tenant->code), 'c3'));

                Log::info("SyncDiscountsToClients: Procesando Tenant '{$tenant->name}' (DB: {$tenant->db_name}, rot_code: {$rotCode})");

                // Conectar al Tenant
                Config::set('database.connections.tenant.database', $tenant->db_name);
                DB::purge('tenant');

                DB::connection('tenant')->statement("SET SESSION sql_mode = ''");

                // Asegurar tablas
                $this->ensureTables();

                // Filtrar descuentos aplicables para esta ruta específica
                $allDetailsForTenant = $masterDetails->filter(function($detail) use ($rotCodeLower, $isC3Branch, $clientToRouteMap, $masterRoutes) {
                    // Caso A: Por cliente específico (cus_code)
                    if (!empty($detail->cus_code)) {
                        $cleanedCusCode = ltrim($detail->cus_code, '0');
                        if (isset($clientToRouteMap[$cleanedCusCode])) {
                            return $clientToRouteMap[$cleanedCusCode] === $rotCodeLower;
                        }
                    }

                    // Caso B: Por ruta específica (rot_code_customer)
                    if (!empty($detail->rot_code_customer)) {
                        $rotCust = strtolower(trim($detail->rot_code_customer));
                        if ($rotCust === 'c3') {
                            return $isC3Branch;
                        }
                        if ($rotCust === $rotCodeLower) {
                            return true;
                        }
                    }

                    // Caso C: Por rutas asociadas al descuento principal
                    $disRoutes = $masterRoutes->where('dis_code', $detail->dis_code);
                    foreach ($disRoutes as $r) {
                        $rCode = strtolower(trim($r->rot_code));
                        if ($rCode === 'c3' && $isC3Branch) {
                            return true;
                        }
                        if ($rCode === $rotCodeLower) {
                            return true;
                        }
                    }

                    return false;
                });

                if ($allDetailsForTenant->isEmpty()) {
                    Log::info("SyncDiscountsToClients: Sin descuentos para rot_code '{$rotCode}'");
                    $results['tenants_skipped']++;
                    continue;
                }

                // Limpieza segura
                $deletedDiscounts = DB::connection('tenant')->table('descuentos_polar')->count();
                $deletedProducts = DB::connection('tenant')->table('descuentos_polar_productos')->count();

                DB::connection('tenant')->table('descuentos_polar_productos')->delete();
                DB::connection('tenant')->table('descuentos_polar')->delete();

                $results['discounts_deleted'] += $deletedDiscounts;

                Log::info("SyncDiscountsToClients: Limpiados {$deletedDiscounts} descuentos y {$deletedProducts} productos en {$tenant->db_name}");

                $discountData = [];
                $productData = [];
                $now = now();

                foreach ($allDetailsForTenant as $detail) {
                    $discount = $masterDiscounts->get($detail->dis_code);
                    if (!$discount) continue;

                    $discountData[] = [
                        'did_code'          => $detail->did_code,
                        'dis_code'          => $detail->dis_code,
                        'dis_name'          => $discount->dis_name,
                        'did_name'          => $detail->did_name,
                        'rot_code_customer' => $detail->rot_code_customer,
                        'cus_code'          => $detail->cus_code ? ltrim($detail->cus_code, '0') : null,
                        'did_since'         => $detail->did_since,
                        'did_until'         => $detail->did_until,
                        'synced_at'         => $now,
                    ];

                    $detailProducts = $productsByDetail->get($detail->did_code, collect());
                    foreach ($detailProducts as $product) {
                        $productData[] = [
                            'dlp_code'                     => $product->dlp_code,
                            'did_code'                     => $product->did_code,
                            'dis_code'                     => $product->dis_code,
                            'pro_code'                     => $product->pro_code,
                            'cl1code'                      => $product->cl1code ?? null,
                            'cl2code'                      => $product->cl2code ?? null,
                            'cl3code'                      => $product->cl3code ?? null,
                            'cl4code'                      => $product->cl4code ?? null,
                            'pro_code_ingredient'          => $product->pro_code_ingredient ?? null,
                            'quo_code'                     => $product->quo_code ?? null,
                            'con_code'                     => $product->con_code ?? null,
                            'unt_code'                     => $product->unt_code,
                            'dlp_required'                 => $product->dlp_required,
                            'dlp_discount'                 => $product->dlp_discount,
                            'dlp_discount_percentage'      => $product->dlp_discount_percentage,
                            'dlp_discount_amount'          => $product->dlp_discount_amount,
                            'dlp_required_quantity'        => $product->dlp_required_quantity,
                            'dlp_required_quantity_amount' => $product->dlp_required_quantity_amount,
                            'dlp_base_from_taken_for_discou'=> $product->dlp_base_from_taken_for_discou,
                            'dlp_pallet_discount'          => $product->dlp_pallet_discount,
                            'dlp_minimum'                  => $product->dlp_minimum,
                            'dlp_quantity1'                => $product->dlp_quantity1,
                            'dlp_min_discount1'            => $product->dlp_min_discount1,
                            'dlp_quantity2'                => $product->dlp_quantity2,
                            'dlp_min_discount2'            => $product->dlp_min_discount2,
                            'dlp_quantity3'                => $product->dlp_quantity3,
                            'dlp_min_discount3'            => $product->dlp_min_discount3,
                            'dlp_quantity4'                => $product->dlp_quantity4,
                            'dlp_min_discount4'            => $product->dlp_min_discount4,
                            'dlp_quantity5'                => $product->dlp_quantity5,
                            'dlp_min_discount5'            => $product->dlp_min_discount5,
                            'dlp_max_discount1'            => $product->dlp_max_discount1,
                            'dlp_max_discount2'            => $product->dlp_max_discount2,
                            'dlp_max_discount3'            => $product->dlp_max_discount3,
                            'dlp_max_discount4'            => $product->dlp_max_discount4,
                            'dlp_max_discount5'            => $product->dlp_max_discount5,
                            'dlp_max_discount6'            => $product->dlp_max_discount6,
                            'dlp_global_discount_amount'   => $product->dlp_global_discount_amount,
                            'synced_at'                    => $now,
                        ];
                    }
                }

                // Batch Inserts
                if (!empty($discountData)) {
                    foreach (array_chunk($discountData, 200) as $chunk) {
                        DB::connection('tenant')->table('descuentos_polar')->insert($chunk);
                    }
                    $results['discounts_synced'] += count($discountData);
                }

                if (!empty($productData)) {
                    foreach (array_chunk($productData, 200) as $chunk) {
                        DB::connection('tenant')->table('descuentos_polar_productos')->insert($chunk);
                    }
                    $results['products_synced'] += count($productData);
                }

                Log::info("SyncDiscountsToClients: Tenant '{$tenant->name}' sincronizado con éxito. Descuentos: " . count($discountData) . ", Productos: " . count($productData));
                $results['tenants_processed']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'tenant' => $tenant->name,
                    'db'     => $tenant->db_name,
                    'error'  => $e->getMessage(),
                ];
                Log::error("SyncDiscountsToClients: Error en Tenant '{$tenant->name}': " . $e->getMessage());
            }
        }

        Log::channel('single')->info("=== FIN: Distribución de Descuentos ===", $results);
        return $results;
    }

    private function extractRotCode(string $code, ?string $dbName = null): ?string
    {
        if (preg_match('/_V([a-z0-9]+)$/i', $code, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^v([a-z0-9]+)$/i', $code, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^[a-z0-9]+$/i', $code)) {
            return $code;
        }

        if ($dbName) {
            $prefix = preg_quote(config('tenants.prefix', 'www_'), '/');
            $suffix = preg_quote(config('tenants.suffix', 'p'), '/');
            if (preg_match('/' . $prefix . 'v([a-z0-9]+)' . $suffix . '$/i', $dbName, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function ensureTables(): void
    {
        $conn = DB::connection('tenant');

        $discountExists = $conn->select("SHOW TABLES LIKE 'descuentos_polar'");
        if (empty($discountExists)) {
            $conn->statement(self::CREATE_DESCUENTOS_TABLE);
        }

        $productsExists = $conn->select("SHOW TABLES LIKE 'descuentos_polar_productos'");
        if (empty($productsExists)) {
            $conn->statement(self::CREATE_PRODUCTOS_TABLE);
        } else {
            $columns = collect($conn->select("SHOW COLUMNS FROM `descuentos_polar_productos`"))->pluck('Field')->toArray();
            $required = ['cl1code', 'cl2code', 'cl3code', 'cl4code', 'pro_code_ingredient', 'quo_code', 'con_code'];
            foreach ($required as $col) {
                if (!in_array($col, $columns)) {
                    $conn->statement("ALTER TABLE `descuentos_polar_productos` ADD COLUMN `$col` varchar(50) DEFAULT NULL");
                }
            }
        }
    }
}
