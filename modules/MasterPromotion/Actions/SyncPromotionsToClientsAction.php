<?php

namespace Modules\MasterPromotion\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Modules\CompanyRoute\Models\CompanyRoute;

/**
 * SyncPromotionsToClientsAction
 *
 * Distribuye las promociones del HUB maestro hacia las bases de datos de cada Tenant.
 * Crea las tablas necesarias si no existen y realiza upsert de los datos.
 *
 * Tablas creadas en cada Tenant:
 *   - promociones_polar          → Cabecera + detalle unificado de la promoción
 *   - promociones_polar_productos → Productos participantes (requeridos y/o gratis)
 *
 * Flujo:
 *   1. Obtener todos los company_routes activos con db_name que contenga 'v' y 'p' (Tenants reales)
 *   2. Para cada Tenant, crear tablas si no existen
 *   3. Filtrar promociones del HUB cuyo rot_code coincida con la ruta del Tenant
 *   4. Upsert de cabecera + productos
 *   5. Auditar resultados
 */
class SyncPromotionsToClientsAction
{
    /**
     * SQL para crear la tabla de cabecera/detalle unificada de promociones en el Tenant.
     * Unifica master_promotions + master_promotion_details en una sola tabla legible.
     */
    private const CREATE_PROMOCIONES_TABLE = "
        CREATE TABLE IF NOT EXISTS `promociones_polar` (
            `pdl_code` varchar(200) NOT NULL COMMENT 'Código único del detalle de promoción',
            `prm_code` varchar(200) NOT NULL COMMENT 'Código de la promoción padre',
            `prm_name` varchar(255) DEFAULT NULL COMMENT 'Nombre de la promoción',
            `pdl_name` varchar(255) DEFAULT NULL COMMENT 'Nombre del detalle/plan',
            `pdl_since` date DEFAULT NULL COMMENT 'Fecha inicio vigencia',
            `pdl_until` datetime DEFAULT NULL COMMENT 'Fecha fin vigencia',
            `cus_code` varchar(50) DEFAULT NULL COMMENT 'Cliente específico (NULL = todos)',
            `rot_code` varchar(50) DEFAULT NULL COMMENT 'Ruta/territorio asignado',
            `tp3code` varchar(50) DEFAULT NULL COMMENT 'Clase de producto (filtro por jerarquía)',
            `tp1_code` varchar(50) DEFAULT NULL COMMENT 'Clase de producto jerarquía 1',
            `tp2_code` varchar(50) DEFAULT NULL COMMENT 'Clase de producto jerarquía 2',
            `tp3_code` varchar(50) DEFAULT NULL COMMENT 'Clase de producto jerarquía 3',
            `pdl_minimum` decimal(15,3) DEFAULT NULL COMMENT 'Mínimo requerido para activar',
            `unt_code_required` varchar(50) DEFAULT NULL COMMENT 'Unidad requerida',
            `unt_code_free` varchar(50) DEFAULT NULL COMMENT 'Unidad del regalo/gratis',
            `pdl_order` int(11) DEFAULT NULL COMMENT 'Orden del detalle',
            `pdl_scalable` tinyint(1) DEFAULT 0 COMMENT 'Es escalable',
            `pdl_accumulable` tinyint(1) DEFAULT 0 COMMENT 'Es acumulable',
            `prm_can_be_disabled` varchar(10) DEFAULT NULL COMMENT 'Si el vendedor puede desactivarla',
            `prm_enabled_value_on` varchar(10) DEFAULT NULL COMMENT 'Valor por defecto de activación',
            `prm_valid_to_sale` varchar(10) DEFAULT NULL COMMENT 'Válida para venta directa',
            `prm_extended_file` text DEFAULT NULL COMMENT 'JSON con campos extendidos de cabecera',
            `pdl_extended_file` text DEFAULT NULL COMMENT 'JSON con campos extendidos de detalle',
            `synced_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Última sincronización',
            PRIMARY KEY (`pdl_code`),
            KEY `idx_prm_code` (`prm_code`),
            KEY `idx_rot_code` (`rot_code`),
            KEY `idx_cus_code` (`cus_code`),
            KEY `idx_vigencia` (`pdl_since`, `pdl_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    /**
     * SQL para crear la tabla de productos asociados a promociones en el Tenant.
     */
    private const CREATE_PRODUCTOS_TABLE = "
        CREATE TABLE IF NOT EXISTS `promociones_polar_productos` (
            `prp_code` varchar(200) NOT NULL COMMENT 'Código único del producto en promoción',
            `pdl_code` varchar(200) NOT NULL COMMENT 'FK al detalle de promoción',
            `prm_code` varchar(200) NOT NULL COMMENT 'FK a la promoción padre',
            `pro_code` varchar(50) DEFAULT NULL COMMENT 'SKU del producto (NULL si aplica a categoría)',
            `unt_code` varchar(10) DEFAULT NULL COMMENT 'Unidad de medida',
            `cl1code` varchar(50) DEFAULT NULL COMMENT 'Clase 1 (Familia)',
            `cl2code` varchar(50) DEFAULT NULL COMMENT 'Clase 2 (Categoría)',
            `cl3code` varchar(50) DEFAULT NULL COMMENT 'Clase 3 (Grupo)',
            `cl4code` varchar(50) DEFAULT NULL COMMENT 'Clase 4 (Segmento)',
            `prp_required` varchar(10) DEFAULT NULL COMMENT 'Es producto requerido para activar promo',
            `prp_free` varchar(10) DEFAULT NULL COMMENT 'Es producto regalo/gratis',
            `prp_valid_for_base_percentage` tinyint(1) DEFAULT 0,
            `prp_quantity` tinyint(1) DEFAULT 0,
            `prp_quantity1` decimal(15,3) DEFAULT NULL,
            `prp_quantity2` decimal(15,3) DEFAULT NULL,
            `prp_quantity3` decimal(15,3) DEFAULT NULL,
            `prp_quantity4` decimal(15,3) DEFAULT NULL,
            `prp_quantity5` decimal(15,3) DEFAULT NULL,
            `prp_min_percentage1` decimal(15,3) DEFAULT NULL,
            `prp_min_percentage2` decimal(15,3) DEFAULT NULL,
            `prp_min_percentage3` decimal(15,3) DEFAULT NULL,
            `prp_min_percentage4` decimal(15,3) DEFAULT NULL,
            `prp_min_percentage5` decimal(15,3) DEFAULT NULL,
            `prp_max_percentage1` decimal(15,3) DEFAULT NULL,
            `prp_max_percentage2` decimal(15,3) DEFAULT NULL,
            `prp_max_percentage3` decimal(15,3) DEFAULT NULL,
            `prp_max_percentage4` decimal(15,3) DEFAULT NULL,
            `prp_max_percentage5` decimal(15,3) DEFAULT NULL,
            `prp_min_free1` decimal(15,3) DEFAULT NULL,
            `prp_min_free2` decimal(15,3) DEFAULT NULL,
            `prp_min_free3` decimal(15,3) DEFAULT NULL,
            `prp_min_free4` decimal(15,3) DEFAULT NULL,
            `prp_min_free5` decimal(15,3) DEFAULT NULL,
            `prp_max_free1` decimal(15,3) DEFAULT NULL,
            `prp_max_free2` decimal(15,3) DEFAULT NULL,
            `prp_max_free3` decimal(15,3) DEFAULT NULL,
            `prp_max_free4` decimal(15,3) DEFAULT NULL,
            `prp_max_free5` decimal(15,3) DEFAULT NULL,
            `unt_code_free` varchar(50) DEFAULT NULL,
            `synced_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Última sincronización',
            PRIMARY KEY (`prp_code`),
            KEY `idx_pdl_code` (`pdl_code`),
            KEY `idx_prm_code` (`prm_code`),
            KEY `idx_pro_code` (`pro_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    public function execute(): array
    {
        Log::channel('single')->info("=== INICIO: Distribución de Promociones HUB → Tenants ===");

        $results = [
            'tenants_processed'    => 0,
            'tenants_skipped'      => 0,
            'promotions_synced'    => 0,
            'products_synced'      => 0,
            'promotions_deleted'   => 0,
            'errors'               => [],
        ];

        // 1. Obtener todos los Tenants activos (los que tienen el formato www_v*p)
        $tenants = CompanyRoute::where('is_active', true)
            ->where('db_name', 'LIKE', 'www_v%p')
            ->get();

        if ($tenants->isEmpty()) {
            Log::warning("SyncPromotionsToClients: No se encontraron Tenants activos con formato www_v*p");
            return $results;
        }

        Log::info("SyncPromotionsToClients: Encontrados {$tenants->count()} Tenants activos");

        // 2. Cargar todas las promociones maestras del HUB en memoria
        $masterPromotions = DB::table('master_promotions')->get()->keyBy('prm_code');
        $masterDetails = DB::table('master_promotion_details')->get();
        $masterProducts = DB::table('master_promotion_detail_products')->get();
        $masterRoutes = DB::table('master_promotion_routes')->get();

        Log::info("SyncPromotionsToClients: HUB tiene {$masterPromotions->count()} promociones, {$masterDetails->count()} detalles, {$masterProducts->count()} productos");

        // 3. Indexar detalles y productos por rot_code para distribución rápida
        // Agrupar detalles por rot_code
        $detailsByRoute = $masterDetails->groupBy('rot_code');
        // Agrupar productos por pdl_code para acceso rápido
        $productsByDetail = $masterProducts->groupBy('pdl_code');

        // 4. Iterar cada Tenant
        foreach ($tenants as $tenant) {
            try {
                // Extraer el rot_code del code del tenant (ej: "C001/C3_V09101" → "09101")
                $rotCode = $this->extractRotCode($tenant->code, $tenant->db_name);

                if (!$rotCode) {
                    Log::warning("SyncPromotionsToClients: No se pudo extraer rot_code de '{$tenant->code}', saltando");
                    $results['tenants_skipped']++;
                    continue;
                }

                Log::info("SyncPromotionsToClients: Procesando Tenant '{$tenant->name}' (DB: {$tenant->db_name}, rot_code: {$rotCode})");

                // Conectar al Tenant
                Config::set('database.connections.tenant.database', $tenant->db_name);
                DB::purge('tenant');

                // Desactivar modo estricto para compatibilidad con datos legacy
                DB::connection('tenant')->statement("SET SESSION sql_mode = ''");

                // Crear tablas si no existen
                $this->ensureTables($tenant->db_name);

                // Obtener los detalles que aplican a esta ruta
                $routeDetails = $detailsByRoute->get($rotCode, collect());

                // También incluir promociones asignadas por la tabla master_promotion_routes
                $prmCodesFromRoutes = $masterRoutes
                    ->where('rot_code', $rotCode)
                    ->pluck('prm_code')
                    ->unique();

                // Obtener detalles adicionales por prm_code de la tabla de rutas
                $additionalDetails = $masterDetails
                    ->whereIn('prm_code', $prmCodesFromRoutes)
                    ->whereNotIn('pdl_code', $routeDetails->pluck('pdl_code'));

                $allDetailsForTenant = $routeDetails->merge($additionalDetails);

                if ($allDetailsForTenant->isEmpty()) {
                    Log::info("SyncPromotionsToClients: Sin promociones para rot_code '{$rotCode}'");
                    $results['tenants_skipped']++;
                    continue;
                }

                // Limpiar promociones anteriores y reemplazar con las actuales (estrategia de refresco completo)
                $deletedPromos = DB::connection('tenant')->table('promociones_polar')->count();
                $deletedProducts = DB::connection('tenant')->table('promociones_polar_productos')->count();
                DB::connection('tenant')->table('promociones_polar_productos')->delete();
                DB::connection('tenant')->table('promociones_polar')->delete();
                $results['promotions_deleted'] += $deletedPromos;

                Log::info("SyncPromotionsToClients: Limpiados {$deletedPromos} promos y {$deletedProducts} productos previos en {$tenant->db_name}");

                // Insertar promociones para este Tenant (BATCH MODE)
                $promoData = [];
                $productData = [];
                $now = now();

                foreach ($allDetailsForTenant as $detail) {
                    $promotion = $masterPromotions->get($detail->prm_code);
                    if (!$promotion) continue;

                    // Preparar cabecera unificada
                    $promoData[] = [
                        'pdl_code'              => $detail->pdl_code,
                        'prm_code'              => $detail->prm_code,
                        'prm_name'              => $promotion->prm_name,
                        'pdl_name'              => $detail->pdl_name,
                        'pdl_since'             => $detail->pdl_since,
                        'pdl_until'             => $detail->pdl_until,
                        'cus_code'              => $detail->cus_code ? ltrim($detail->cus_code, '0') : null,
                        'rot_code'              => $detail->rot_code,
                        'tp3code'               => $detail->tp3code,
                        'tp1_code'              => $detail->tp1_code ?? null,
                        'tp2_code'              => $detail->tp2_code ?? null,
                        'tp3_code'              => $detail->tp3_code ?? null,
                        'pdl_minimum'           => $detail->pdl_minimum,
                        'unt_code_required'     => $detail->unt_code_required,
                        'unt_code_free'         => $detail->unt_code_free ?? null,
                        'pdl_order'             => $detail->pdl_order,
                        'pdl_scalable'          => $detail->pdl_scalable,
                        'pdl_accumulable'       => $detail->pdl_accumulable,
                        'prm_can_be_disabled'   => $promotion->prm_can_be_disabled,
                        'prm_enabled_value_on'  => $promotion->prm_enabled_value_on,
                        'prm_valid_to_sale'     => $promotion->prm_valid_to_sale,
                        'prm_extended_file'     => $promotion->prm_extended_file,
                        'pdl_extended_file'     => $detail->pdl_extended_file,
                        'synced_at'             => $now,
                    ];

                    // Preparar productos asociados
                    $detailProducts = $productsByDetail->get($detail->pdl_code, collect());
                    foreach ($detailProducts as $product) {
                        $productData[] = [
                            'prp_code'           => $product->prp_code,
                            'pdl_code'           => $product->pdl_code,
                            'prm_code'           => $product->prm_code,
                            'pro_code'           => $product->pro_code,
                            'unt_code'           => $product->unt_code,
                            'cl1code'            => $product->cl1code,
                            'cl2code'            => $product->cl2code,
                            'cl3code'            => $product->cl3code,
                            'cl4code'            => $product->cl4code,
                            'prp_required'       => $product->prp_required,
                            'prp_free'           => $product->prp_free,
                            'prp_valid_for_base_percentage' => $product->prp_valid_for_base_percentage,
                            'prp_quantity'       => $product->prp_quantity,
                            'prp_quantity1'      => $product->prp_quantity1,
                            'prp_quantity2'      => $product->prp_quantity2,
                            'prp_quantity3'      => $product->prp_quantity3,
                            'prp_quantity4'      => $product->prp_quantity4,
                            'prp_quantity5'      => $product->prp_quantity5,
                            'prp_min_percentage1'=> $product->prp_min_percentage1,
                            'prp_min_percentage2'=> $product->prp_min_percentage2,
                            'prp_min_percentage3'=> $product->prp_min_percentage3,
                            'prp_min_percentage4'=> $product->prp_min_percentage4,
                            'prp_min_percentage5'=> $product->prp_min_percentage5,
                            'prp_max_percentage1'=> $product->prp_max_percentage1 ?? null,
                            'prp_max_percentage2'=> $product->prp_max_percentage2,
                            'prp_max_percentage3'=> $product->prp_max_percentage3,
                            'prp_max_percentage4'=> $product->prp_max_percentage4,
                            'prp_max_percentage5'=> $product->prp_max_percentage5,
                            'prp_min_free1'      => $product->prp_min_free1,
                            'prp_min_free2'      => $product->prp_min_free2,
                            'prp_min_free3'      => $product->prp_min_free3,
                            'prp_min_free4'      => $product->prp_min_free4,
                            'prp_min_free5'      => $product->prp_min_free5,
                            'prp_max_free1'      => $product->prp_max_free1,
                            'prp_max_free2'      => $product->prp_max_free2,
                            'prp_max_free3'      => $product->prp_max_free3,
                            'prp_max_free4'      => $product->prp_max_free4,
                            'prp_max_free5'      => $product->prp_max_free5 ?? null,
                            'unt_code_free'      => $product->unt_code_free,
                            'synced_at'          => $now,
                        ];
                    }
                }

                // --- NUEVO: Asegurar que los productos existan en el Tenant antes de insertar promociones ---
                if (!empty($productData)) {
                    $relevantProductCodes = array_unique(array_column($productData, 'pro_code'));
                    $productEnsurer = new EnsurePromotionProductsExistAction();
                    $ensureRes = $productEnsurer->execute($tenant->db_name, $relevantProductCodes);
                    if ($ensureRes['created'] > 0) {
                        Log::info("SyncPromotionsToClients: Creados {$ensureRes['created']} productos faltantes en '{$tenant->name}'");
                    }
                }
                // ---------------------------------------------------------------------------------------------

                // Inserción masiva en bloques para optimizar velocidad y memoria
                if (!empty($promoData)) {
                    foreach (array_chunk($promoData, 50) as $chunk) {
                        DB::connection('tenant')->table('promociones_polar')->insert($chunk);
                    }
                }

                if (!empty($productData)) {
                    foreach (array_chunk($productData, 100) as $chunk) {
                        DB::connection('tenant')->table('promociones_polar_productos')->insert($chunk);
                    }
                }

                $promoCount = count($promoData);
                $productCount = count($productData);

                $results['promotions_synced'] += $promoCount;
                $results['products_synced'] += $productCount;
                $results['tenants_processed']++;

                Log::info("SyncPromotionsToClients: Tenant '{$tenant->name}' OK → {$promoCount} promos, {$productCount} productos");

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'tenant' => $tenant->name,
                    'db'     => $tenant->db_name,
                    'error'  => $e->getMessage(),
                ];
                Log::error("SyncPromotionsToClients: Error en Tenant '{$tenant->name}': " . $e->getMessage());
            }
        }

        Log::channel('single')->info("=== FIN: Distribución de Promociones ===", $results);
        return $results;
    }

    /**
     * Extrae el rot_code del código del CompanyRoute.
     * Ejemplo: "C001/C3_V09101" → "09101"
     */
    private function extractRotCode(string $code, ?string $dbName = null): ?string
    {
        // Patrón: *_V{rot_code} → extraer lo que está después de _V
        if (preg_match('/_V(\d+)$/i', $code, $matches)) {
            return $matches[1];
        }

        // Patrón: v{rot_code} (común en nuevos tenants)
        if (preg_match('/^v(\d+)$/i', $code, $matches)) {
            return $matches[1];
        }

        // Si el código es directamente un número (formato legacy)
        if (preg_match('/^\d+$/', $code)) {
            return $code;
        }

        // Si todo falla, intentar extraer de db_name (www_v{rot_code}p)
        if ($dbName && preg_match('/www_v(\d+)p/i', $dbName, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Asegura que las tablas de promociones existan en el Tenant.
     */
    private function ensureTables(string $dbName): void
    {
        $conn = DB::connection('tenant');

        // 1. promociones_polar
        $promoExists = $conn->select("SHOW TABLES LIKE 'promociones_polar'");
        if (empty($promoExists)) {
            $conn->statement(self::CREATE_PROMOCIONES_TABLE);
        } else {
            $columns = collect($conn->select("SHOW COLUMNS FROM `promociones_polar`"))->pluck('Field')->toArray();
            $required = ['pdl_order', 'pdl_scalable', 'pdl_accumulable', 'prm_extended_file', 'pdl_extended_file', 'tp1_code', 'tp2_code', 'tp3_code', 'unt_code_free'];
            foreach ($required as $col) {
                if (!in_array($col, $columns)) {
                    $type = (strpos($col, 'file') !== false) ? "TEXT DEFAULT NULL" : 
                            ((strpos($col, 'code') !== false) ? "varchar(50) DEFAULT NULL" :
                            (($col === 'pdl_order') ? "int(11) DEFAULT NULL" : "tinyint(1) DEFAULT 0"));
                    
                    $conn->statement("ALTER TABLE `promociones_polar` ADD COLUMN `$col` $type");
                }
            }
        }

        // 2. promociones_polar_productos
        $productsExists = $conn->select("SHOW TABLES LIKE 'promociones_polar_productos'");
        if (empty($productsExists)) {
            $conn->statement(self::CREATE_PRODUCTOS_TABLE);
        } else {
            // Verificar columnas faltantes
            $columns = collect($conn->select("SHOW COLUMNS FROM `promociones_polar_productos`"))->pluck('Field')->toArray();
            $required = [
                'prp_valid_for_base_percentage', 'prp_quantity', 'prp_quantity2', 'prp_quantity3', 
                'prp_quantity4', 'prp_quantity5', 'prp_min_percentage3', 'prp_min_percentage4', 
                'prp_min_percentage5', 'prp_max_percentage1', 'prp_max_percentage2', 'prp_max_percentage3', 
                'prp_max_percentage4', 'prp_max_percentage5', 'prp_min_free2', 'prp_min_free3', 
                'prp_min_free4', 'prp_min_free5', 'prp_max_free1', 'prp_max_free2', 
                'prp_max_free3', 'prp_max_free4', 'prp_max_free5', 'unt_code_free'
            ];
            foreach ($required as $col) {
                if (!in_array($col, $columns)) {
                    $type = (strpos($col, 'code') !== false) ? "varchar(50) DEFAULT NULL" : 
                            ((strpos($col, 'percentage') !== false || strpos($col, 'free') !== false || strpos($col, 'quantity') !== false && !in_array($col, ['prp_quantity'])) ? "decimal(15,3) DEFAULT NULL" : "tinyint(1) DEFAULT 0");
                    
                    // Ajuste fino para tipos específicos
                    if ($col === 'prp_quantity') $type = "tinyint(1) DEFAULT 0";

                    $conn->statement("ALTER TABLE `promociones_polar_productos` ADD COLUMN `$col` $type");
                }
            }
        }
    }
}
