<?php

namespace Modules\MasterClient\Actions;

use Illuminate\Support\Facades\Log;
use Modules\MasterClient\Models\MasterClientPolar;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterClientBulkSyncAction
{
    protected EnsureCustomerPoolTablesExistAction $ensureTablesAction;

    public function __construct(EnsureCustomerPoolTablesExistAction $ensureTablesAction)
    {
        $this->ensureTablesAction = $ensureTablesAction;
    }

    public function execute(
        array $data, 
        array $branches = [], 
        array $segments = [], 
        array $pools = [], 
        array $customerPools = [],
        array $customerRoutes = [],
        array $customerPrices = [],
        array $customerFrequencies = [],
        array $types1 = []
    ): array {
        $results = [
            'types1_synced' => 0,
            'branches_synced' => 0,
            'segments_synced' => 0,
            'pools_synced' => 0,
            'customer_pools_synced' => 0,
            'customer_routes_synced' => 0,
            'customer_prices_synced' => 0,
            'customer_frequencies_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'pushed_to_tenants' => 0,
            'errors' => [],
        ];

        Log::info("MasterClientBulkSyncAction: Starting execution. Payload counts - data: " . count($data) . ", customer_routes: " . count($customerRoutes));

        // Homologación: Limpiar todos los ceros a la izquierda de cus_code
        $data = array_map(function($item) {
            if (isset($item['cus_code'])) {
                $item['cus_code'] = ltrim((string)$item['cus_code'], '0');
            }
            return $item;
        }, $data);

        if (!empty($customerPools)) {
            $customerPools = array_map(function($cp) {
                if (isset($cp['cus_code'])) {
                    $cp['cus_code'] = ltrim((string)$cp['cus_code'], '0');
                }
                return $cp;
            }, $customerPools);
        }

        if (!empty($customerRoutes)) {
            $customerRoutes = array_map(function($cr) {
                if (isset($cr['cus_code'])) {
                    $cr['cus_code'] = ltrim((string)$cr['cus_code'], '0');
                }
                return $cr;
            }, $customerRoutes);
        }

        if (!empty($customerPrices)) {
            $customerPrices = array_map(function($cp) {
                if (isset($cp['cus_code'])) {
                    $cp['cus_code'] = ltrim((string)$cp['cus_code'], '0');
                }
                return $cp;
            }, $customerPrices);
        }

        // 00. Procesar Clase 1 (Types1)
        if (!empty($types1)) {
            foreach ($types1 as $t1) {
                \Modules\MasterClient\Models\MasterClientType1::updateOrCreate(
                    ['tp1_code' => $t1['tp1_code']],
                    ['tp1_name' => $t1['tp1_name']]
                );
                $results['types1_synced']++;
            }
        }

        // 0a. Procesar Sucursales (Branches)
        $branchesMap = [];
        if (!empty($branches)) {
            foreach ($branches as $branch) {
                \Modules\MasterClient\Models\MasterClientType2::updateOrCreate(
                    ['tp2_code' => $branch['tp2_code']],
                    ['tp2_name' => $branch['tp2_name']]
                );
                $branchesMap[$branch['tp2_code']] = $branch['tp2_name'];
                $results['branches_synced']++;
            }
        }

        // 0b. Procesar Segmentos (Segments)
        $segmentsMap = [];
        if (!empty($segments)) {
            foreach ($segments as $segment) {
                \Modules\MasterClient\Models\MasterClientSegment::updateOrCreate(
                    ['tp3_code' => $segment['tp3_code']],
                    ['tp3_name' => $segment['tp3_name']]
                );
                $segmentsMap[$segment['tp3_code']] = $segment['tp3_name'];
                $results['segments_synced']++;
            }
        }

        // 0c. Procesar Pools
        if (!empty($pools)) {
            foreach ($pools as $pool) {
                \Modules\MasterClient\Models\MasterPool::updateOrCreate(
                    ['pol_code' => $pool['pol_code']],
                    [
                        'pol_name' => $pool['pol_name'],
                        'pol_customer_search' => $pool['pol_customer_search'],
                        'deleted' => $pool['deleted']
                    ]
                );
                $results['pools_synced']++;
            }
        }

        // 0d. Procesar Relación Cliente-Pool
        if (!empty($customerPools)) {
            foreach ($customerPools as $cp) {
                \Modules\MasterClient\Models\MasterCustomerPool::updateOrCreate(
                    [
                        'cus_code' => $cp['cus_code'],
                        'pol_code' => $cp['pol_code']
                    ],
                    ['deleted' => $cp['deleted']]
                );
                $results['customer_pools_synced']++;
            }
        }

        // 0e. Procesar customer_routes
        if (!empty($customerRoutes)) {
            foreach ($customerRoutes as $cr) {
                \Modules\MasterClient\Models\MasterCustomerRoute::updateOrCreate(
                    [
                        'rot_code' => $cr['rot_code'],
                        'cus_code' => $cr['cus_code'],
                    ],
                    [
                        'fre_code'           => $cr['fre_code'] ?? null,
                        'ctr_monday'         => $cr['ctr_monday'] ?? null,
                        'ctr_tuesday'        => $cr['ctr_tuesday'] ?? null,
                        'ctr_wednesday'      => $cr['ctr_wednesday'] ?? null,
                        'ctr_thursday'       => $cr['ctr_thursday'] ?? null,
                        'ctr_friday'         => $cr['ctr_friday'] ?? null,
                        'ctr_saturday'       => $cr['ctr_saturday'] ?? null,
                        'ctr_sunday'         => $cr['ctr_sunday'] ?? null,
                        'ctr_contact_person' => $cr['ctr_contact_person'] ?? null,
                        'ctr_balance'        => $cr['ctr_balance'] ?? null,
                        'prc_code_for_sale'  => $cr['prc_code_for_sale'] ?? null,
                        'con_code'           => $cr['con_code'] ?? null,
                    ]
                );
                $results['customer_routes_synced']++;
            }
        }

        // 0f. Procesar customer_prices
        if (!empty($customerPrices)) {
            foreach ($customerPrices as $cp) {
                \Modules\MasterClient\Models\MasterCustomerPrice::updateOrCreate(
                    [
                        'rot_code' => $cp['rot_code'],
                        'cus_code' => $cp['cus_code'],
                        'prc_code' => $cp['prc_code'],
                    ],
                    [
                        'csp_for_sale'   => $cp['csp_for_sale'] ?? 0,
                        'csp_for_return' => $cp['csp_for_return'] ?? 0,
                    ]
                );
                $results['customer_prices_synced']++;
            }
        }

        // 0g. Procesar customer_frequencies
        if (!empty($customerFrequencies)) {
            foreach ($customerFrequencies as $cf) {
                \Modules\MasterClient\Models\MasterCustomerFrequency::updateOrCreate(
                    [
                        'fre_code' => $cf['fre_code'],
                    ],
                    [
                        'fre_name'     => $cf['fre_name'] ?? null,
                        'fre_week1'    => $cf['fre_week1'] ?? null,
                        'fre_week2'    => $cf['fre_week2'] ?? null,
                        'fre_week3'    => $cf['fre_week3'] ?? null,
                        'fre_week4'    => $cf['fre_week4'] ?? null,
                        'fre_customer' => $cf['fre_customer'] ?? null,
                    ]
                );
                $results['customer_frequencies_synced']++;
            }
        }

        // Si no vinieron datos en el payload, cargamos lo que tengamos en DB para el mapeo
        if (empty($branchesMap)) {
            $branchesMap = \Modules\MasterClient\Models\MasterClientType2::pluck('tp2_name', 'tp2_code')->toArray();
        }
        if (empty($segmentsMap)) {
            $segmentsMap = \Modules\MasterClient\Models\MasterClientSegment::pluck('tp3_name', 'tp3_code')->toArray();
        }

        // 1. Agrupar datos por ruta para procesamiento eficiente
        $groupedByRoute = [];
        // Cache de objetos de ruta
        $routesCache = [];

        foreach ($data as $item) {
            try {
                $routeName = $item['route_name'] ?? null;
                $tenantRouteName = $item['tenant_route_name'] ?? $routeName; // Fallback a route_name si no viene
                $companyRoute = null;

                if ($tenantRouteName) {
                    if (!isset($routesCache[$tenantRouteName])) {
                        $cleanRot = ltrim(strtolower((string)$tenantRouteName), 'v');
                        $routesCache[$tenantRouteName] = CompanyRoute::all()
                            ->sortBy(function($cr) {
                                return $cr->cep ? 0 : 1;
                            })
                            ->first(function($cr) use ($cleanRot) {
                                $crCleanRouteName = ltrim(strtolower((string)$cr->route_name), 'v');
                                $crCleanCode = ltrim(strtolower((string)$cr->code), 'v');
                                return $crCleanRouteName === $cleanRot || $crCleanCode === $cleanRot;
                            });
                        if (!$routesCache[$tenantRouteName]) {
                            Log::warning("MasterClientBulkSyncAction: CompanyRoute NOT FOUND in DB for tenant_route_name: {$tenantRouteName} (cleaned: {$cleanRot})");
                        } else {
                            Log::info("MasterClientBulkSyncAction: Found CompanyRoute for tenant_route_name: {$tenantRouteName} -> DB: {$routesCache[$tenantRouteName]->db_name}");
                        }
                    }
                    $companyRoute = $routesCache[$tenantRouteName];
                }

                // A. Guardar en Master (si existe localmente por RIF y no tiene CEP, lo actualizamos asignándole el CEP)
                $existingLocal = null;
                if (!empty($item['cus_tax_id1'])) {
                    $existingLocal = MasterClientPolar::where('cus_tax_id1', $item['cus_tax_id1'])
                        ->where(function ($q) {
                            $q->whereNull('cus_code')->orWhere('cus_code', '');
                        })
                        ->where('company_route_id', $companyRoute?->id)
                        ->first();
                }

                // Verificar si ya existe otro registro con ese cus_code (CEP) oficial en master
                $existingOfficial = MasterClientPolar::where('cus_code', $item['cus_code'])->first();

                if ($existingOfficial) {
                    // Si ya existe el registro oficial con ese CEP en master:
                    // 1. Actualizamos el registro oficial con toda la información nueva (incluyendo RIF)
                    $existingOfficial->update([
                        'cus_name' => $item['cus_name'] ?? $existingOfficial->cus_name,
                        'cus_business_name' => $item['cus_business_name'] ?? $existingOfficial->cus_business_name,
                        'cus_administrator' => $item['cus_administrator'] ?? $existingOfficial->cus_administrator,
                        'company_route_id' => $companyRoute?->id ?? $existingOfficial->company_route_id,
                        'tp1_code' => $item['tp1_code'] ?? $existingOfficial->tp1_code,
                        'tp2_code' => $item['tp2_code'] ?? $existingOfficial->tp2_code,
                        'cit_code' => $item['cit_code'] ?? $existingOfficial->cit_code,
                        'cus_tax_id1' => $item['cus_tax_id1'] ?? $existingOfficial->cus_tax_id1,
                        'cus_phone' => $item['cus_phone'] ?? ($item['phone'] ?? $existingOfficial->cus_phone),
                        'cus_email' => $item['cus_email'] ?? $existingOfficial->cus_email,
                        'cus_duns' => $item['cus_duns'] ?? $existingOfficial->cus_duns,
                        'cus_comm_id' => $item['cus_comm_id'] ?? $existingOfficial->cus_comm_id,
                    ]);
                    $client = $existingOfficial;

                    // 2. Si existía un registro local temporal sin CEP para este mismo RIF y ruta, lo eliminamos para evitar duplicidades
                    if ($existingLocal && $existingLocal->id !== $existingOfficial->id) {
                        $existingLocal->delete();
                    }
                    $results['updated']++;
                } else if ($existingLocal) {
                    // Si no existe registro oficial, pero sí el local temporal, le asignamos el CEP y lo actualizamos
                    $existingLocal->update([
                        'cus_code' => $item['cus_code'],
                        'cus_name' => $item['cus_name'] ?? $existingLocal->cus_name,
                        'cus_business_name' => $item['cus_business_name'] ?? $existingLocal->cus_business_name,
                        'cus_administrator' => $item['cus_administrator'] ?? $existingLocal->cus_administrator,
                        'tp1_code' => $item['tp1_code'] ?? $existingLocal->tp1_code,
                        'tp2_code' => $item['tp2_code'] ?? $existingLocal->tp2_code,
                        'cit_code' => $item['cit_code'] ?? $existingLocal->cit_code,
                        'cus_phone' => $item['cus_phone'] ?? ($item['phone'] ?? $existingLocal->cus_phone),
                        'cus_email' => $item['cus_email'] ?? $existingLocal->cus_email,
                        'cus_duns' => $item['cus_duns'] ?? $existingLocal->cus_duns,
                        'cus_comm_id' => $item['cus_comm_id'] ?? $existingLocal->cus_comm_id,
                    ]);
                    $client = $existingLocal;
                    $results['updated']++;
                } else {
                    // Si no existe ninguno de los dos, se crea de cero
                    $client = MasterClientPolar::updateOrCreate(
                        ['cus_code' => $item['cus_code']],
                        [
                            'cus_name' => $item['cus_name'] ?? null,
                            'cus_business_name' => $item['cus_business_name'] ?? null,
                            'cus_administrator' => $item['cus_administrator'] ?? null,
                            'company_route_id' => $companyRoute?->id,
                            'tp1_code' => $item['tp1_code'] ?? null,
                            'tp2_code' => $item['tp2_code'] ?? null,
                            'cit_code' => $item['cit_code'] ?? null,
                            'cus_tax_id1' => $item['cus_tax_id1'] ?? null,
                            'cus_phone' => $item['cus_phone'] ?? ($item['phone'] ?? null),
                            'cus_email' => $item['cus_email'] ?? null,
                            'cus_duns' => $item['cus_duns'] ?? null,
                            'cus_comm_id' => $item['cus_comm_id'] ?? null,
                        ]
                    );
                    if ($client->wasRecentlyCreated) {
                        $results['created']++;
                    } else {
                        $results['updated']++;
                    }
                }

                // B. Preparar para push a Tenant
                if ($companyRoute && $companyRoute->db_name) {
                    // Lógica de Días de Despacho (Fuera del array)
                    $activeDays = [];
                    if (isset($item['days'])) {
                        if (($item['days']['monday'] ?? 0) > 0) $activeDays[] = 'LUNES';
                        if (($item['days']['tuesday'] ?? 0) > 0) $activeDays[] = 'MARTES';
                        if (($item['days']['wednesday'] ?? 0) > 0) $activeDays[] = 'MIERCOLES';
                        if (($item['days']['thursday'] ?? 0) > 0) $activeDays[] = 'JUEVES';
                        if (($item['days']['friday'] ?? 0) > 0) $activeDays[] = 'VIERNES';
                        if (($item['days']['saturday'] ?? 0) > 0) $activeDays[] = 'SABADO';
                        if (($item['days']['sunday'] ?? 0) > 0) $activeDays[] = 'DOMINGO';
                    }

                    // Resolver banderas y Días de Visita de las tablas Master recién sincronizadas
                    $paddedCusCode = $item['cus_code'];
                    $rotCode = ltrim((string)$routeName, 'vV');

                    $cspFlags = \Modules\MasterClient\Models\MasterCustomerPrice::where('rot_code', $rotCode)
                        ->where('cus_code', $paddedCusCode)
                        ->orderByDesc('csp_for_sale')->first();
                    
                    $routeFlags = \Modules\MasterClient\Models\MasterCustomerRoute::where('rot_code', $rotCode)
                        ->where('cus_code', $paddedCusCode)->first();

                    $freqFlags = null;
                    if ($routeFlags && $routeFlags->fre_code) {
                        $freqFlags = \Modules\MasterClient\Models\MasterCustomerFrequency::where('fre_code', $routeFlags->fre_code)->first();
                    }

                    $groupedByRoute[$companyRoute->db_name][] = [
                        'cep' => $item['cus_code'],
                        'Cliente' => $item['cus_business_name'] ?? ($item['cus_name'] ?? ''),
                        'cus_business_name' => $item['cus_business_name'] ?? '',
                        'Ruta' => strtoupper($tenantRouteName ?? $routeName),
                        'RIF' => $item['cus_tax_id1'] ?? '',
                        'status' => 'Activo',
                        // Campos de texto reales mapeados
                        'Direccion' => $item['address'] ?? '',
                        'cus_street1' => $item['cus_street1'] ?? '',
                        'cus_street2' => $item['cus_street2'] ?? '',
                        'cus_street3' => $item['cus_street3'] ?? '',
                        'latitud' => $item['latitude'] ?? '',
                        'longitud' => $item['longitude'] ?? '',
                        'PersonaContacto' => $item['contact_person'] ?? '',
                        'TelefonoContacto' => $item['phone'] ?? '',
                        'email' => '',
                        'instagram' => '',
                        'DiaDespacho1' => $activeDays[0] ?? '',
                        'DiaDespacho2' => $activeDays[1] ?? '',
                        'DiaDespacho3' => $activeDays[0] ?? '', // Se repite el primer día aquí
                        'FormaPago' => '',
                        'PIN' => 0,
                        'vendedor' => '',
                        'tipoclienteplantactico' => '',
                        'tp1_code' => $item['tp1_code'] ?? '',
                        'tp2_code' => $item['tp2_code'] ?? '',
                        'tp3_code' => $item['tp3_code'] ?? '',
                        'TipoCliente' => $branchesMap[$item['tp2_code'] ?? ''] ?? '',
                        'categoria' => '',
                        'licencialicor' => '',
                        'nota' => '',
                        'segmento' => $segmentsMap[$item['tp3_code'] ?? ''] ?? '',
                        'perfilUsuario' => '',
                        'perfilUsuarioApp' => '',
                        // Campos numéricos obligatorios
                        'diasCredito' => 0,
                        'MontoCredito' => 0,
                        'Activo' => 1,
                        'PorcentajeAcuerdoComercial' => 0,
                        'AgenteRetencion' => 0,
                        'PorcentajeRetencion' => 0,
                        'prontopago' => 0,
                        'idgrupo' => 0,
                        'ADC_CP' => 0,
                        'ADC_PCV' => 0,
                        'PCV' => 0,
                        'APC' => 0,
                        'CP' => 0,
                        'prioridad' => 0,
                        // Otros campos
                        'imagen_negocio' => '',
                        'ubicacion_imagen_negocio' => '',
                        'requiere_pasos_visita' => 0,
                        'promoAPC' => 0,
                        'promoCP' => 0,
                        'promoPCV' => 0,
                        'Descuento' => 0.00,
                        // Campos de identificación comercial
                        'cus_duns' => $item['cus_duns'] ?? '',
                        'cus_comm_id' => $item['cus_comm_id'] ?? '',
                        
                        // Campos de precio, ruta y frecuencia
                        'csp_for_sale'       => $cspFlags ? $cspFlags->csp_for_sale : 0,
                        'csp_for_return'     => $cspFlags ? $cspFlags->csp_for_return : 0,
                        'ctr_contact_person' => $routeFlags ? $routeFlags->ctr_contact_person : null,
                        'ctr_balance'        => $routeFlags ? $routeFlags->ctr_balance : null,
                        'prc_code_for_sale'  => $routeFlags ? $routeFlags->prc_code_for_sale : null,
                        'con_code'           => $routeFlags ? $routeFlags->con_code : null,
                        'fre_week1'          => $freqFlags ? $freqFlags->fre_week1 : null,
                        'fre_week2'          => $freqFlags ? $freqFlags->fre_week2 : null,
                        'fre_week3'          => $freqFlags ? $freqFlags->fre_week3 : null,
                        'fre_week4'          => $freqFlags ? $freqFlags->fre_week4 : null,
                        'fre_customer'       => $freqFlags ? $freqFlags->fre_customer : null,
                    ];
                } else {
                    static $skippedCount = 0;
                    if ($skippedCount < 5) {
                        Log::warning("MasterClientBulkSyncAction: Skipping tenant push for customer {$item['cus_code']} because companyRoute is null or has no db_name");
                        $skippedCount++;
                    }
                }

            } catch (\Exception $e) {
                Log::error("Error syncing master client {$item['cus_code']}: " . $e->getMessage());
                $results['errors'][] = "CusCode {$item['cus_code']}: " . $e->getMessage();
            }
        }

        // 2. Procesar Push a cada Tenant
        foreach ($groupedByRoute as $dbName => $clients) {
            try {
                \Illuminate\Support\Facades\Config::set('database.connections.tenant.database', $dbName);
                \Illuminate\Support\Facades\DB::purge('tenant');
                
                // Asegurar las columnas correctas en la tabla clientes de este tenant
                $this->ensureClientesColumnsExist('tenant');

                foreach ($clients as $clientData) {
                    $updated = false;
                    if (!empty($clientData['RIF'])) {
                        // Buscar si existe un cliente local con el mismo RIF y sin código CEP
                        $existingTenantClient = \Illuminate\Support\Facades\DB::connection('tenant')
                            ->table('clientes')
                            ->where('RIF', $clientData['RIF'])
                            ->where(function ($q) {
                                $q->whereNull('cep')->orWhere('cep', '');
                            })
                            ->first();

                        if ($existingTenantClient) {
                            \Illuminate\Support\Facades\DB::connection('tenant')
                                ->table('clientes')
                                ->where('IdCliente', $existingTenantClient->IdCliente)
                                ->update($clientData);
                            $updated = true;
                        }
                    }

                    if (!$updated) {
                        \Illuminate\Support\Facades\DB::connection('tenant')
                            ->table('clientes')
                            ->updateOrInsert(
                                ['cep' => $clientData['cep']],
                                $clientData
                            );
                    }
                    $results['pushed_to_tenants']++;

                    // Marcar como registrado en tenant en la tabla maestra
                    MasterClientPolar::where('cus_code', $clientData['cep'])
                        ->update(['registered_at_tenant' => now()]);
                }

                // C. Sincronizar Pools en el Tenant
                $this->ensureTablesAction->execute('tenant');

                if (!empty($pools)) {
                    foreach ($pools as $pool) {
                        \Illuminate\Support\Facades\DB::connection('tenant')
                            ->table('pools')
                            ->updateOrInsert(
                                ['pol_code' => $pool['pol_code']],
                                [
                                    'pol_name' => $pool['pol_name'],
                                    'pol_customer_search' => $pool['pol_customer_search'],
                                    'deleted' => $pool['deleted'],
                                    'updated_at' => now(),
                                ]
                            );
                    }
                }

                // D. Sincronizar Relaciones Cliente-Pool (Solo para clientes de este tenant)
                $tenantCusCodes = array_column($clients, 'cep');
                $tenantCustomerPools = array_filter($customerPools, fn($cp) => in_array($cp['cus_code'], $tenantCusCodes));

                if (!empty($tenantCustomerPools)) {
                    foreach ($tenantCustomerPools as $tcp) {
                        \Illuminate\Support\Facades\DB::connection('tenant')
                            ->table('customer_pools')
                            ->updateOrInsert(
                                [
                                    'cus_code' => $tcp['cus_code'],
                                    'pol_code' => $tcp['pol_code']
                                ],
                                [
                                    'deleted' => $tcp['deleted'],
                                    'updated_at' => now(),
                                ]
                            );
                    }
                }
                
                Log::info("MasterClientBulkSyncAction: Pushed " . count($clients) . " clients and " . count($tenantCustomerPools) . " pool relations to {$dbName}");
            } catch (\Exception $e) {
                Log::error("Error pushing to tenant {$dbName}: " . $e->getMessage());
                $results['errors'][] = "Tenant {$dbName}: " . $e->getMessage();
            }
        }

        return $results;
    }

    private function ensureClientesColumnsExist(string $connection): void
    {
        try {
            $columns = \Illuminate\Support\Facades\DB::connection($connection)->select("SHOW COLUMNS FROM clientes");
            $existingColumns = array_column($columns, 'Field');

            $toAdd = [
                'cus_business_name'  => 'VARCHAR(255) DEFAULT NULL',
                'tipoclienteplantactico' => 'VARCHAR(50) DEFAULT NULL',
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
                'tp2_code'           => 'VARCHAR(20) DEFAULT NULL',
                'tp3_code'           => 'VARCHAR(20) DEFAULT NULL',
                'fre_week1'          => 'VARCHAR(10) DEFAULT NULL',
                'fre_week2'          => 'VARCHAR(10) DEFAULT NULL',
                'fre_week3'          => 'VARCHAR(10) DEFAULT NULL',
                'fre_week4'          => 'VARCHAR(10) DEFAULT NULL',
                'fre_customer'       => 'VARCHAR(10) DEFAULT NULL',
                // Legacy fields that might be missing on some tenants
                'imagen_negocio'     => 'VARCHAR(255) DEFAULT NULL',
                'ubicacion_imagen_negocio' => 'VARCHAR(255) DEFAULT NULL',
                'requiere_pasos_visita'=> 'TINYINT(1) NOT NULL DEFAULT 0',
                'promoAPC'           => 'INT(11) NOT NULL DEFAULT 0',
                'promoCP'            => 'INT(11) NOT NULL DEFAULT 0',
                'promoPCV'           => 'INT(11) NOT NULL DEFAULT 0',
                'email'              => 'VARCHAR(150) DEFAULT NULL',
                'instagram'          => 'VARCHAR(30) DEFAULT NULL',
                'DiaDespacho1'       => 'VARCHAR(255) DEFAULT NULL',
                'DiaDespacho2'       => 'VARCHAR(255) DEFAULT NULL',
                'DiaDespacho3'       => 'VARCHAR(255) DEFAULT NULL',
                'FormaPago'          => 'VARCHAR(20) DEFAULT NULL',
                'PIN'                => 'VARCHAR(20) DEFAULT NULL',
                'vendedor'           => 'VARCHAR(100) DEFAULT NULL',
                'categoria'          => 'VARCHAR(3) DEFAULT NULL',
                'licencialicor'      => 'VARCHAR(30) DEFAULT NULL',
                'nota'               => 'TEXT DEFAULT NULL',
                'perfilUsuario'      => 'VARCHAR(10) DEFAULT NULL',
                'perfilUsuarioApp'   => 'VARCHAR(10) DEFAULT NULL',
                'motivo_no_cep'      => 'VARCHAR(255) DEFAULT NULL',
                'cus_street1'        => 'VARCHAR(255) DEFAULT NULL',
                'cus_street2'        => 'VARCHAR(255) DEFAULT NULL',
                'cus_street3'        => 'VARCHAR(255) DEFAULT NULL',
                'cus_duns'           => 'VARCHAR(50) DEFAULT NULL',
                'cus_comm_id'        => 'VARCHAR(50) DEFAULT NULL',
            ];

            foreach ($toAdd as $col => $definition) {
                if (!in_array($col, $existingColumns)) {
                    Log::info("MasterClientBulkSyncAction: Adding missing column {$col} to table clientes in connection {$connection}");
                    \Illuminate\Support\Facades\DB::connection($connection)->statement("ALTER TABLE clientes ADD COLUMN `{$col}` {$definition}");
                }
            }
        } catch (\Exception $e) {
            Log::warning("MasterClientBulkSyncAction: Error ensuring column structure on {$connection}: " . $e->getMessage());
        }
    }
}
