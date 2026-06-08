<?php
declare(strict_types=1);

namespace Modules\MasterClient\Actions;

use Modules\CompanyRoute\Models\CompanyRoute;
use Modules\MasterClient\Models\MasterClient;
use Modules\MasterClient\Models\MasterCustomerPrice;
use Modules\MasterClient\Models\MasterCustomerRoute;
use Modules\MasterClient\Models\MasterCustomerFrequency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SyncOfficialCustomersAction
{
    protected EnsureCustomerPoolTablesExistAction $ensurePoolTablesAction;
    private array $routeMap = [];

    public function __construct(EnsureCustomerPoolTablesExistAction $ensurePoolTablesAction)
    {
        $this->ensurePoolTablesAction = $ensurePoolTablesAction;
    }
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
          `tp1_code` varchar(20) DEFAULT NULL,
          `PIN` varchar(100) NOT NULL,
          `Direccion` text NOT NULL,
          `email` varchar(150) NOT NULL,
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
          `brc_code` varchar(50) DEFAULT NULL,
          `fre_week1` varchar(10) DEFAULT NULL,
          `fre_week2` varchar(10) DEFAULT NULL,
          `fre_week3` varchar(10) DEFAULT NULL,
          `fre_week4` varchar(10) DEFAULT NULL,
          `fre_customer` varchar(10) DEFAULT NULL,
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
            'customer_frequencies_synced_master' => 0,
            'errors' => [],
        ];

        $startTime = microtime(true);

        try {
            $officialDb = DB::connection('productos_polar');
            Log::channel('stack')->info('=== SyncOfficialCustomers: INICIO DE SINCRONIZACIÓN ===');
            Log::channel('stack')->info('SyncOfficialCustomers: Timestamp: ' . now()->toDateTimeString());

            // 0. Build route map from companies_territories
            $territories = $officialDb->table('companies_territories')->get();
            $this->routeMap = [];
            foreach ($territories as $t) {
                if (empty($t->try_code)) continue;
                $parts = explode('-', $t->try_code);
                if (count($parts) === 2) {
                    $parent = ltrim(strtolower(trim($parts[0])), 'v'); // e.g. "v0824a" -> "0824a"
                    $child = ltrim(strtolower(trim($parts[1])), 'v');  // e.g. "v09101" -> "09101"
                    $this->routeMap[$child] = $parent;
                }
            }
            Log::channel('stack')->info("SyncOfficialCustomers: Built route map with " . count($this->routeMap) . " mappings.");

            // 1. Identify unique routes and ensure infrastructure
            $routes = $officialDb->table('customer_routes')->distinct()->pluck('rot_code');
            $totalCustomersInSource = $officialDb->table('customers')->count();
            $totalAssignmentsInSource = $officialDb->table('customer_routes')->count();
            Log::channel('stack')->info("SyncOfficialCustomers: [ORIGEN] Rutas únicas: {$routes->count()}, Clientes: {$totalCustomersInSource}, Asignaciones: {$totalAssignmentsInSource}");
            
            $uniqueMappedRoutes = [];
            foreach ($routes as $rot_code) {
                if (empty($rot_code)) continue;
                $cleanRot = ltrim(strtolower((string)$rot_code), 'v');
                $mappedRot = $this->routeMap[$cleanRot] ?? $cleanRot;
                if (!isset($uniqueMappedRoutes[$mappedRot])) {
                    $uniqueMappedRoutes[$mappedRot] = true;
                }
            }

            foreach (array_keys($uniqueMappedRoutes) as $rot_code) {
                $companyRoute = CompanyRoute::all()->first(function($cr) use ($rot_code) {
                    $crCleanRouteName = ltrim(strtolower((string)$cr->route_name), 'v');
                    $crCleanCode = ltrim(strtolower((string)$cr->code), 'v');
                    return $crCleanRouteName === $rot_code || $crCleanCode === $rot_code;
                });

                if ($companyRoute) {
                    $dbName = $companyRoute->db_name;
                    $cleanRotCode = ltrim(strtolower($companyRoute->route_name), 'v');
                } else {
                    $cleanRotCode = $rot_code;
                    $prefix = config('tenants.prefix', 'www_');
                    $suffix = config('tenants.suffix', 'p');
                    $dbName = $prefix . 'v' . $cleanRotCode . $suffix;
                }

                // Ensure infrastructure (DB + Table)
                if ($this->ensureInfrastructure($cleanRotCode, $dbName, $summary)) {
                    $summary['tenants_processed']++;
                }
            }
            Log::channel('stack')->info("SyncOfficialCustomers: [INFRA] {$summary['tenants_processed']} tenants verificados/creados.");

            // 2. Sync Pools to Master first
            $this->syncPools($officialDb, $summary);

            // 3. Sync Master and Tenants (Unified Loop)
            $this->syncData($officialDb, $summary);

            // 4. Sync Customer Prices
            $this->syncCustomerPrices($officialDb, $summary);

            // 5. Sync Customer Routes (Días de Visita + Contacto/Balance)
            $this->syncCustomerRoutes($officialDb, $summary);

            // 6. Sync Customer Frequencies (Semanas de Visita)
            $this->syncCustomerFrequencies($officialDb, $summary);

        } catch (\Exception $e) {
            Log::channel('stack')->error('SyncOfficialCustomers EXCEPCION GENERAL: ' . $e->getMessage());
            $summary['errors'][] = 'General Error: ' . $e->getMessage();
        }

        $elapsedSeconds = round(microtime(true) - $startTime, 2);
        Log::channel('stack')->info("=== SyncOfficialCustomers: FIN DE SINCRONIZACIÓN ===");
        Log::channel('stack')->info("SyncOfficialCustomers: [RESUMEN] Tenants: {$summary['tenants_processed']}, Master: {$summary['customers_synced_master']}, Tenants push: {$summary['customers_pushed_tenants']}, Errores: " . count($summary['errors']) . ", Tiempo: {$elapsedSeconds}s");
        if (!empty($summary['errors'])) {
            Log::channel('stack')->warning('SyncOfficialCustomers: [ERRORES] Primeros 5: ' . json_encode(array_slice($summary['errors'], 0, 5)));
        }

        return $summary;
    }

    private function ensureInfrastructure(string $rot_code, string $dbName, array &$summary): bool
    {
        $dbName = strtolower($dbName);
        try {
            // A. Ensure record in company_routes
            $exists = CompanyRoute::where('db_name', $dbName)->first();
            CompanyRoute::updateOrCreate(
                ['db_name' => $dbName],
                [
                    'code'       => $exists ? $exists->code : 'v' . ltrim($rot_code, 'v'),
                    'name'       => $exists ? $exists->name : 'v' . ltrim($rot_code, 'v'),
                    'route_name' => $exists ? $exists->route_name : 'v' . ltrim($rot_code, 'v'),
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
            Config::set('database.connections.tenant.strict', false); // Desactivar modo estricto para ignorar defaults faltantes
            DB::purge('tenant');
            
            try {
                $tableExists = DB::connection('tenant')->select("SHOW TABLES LIKE 'clientes'");
                if (empty($tableExists)) {
                    Log::info("SyncOfficialCustomers: Creating 'clientes' table in {$dbName}");
                    DB::connection('tenant')->statement(self::CREATE_CLIENTES_TABLE_SQL);
                } else {
                    $this->ensureCustomerPriceColumnsExist('tenant');
                }

                // Ensure Pool tables
                $this->ensurePoolTablesAction->execute('tenant');
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
            'latitud'            => 'VARCHAR(20) NOT NULL DEFAULT \'\'',
            'longitud'           => 'VARCHAR(20) NOT NULL DEFAULT \'\'',
            'csp_for_sale'       => 'TINYINT(1) NOT NULL DEFAULT 0',
            'csp_for_return'     => 'TINYINT(1) NOT NULL DEFAULT 0',
            'ctr_contact_person' => 'VARCHAR(100) DEFAULT NULL',
            'ctr_balance'        => 'VARCHAR(50) DEFAULT NULL',
            'prc_code_for_sale'  => 'VARCHAR(20) DEFAULT NULL',
            'con_code'           => 'VARCHAR(50) DEFAULT NULL',
            'brc_code'           => 'VARCHAR(50) DEFAULT NULL',
            'cus_credit_limit'   => 'VARCHAR(50) DEFAULT NULL',
            'cus_balance'        => 'VARCHAR(50) DEFAULT NULL',
            'tp1_code'           => 'VARCHAR(20) DEFAULT NULL',
            'fre_week1'          => 'VARCHAR(10) DEFAULT NULL',
            'fre_week2'          => 'VARCHAR(10) DEFAULT NULL',
            'fre_week3'          => 'VARCHAR(10) DEFAULT NULL',
            'fre_week4'          => 'VARCHAR(10) DEFAULT NULL',
            'fre_customer'       => 'VARCHAR(10) DEFAULT NULL',
        ];

        foreach ($toAdd as $col => $definition) {
            if (!in_array($col, $existingColumns)) {
                Log::info("SyncOfficialCustomers: Adding column {$col} to table clientes in connection {$connection}");
                DB::connection($connection)->statement("ALTER TABLE clientes ADD COLUMN `{$col}` {$definition}");
            }
        }

        // Expandir tamaño de email si es necesario
        try {
            DB::connection($connection)->statement("ALTER TABLE clientes MODIFY COLUMN `email` VARCHAR(150) NOT NULL");
        } catch (\Exception $e) {
            Log::warning("SyncOfficialCustomers: Could not modify email column in {$connection}: " . $e->getMessage());
        }
    }

    private function syncData($officialDb, array &$summary): void
    {
        // 1. Get all assignments and group them by route
        $assignments = $officialDb->table('customer_routes')
            ->leftJoin('customer_frequencies', 'customer_routes.fre_code', '=', 'customer_frequencies.fre_code')
            ->select('customer_routes.*', 
                     'customer_frequencies.fre_week1', 
                     'customer_frequencies.fre_week2', 
                     'customer_frequencies.fre_week3', 
                     'customer_frequencies.fre_week4', 
                     'customer_frequencies.fre_customer')
            ->get();
        
        Log::info('SyncOfficialCustomers: Found ' . $assignments->count() . ' assignments. Grouping by route...');

        // Grouping
        $groupedByRoute = [];
        foreach ($assignments as $asg) {
            $groupedByRoute[$asg->rot_code][] = $asg;
        }

        // 2. Pre-load all Pools from Master
        $allMasterPools = \Modules\MasterClient\Models\MasterPool::all()->map(fn($p) => [
            'pol_code' => $p->pol_code,
            'pol_name' => $p->pol_name,
            'pol_customer_search' => $p->pol_customer_search,
            'deleted' => $p->deleted,
            'updated_at' => now(),
        ])->toArray();

        // 3. Process each Route
        foreach ($groupedByRoute as $rotCode => $routeAssignments) {
            $cleanRot = ltrim(strtolower((string)$rotCode), 'v');
            $mappedRot = $this->routeMap[$cleanRot] ?? $cleanRot;
            
            $companyRoute = CompanyRoute::all()->first(function($cr) use ($mappedRot) {
                $crCleanRouteName = ltrim(strtolower((string)$cr->route_name), 'v');
                $crCleanCode = ltrim(strtolower((string)$cr->code), 'v');
                return $crCleanRouteName === $mappedRot || $crCleanCode === $mappedRot;
            });

            if (!$companyRoute) {
                Log::warning("SyncOfficialCustomers: CompanyRoute NOT FOUND for rot_code: {$rotCode} (mapped: {$mappedRot}). Skipping " . count($routeAssignments) . " customers.");
                continue;
            }

            Log::channel('stack')->info("SyncOfficialCustomers: [RUTA {$rotCode}] DB: {$companyRoute->db_name}, Clientes a procesar: " . count($routeAssignments));

            // A. Ensure Tenant Infrastructure
            $this->ensureInfrastructure($companyRoute->route_name, $companyRoute->db_name, $summary);
            
            // B. Connect to Tenant
            Config::set('database.connections.tenant.database', $companyRoute->db_name);
            DB::purge('tenant');
            $tenantDb = DB::connection('tenant');

            // C. Global Push of all Pools to this Tenant
            if (!empty($allMasterPools)) {
                foreach ($allMasterPools as $poolData) {
                    $tenantDb->table('pools')->updateOrInsert(['pol_code' => $poolData['pol_code']], $poolData);
                }
            }

            // D. Process each Customer in this route
            foreach ($routeAssignments as $assignment) {
                try {
                    $customer = $officialDb->table('customers')
                        ->where('cus_code', $assignment->cus_code)
                        ->orWhere('cus_code', ltrim($assignment->cus_code, '0'))
                        ->first();
                    
                    if (!$customer) continue;

                    $paddedCusCode = ltrim((string)$customer->cus_code, '0');

                    // D1. Sync to Master (master_client_polar via MasterClient model)
                    MasterClient::updateOrCreate(
                        ['cus_code' => $paddedCusCode],
                        [
                            'company_route_id'  => $companyRoute->id,
                            'cus_name'          => $customer->cus_name,
                            'cus_business_name' => $customer->cus_business_name,
                            'cus_administrator' => $customer->cus_administrator ?? null,
                            'tp1_code'          => $customer->tp1_code,
                            'tp2_code'          => $customer->tp2_code,
                            'cit_code'          => $customer->cit_code,
                            'cus_tax_id1'       => $customer->cus_tax_id1,
                            'cus_phone'         => $customer->cus_phone,
                            'cus_email'         => $customer->cus_email,
                            'registered_at_tenant' => now(),
                        ]
                    );
                    $summary['customers_synced_master']++;

                    // Log cada 50 clientes para no saturar
                    if ($summary['customers_synced_master'] % 50 === 0) {
                        Log::channel('stack')->info("SyncOfficialCustomers: [PROGRESO MASTER] {$summary['customers_synced_master']} clientes sincronizados...");
                    }

                    // D2. Resolve flags and Visit Days
                    $cspFlags = MasterCustomerPrice::where('rot_code', $assignment->rot_code)
                        ->where('cus_code', $paddedCusCode)
                        ->orderByDesc('csp_for_sale')->first();
                    
                    $routeFlags = MasterCustomerRoute::where('rot_code', $assignment->rot_code)
                        ->where('cus_code', $paddedCusCode)->first();

                    $activeDays = [];
                    if ($routeFlags) {
                        if ((int)$routeFlags->ctr_monday > 0) $activeDays[] = 'LUNES';
                        if ((int)$routeFlags->ctr_tuesday > 0) $activeDays[] = 'MARTES';
                        if ((int)$routeFlags->ctr_wednesday > 0) $activeDays[] = 'MIERCOLES';
                        if ((int)$routeFlags->ctr_thursday > 0) $activeDays[] = 'JUEVES';
                        if ((int)$routeFlags->ctr_friday > 0) $activeDays[] = 'VIERNES';
                        if ((int)$routeFlags->ctr_saturday > 0) $activeDays[] = 'SABADO';
                        if ((int)$routeFlags->ctr_sunday > 0) $activeDays[] = 'DOMINGO';
                    }

                    // D3. Push to Tenant Clientes
                    $tenantDb->table('clientes')->updateOrInsert(
                        ['cep' => $paddedCusCode],
                        [
                            'Cliente'       => $customer->cus_name ?? '',
                            'RIF'           => $customer->cus_tax_id1 ?? '',
                            'tp1_code'      => $customer->tp1_code ?? '',
                            'Direccion'     => ($customer->cus_street1 . ' ' . $customer->cus_street2 . ' ' . $customer->cus_street3),
                            'email'         => $customer->cus_email ?? '',
                            'Ruta'          => $assignment->rot_code,
                            'latitud'       => $customer->cus_latitude ?? '',
                            'longitud'      => $customer->cus_longitude ?? '',
                            'status'        => 'Activo',
                            'DiaDespacho1'  => $activeDays[0] ?? '',
                            'DiaDespacho2'  => $activeDays[1] ?? '',
                            'DiaDespacho3'  => $activeDays[0] ?? '',
                            'Activo'        => 1,
                            'PersonaContacto' => $customer->cus_contact_person ?? '',
                            'TelefonoContacto' => $customer->cus_phone ?? '',
                            'csp_for_sale'     => $cspFlags ? $cspFlags->csp_for_sale : 0,
                            'csp_for_return'    => $cspFlags ? $cspFlags->csp_for_return : 0,
                            'ctr_contact_person'=> $routeFlags ? $routeFlags->ctr_contact_person : null,
                            'ctr_balance'       => $routeFlags ? $routeFlags->ctr_balance : null,
                            'prc_code_for_sale' => $routeFlags ? $routeFlags->prc_code_for_sale : null,
                            'con_code'          => $customer->con_code ?? ($routeFlags ? $routeFlags->con_code : null),
                            'brc_code'          => $customer->brc_code,
                            'cus_credit_limit'  => $customer->cus_credit_limit,
                            'cus_balance'       => $customer->cus_balance,
                            'fre_week1'         => $assignment->fre_week1,
                            'fre_week2'         => $assignment->fre_week2,
                            'fre_week3'         => $assignment->fre_week3,
                            'fre_week4'         => $assignment->fre_week4,
                            'fre_customer'      => $assignment->fre_customer,
                        ]
                    );

                    // D4. Push CustomerPool relations for THIS customer
                    $cpRelations = \Modules\MasterClient\Models\MasterCustomerPool::where('cus_code', $paddedCusCode)->get();
                    foreach ($cpRelations as $rel) {
                        $tenantDb->table('customer_pools')->updateOrInsert(
                            ['cus_code' => $rel->cus_code, 'pol_code' => $rel->pol_code],
                            ['deleted' => $rel->deleted, 'updated_at' => now()]
                        );
                    }

                    $summary['customers_pushed_tenants']++;

                    // Log cada 50 clientes para no saturar
                    if ($summary['customers_pushed_tenants'] % 50 === 0) {
                        Log::channel('stack')->info("SyncOfficialCustomers: [PROGRESO TENANT] {$summary['customers_pushed_tenants']} clientes empujados a tenants...");
                    }

                } catch (\Exception $e) {
                    Log::channel('stack')->error("SyncOfficialCustomers: [ERROR TENANT] CEP {$assignment->cus_code} en ruta {$rotCode}: " . $e->getMessage());
                    $summary['errors'][] = "Error processing {$assignment->cus_code} on route {$rotCode}: " . $e->getMessage();
                }
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
                        'cus_code' => ltrim((string)$price->cus_code, '0'),
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
                        'cus_code' => ltrim((string)$route->cus_code, '0'),
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

    private function syncCustomerFrequencies($officialDb, array &$summary): void
    {
        Log::info('SyncOfficialCustomers: Starting sync of customer frequencies.');

        $frequencies = $officialDb->table('customer_frequencies')->get();
        $synced = 0;

        foreach ($frequencies as $freq) {
            try {
                MasterCustomerFrequency::updateOrCreate(
                    [
                        'fre_code' => $freq->fre_code,
                    ],
                    [
                        'fre_name'     => $freq->fre_name,
                        'fre_week1'    => $freq->fre_week1,
                        'fre_week2'    => $freq->fre_week2,
                        'fre_week3'    => $freq->fre_week3,
                        'fre_week4'    => $freq->fre_week4,
                        'fre_customer' => $freq->fre_customer,
                    ]
                );
                $synced++;
            } catch (\Exception $e) {
                $summary['errors'][] = "Error syncing customer frequency for fre_code {$freq->fre_code}: " . $e->getMessage();
            }
        }

        $summary['customer_frequencies_synced_master'] = $synced;
        Log::info("SyncOfficialCustomers: Finished syncing $synced customer frequencies.");
    }

    private function syncPools($officialDb, array &$summary): void
    {
        Log::info('SyncOfficialCustomers: Starting sync of Pools.');

        // A. Master Pools
        $pools = $officialDb->table('pools')->get();
        foreach ($pools as $pool) {
            \Modules\MasterClient\Models\MasterPool::updateOrCreate(
                ['pol_code' => $pool->pol_code],
                [
                    'pol_name' => $pool->pol_name,
                    'pol_customer_search' => $pool->pol_customer_search,
                    'deleted' => $pool->deleted,
                ]
            );
        }

        // B. Master CustomerPools
        $relations = $officialDb->table('customer_pools')->get();
        foreach ($relations as $rel) {
            \Modules\MasterClient\Models\MasterCustomerPool::updateOrCreate(
                ['cus_code' => $rel->cus_code, 'pol_code' => $rel->pol_code],
                ['deleted' => $rel->deleted]
            );
        }

        Log::info("SyncOfficialCustomers: Finished syncing " . $pools->count() . " pools and " . $relations->count() . " relations.");
    }
}
