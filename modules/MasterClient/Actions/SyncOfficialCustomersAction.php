<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterClient\Models\MasterClient;
use Modules\MasterClient\Models\MasterCustomerPrice;
use Modules\MasterClient\Models\MasterCustomerRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SyncOfficialCustomersAction
{
    /**
     * Structure of the 'clientes' table for tenants based on the provided dump.
     * Updated to include the 'cep' column as the link to Polar Master.
     */
    private const CREATE_CLIENTES_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `clientes` (
          `IdCliente` bigint(20) NOT NULL AUTO_INCREMENT,
          `cep` varchar(20) DEFAULT NULL,
          `Cliente` varchar(255) NOT NULL,
          `RIF` varchar(50) NOT NULL,
          `PIN` varchar(100) NOT NULL,
          `Direccion` text NOT NULL,
          `email` varchar(30) NOT NULL,
          `instagram` varchar(30) NOT NULL,
          `DiaDespacho1` varchar(255) NOT NULL,
          `DiaDespacho2` varchar(255) NOT NULL,
          `DiaDespacho3` varchar(255) NOT NULL,
          `FormaPago` varchar(20) NOT NULL,
          `diasCredito` int(11) NOT NULL,
          `MontoCredito` int(11) NOT NULL,
          `Activo` tinyint(4) NOT NULL,
          `PorcentajeAcuerdoComercial` int(11) NOT NULL,
          `tipoclienteplantactico` varchar(20) NOT NULL,
          `AgenteRetencion` tinyint(4) NOT NULL,
          `PorcentajeRetencion` int(11) NOT NULL,
          `prontopago` int(11) NOT NULL,
          `TipoCliente` varchar(100) NOT NULL,
          `idgrupo` int(11) NOT NULL,
          `PersonaContacto` varchar(100) NOT NULL,
          `TelefonoContacto` varchar(100) NOT NULL,
          `Ruta` varchar(50) NOT NULL,
          `categoria` varchar(3) NOT NULL,
          `licencialicor` varchar(30) NOT NULL,
          `nota` text NOT NULL,
          `segmento` varchar(30) NOT NULL,
          `ADC_CP` int(11) NOT NULL,
          `ADC_PCV` int(11) NOT NULL,
          `PCV` int(11) NOT NULL,
          `APC` int(11) NOT NULL,
          `CP` int(11) NOT NULL,
          `status` varchar(20) NOT NULL,
          `prioridad` int(11) NOT NULL,
          `perfilUsuario` varchar(10) NOT NULL,
          `perfilUsuarioApp` varchar(10) NOT NULL,
          `vendedor` varchar(100) NOT NULL,
          `promoAPC` int(11) NOT NULL DEFAULT 0,
          `promoCP` int(11) NOT NULL DEFAULT 0,
          `promoPCV` int(11) NOT NULL DEFAULT 0,
          `Descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
          `latitud` varchar(20) NOT NULL,
          `longitud` varchar(20) NOT NULL,
          `imagen_negocio` varchar(50) NOT NULL,
          `ubicacion_imagen_negocio` varchar(150) NOT NULL,
          `requiere_pasos_visita` int(11) NOT NULL DEFAULT 0,
          `csp_for_sale` tinyint(1) NOT NULL DEFAULT 0,
          `csp_for_return` tinyint(1) NOT NULL DEFAULT 0,
          `ctr_contact_person` varchar(100) DEFAULT NULL,
          `ctr_balance` varchar(50) DEFAULT NULL,
          `prc_code_for_sale` varchar(20) DEFAULT NULL,
          `con_code` varchar(50) DEFAULT NULL,
          UNIQUE KEY `IdCliente` (`IdCliente`),
          UNIQUE KEY `idx_cep` (`cep`),
          KEY `idx_ruta` (`Ruta`),
          KEY `idx_vendedor` (`vendedor`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    public function execute(): array
    {
        $summary = [
            'tenants_processed' => 0,
            'customers_synced_master' => 0,
            'customers_pushed_tenants' => 0,
            'errors' => [],
        ];

        try {
            $officialDb = DB::connection('productos_polar');
            Log::info('SyncOfficialCustomers: Starting process.');

            // 1. Identify unique routes and ensure infrastructure
            $routes = $officialDb->table('customer_routes')->distinct()->pluck('rot_code');
            
            foreach ($routes as $rot_code) {
                if (empty($rot_code)) continue;
                
                $dbName = 'www_' . $rot_code;
                
                // Ensure infrastructure (DB + Table)
                if ($this->ensureInfrastructure((string)$rot_code, $dbName, $summary)) {
                    $summary['tenants_processed']++;
                }
            }

            // 2. Sync Master and Tenants (Unified Loop)
            $this->syncData($officialDb, $summary);

            // 3. Sync Customer Prices
            $this->syncCustomerPrices($officialDb, $summary);

            // 4. Sync Customer Routes (Días de Visita + Contacto/Balance)
            $this->syncCustomerRoutes($officialDb, $summary);

        } catch (\Exception $e) {
            Log::error('SyncOfficialCustomers General Exception: ' . $e->getMessage());
            $summary['errors'][] = 'General Error: ' . $e->getMessage();
        }

        return $summary;
    }

    private function ensureInfrastructure(string $rot_code, string $dbName, array &$summary): bool
    {
        try {
            // A. Ensure record in company_routes
            CompanyRoute::updateOrCreate(
                ['code' => $rot_code],
                [
                    'name'       => $rot_code,
                    'route_name' => $rot_code,
                    'db_name'    => $dbName,
                    'is_active'  => true,
                ]
            );

            // B. Ensure physical Database exists
            // We use the main connection to check and create
            $dbExists = DB::connection('mysql')->select("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
            
            if (empty($dbExists)) {
                try {
                    Log::info("SyncOfficialCustomers: Creating database {$dbName}");
                    DB::connection('mysql')->statement("CREATE DATABASE `{$dbName}`");
                } catch (\Exception $e) {
                    // Check again if it was created despite the error (common in some Docker setups)
                    $dbExistsNow = DB::connection('mysql')->select("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                    if (empty($dbExistsNow)) throw $e; 
                    Log::warning("SyncOfficialCustomers: Database creation threw error but DB exists: " . $e->getMessage());
                }
            }

            // C. Ensure 'clientes' table exists in this tenant
            Config::set('database.connections.tenant.database', $dbName);
            DB::purge('tenant');
            
            try {
                $tableExists = DB::connection('tenant')->select("SHOW TABLES LIKE 'clientes'");
                if (empty($tableExists)) {
                    Log::info("SyncOfficialCustomers: Creating 'clientes' table in {$dbName}");
                    DB::connection('tenant')->statement(self::CREATE_CLIENTES_TABLE_SQL);
                } else {
                    $this->ensureCustomerPriceColumnsExist('tenant');
                }
            } catch (\Exception $e) {
                // If it fails but table exists, ignore
                try {
                    $tableExistsNow = DB::connection('tenant')->select("SHOW TABLES LIKE 'clientes'");
                    if (empty($tableExistsNow)) throw $e;
                    Log::warning("SyncOfficialCustomers: Table creation threw error but table exists: " . $e->getMessage());
                } catch (\Exception $inner) {
                    throw $e;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("SyncOfficialCustomers: Infrastructure error for {$rot_code}: " . $e->getMessage());
            $summary['errors'][] = "Infra for {$rot_code}: " . $e->getMessage();
            return false;
        }
    }

    private function ensureCustomerPriceColumnsExist(string $connection): void
    {
        $columns = DB::connection($connection)->select("SHOW COLUMNS FROM clientes");
        $existingColumns = array_column($columns, 'Field');

        $toAdd = [
            'csp_for_sale'       => 'TINYINT(1) NOT NULL DEFAULT 0',
            'csp_for_return'     => 'TINYINT(1) NOT NULL DEFAULT 0',
            'ctr_contact_person' => 'VARCHAR(100) DEFAULT NULL',
            'ctr_balance'        => 'VARCHAR(50) DEFAULT NULL',
            'prc_code_for_sale'  => 'VARCHAR(20) DEFAULT NULL',
            'con_code'           => 'VARCHAR(50) DEFAULT NULL',
        ];

        foreach ($toAdd as $col => $definition) {
            if (!in_array($col, $existingColumns)) {
                DB::connection($connection)->statement("ALTER TABLE clientes ADD COLUMN `{$col}` {$definition}");
            }
        }
    }

    private function syncData($officialDb, array &$summary): void
    {
        // Get all customer-route assignments
        $assignments = $officialDb->table('customer_routes')->get();
        Log::info('SyncOfficialCustomers: Found ' . $assignments->count() . ' assignments in official customer_routes.');
        
        foreach ($assignments as $assignment) {
            Log::info("SyncOfficialCustomers: Processing assignment for cus_code: {$assignment->cus_code}, rot_code: {$assignment->rot_code}");
            
            $customer = $officialDb->table('customers')
                ->where('cus_code', $assignment->cus_code)
                ->orWhere('cus_code', ltrim($assignment->cus_code, '0'))
                ->first();
            
            if (!$customer) {
                Log::warning("SyncOfficialCustomers: Customer not found for cus_code: {$assignment->cus_code} (normalized: " . ltrim($assignment->cus_code, '0') . ")");
                continue;
            }

            $companyRoute = CompanyRoute::where('code', $assignment->rot_code)->first();
            if (!$companyRoute) {
                Log::warning("SyncOfficialCustomers: CompanyRoute not found for rot_code: {$assignment->rot_code}");
                continue;
            }

            // A. Sync to Master
            Log::info("SyncOfficialCustomers: Syncing customer {$customer->cus_code} to master.");
            MasterClient::updateOrCreate(
                ['cep' => $customer->cus_code],
                [
                    'company_route_id'  => $companyRoute->id,
                    'cliente'           => $customer->cus_name ?? '',
                    'ruta'              => $assignment->rot_code,
                    'cus_name'          => $customer->cus_name,
                    'cus_business_name' => $customer->cus_business_name,
                    'cus_duns'          => $customer->cus_duns,
                    'cus_comm_id'       => $customer->cus_comm_id,
                    'tp1_code'          => $customer->tp1_code,
                    'tp2_code'          => $customer->tp2_code,
                    'cit_code'          => $customer->cit_code,
                    'txn_code'          => $customer->txn_code,
                    'cus_phone'         => $customer->cus_phone,
                    'cus_fax'           => $customer->cus_fax,
                    'cus_street1'       => $customer->cus_street1,
                    'cus_street2'       => $customer->cus_street2,
                    'cus_street3'       => $customer->cus_street3,
                    'cus_tax_id1'       => $customer->cus_tax_id1,
                    'brc_code'          => $customer->brc_code,
                    'cus_latitude'      => $customer->cus_latitude,
                    'cus_longitude'     => $customer->cus_longitude,
                    'prc_code_for_sale' => $customer->prc_code_for_sale,
                    'prc_code_for_return'=> $customer->prc_code_for_return,
                    'cus_contact_person'=> $customer->cus_contact_person,
                    'cus_email'         => $customer->cus_email,
                ]
            );
            $summary['customers_synced_master']++;

            // B. Push to Tenant
            try {
                // Fetch csp flags for this customer on this route
                $cspFlags = MasterCustomerPrice::where('rot_code', $assignment->rot_code)
                    ->where('cus_code', $customer->cus_code)
                    ->orderByDesc('csp_for_sale')
                    ->first();
                $cspForSale   = $cspFlags ? $cspFlags->csp_for_sale   : 0;
                $cspForReturn = $cspFlags ? $cspFlags->csp_for_return : 0;

                // Fetch route contact/balance fields for this customer
                // Note: master_customer_routes stores cus_code as-is from the provider (padded zeros)
                $routeFlags = MasterCustomerRoute::where('rot_code', $assignment->rot_code)
                    ->where('cus_code', $assignment->cus_code)
                    ->first();
                $ctrContactPerson = $routeFlags ? $routeFlags->ctr_contact_person : null;
                $ctrBalance       = $routeFlags ? $routeFlags->ctr_balance        : null;
                $prcCodeForSale   = $routeFlags ? $routeFlags->prc_code_for_sale  : null;
                $conCode          = $routeFlags ? $routeFlags->con_code           : null;

                Config::set('database.connections.tenant.database', $companyRoute->db_name);
                DB::purge('tenant');
                $tenantDb = DB::connection('tenant');

                $tenantDb->table('clientes')->updateOrInsert(
                    ['IdCliente' => (int)ltrim($customer->cus_code, '0')],
                    [
                        'cep'           => $customer->cus_code,
                        'Cliente'       => $customer->cus_name ?? '',
                        'RIF'           => $customer->cus_tax_id1 ?? '',
                        'Direccion'     => ($customer->cus_street1 . ' ' . $customer->cus_street2 . ' ' . $customer->cus_street3),
                        'email'         => $customer->cus_email ?? '',
                        'Ruta'          => $assignment->rot_code,
                        'latitud'       => $customer->cus_latitude ?? '',
                        'longitud'      => $customer->cus_longitude ?? '',
                        'status'        => 'Activo',
                        // ... set defaults for other required fields if needed
                        'PIN'           => '',
                        'instagram'     => '',
                        'DiaDespacho1'  => '',
                        'DiaDespacho2'  => '',
                        'DiaDespacho3'  => '',
                        'FormaPago'     => '',
                        'diasCredito'   => 0,
                        'MontoCredito'  => 0,
                        'Activo'        => 1,
                        'PorcentajeAcuerdoComercial' => 0,
                        'tipoclienteplantactico' => '',
                        'AgenteRetencion' => 0,
                        'PorcentajeRetencion' => 0,
                        'prontopago' => 0,
                        'TipoCliente' => '',
                        'idgrupo' => 0,
                        'PersonaContacto' => $customer->cus_contact_person ?? '',
                        'TelefonoContacto' => $customer->cus_phone ?? '',
                        'categoria' => '',
                        'licencialicor' => '',
                        'nota' => '',
                        'segmento' => '',
                        'ADC_CP' => 0,
                        'ADC_PCV' => 0,
                        'PCV' => 0,
                        'APC' => 0,
                        'CP' => 0,
                        'prioridad' => 0,
                        'perfilUsuario' => '',
                        'perfilUsuarioApp' => '',
                        'vendedor' => '',
                        'imagen_negocio' => '',
                        'ubicacion_imagen_negocio' => '',
                        'requiere_pasos_visita' => 0,
                        'csp_for_sale'        => $cspForSale,
                        'csp_for_return'       => $cspForReturn,
                        'ctr_contact_person'   => $ctrContactPerson,
                        'ctr_balance'          => $ctrBalance,
                        'prc_code_for_sale'    => $prcCodeForSale,
                        'con_code'             => $conCode,
                    ]
                );
                $summary['customers_pushed_tenants']++;
            } catch (\Exception $e) {
                $summary['errors'][] = "Error pushing {$customer->cus_code} to tenant {$companyRoute->db_name}: " . $e->getMessage();
            }
        }
    }

    private function syncCustomerPrices($officialDb, array &$summary): void
    {
        Log::info('SyncOfficialCustomers: Starting sync of customer prices.');
        
        $prices = $officialDb->table('customer_prices')->get();
        $synced = 0;
        
        foreach ($prices as $price) {
            try {
                MasterCustomerPrice::updateOrCreate(
                    [
                        'rot_code' => $price->rot_code,
                        'cus_code' => $price->cus_code,
                        'prc_code' => $price->prc_code,
                    ],
                    [
                        'csp_for_sale'   => $price->csp_for_sale,
                        'csp_for_return' => $price->csp_for_return,
                    ]
                );
                $synced++;
            } catch (\Exception $e) {
                $summary['errors'][] = "Error syncing customer price for cus_code {$price->cus_code}: " . $e->getMessage();
            }
        }
        
        $summary['customer_prices_synced_master'] = $synced;
        Log::info("SyncOfficialCustomers: Finished syncing $synced customer prices.");
    }

    private function syncCustomerRoutes($officialDb, array &$summary): void
    {
        Log::info('SyncOfficialCustomers: Starting sync of customer routes.');

        $routes = $officialDb->table('customer_routes')->get();
        $synced = 0;

        foreach ($routes as $route) {
            try {
                MasterCustomerRoute::updateOrCreate(
                    [
                        'rot_code' => $route->rot_code,
                        'cus_code' => $route->cus_code,
                    ],
                    [
                        'fre_code'           => $route->fre_code,
                        'ctr_monday'         => $route->ctr_monday,
                        'ctr_tuesday'        => $route->ctr_tuesday,
                        'ctr_wednesday'      => $route->ctr_wednesday,
                        'ctr_thursday'       => $route->ctr_thursday,
                        'ctr_friday'         => $route->ctr_friday,
                        'ctr_saturday'       => $route->ctr_saturday,
                        'ctr_sunday'         => $route->ctr_sunday,
                        'ctr_contact_person' => $route->ctr_contact_person,
                        'ctr_balance'        => $route->ctr_balance,
                        'prc_code_for_sale'  => $route->prc_code_for_sale,
                        'con_code'           => $route->con_code,
                    ]
                );
                $synced++;
            } catch (\Exception $e) {
                $summary['errors'][] = "Error syncing customer route for cus_code {$route->cus_code}: " . $e->getMessage();
            }
        }

        $summary['customer_routes_synced_master'] = $synced;
        Log::info("SyncOfficialCustomers: Finished syncing $synced customer routes.");
    }
}
