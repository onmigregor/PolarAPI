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

    public function execute(array $data, array $branches = [], array $segments = [], array $pools = [], array $customerPools = []): array
    {
        $results = [
            'branches_synced' => 0,
            'segments_synced' => 0,
            'pools_synced' => 0,
            'customer_pools_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'pushed_to_tenants' => 0,
            'errors' => [],
        ];

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

        // 0a. Procesar Sucursales (Branches)
        $branchesMap = [];
        if (!empty($branches)) {
            foreach ($branches as $branch) {
                \Modules\MasterClient\Models\MasterClientBranch::updateOrCreate(
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

        // Si no vinieron datos en el payload, cargamos lo que tengamos en DB para el mapeo
        if (empty($branchesMap)) {
            $branchesMap = \Modules\MasterClient\Models\MasterClientBranch::pluck('tp2_name', 'tp2_code')->toArray();
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
                $companyRoute = null;

                if ($routeName) {
                    if (!isset($routesCache[$routeName])) {
                        $routesCache[$routeName] = CompanyRoute::where('route_name', $routeName)->first();
                    }
                    $companyRoute = $routesCache[$routeName];
                }

                // A. Guardar en Master
                $client = MasterClientPolar::updateOrCreate(
                    ['cus_code' => $item['cus_code']],
                    [
                        'cus_name' => $item['cus_name'] ?? null,
                        'cus_business_name' => $item['cus_business_name'] ?? null,
                        'cus_administrator' => $item['cus_administrator'] ?? null,
                        'company_route_id' => $companyRoute?->id,
                    ]
                );

                if ($client->wasRecentlyCreated) {
                    $results['created']++;
                } else {
                    $results['updated']++;
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

                    $groupedByRoute[$companyRoute->db_name][] = [
                        'IdCliente' => (int)ltrim($item['cus_code'], '0'),
                        'cep' => $item['cus_code'],
                        'Cliente' => $item['cus_business_name'] ?? ($item['cus_name'] ?? ''),
                        'cus_business_name' => $item['cus_business_name'] ?? '',
                        'Ruta' => $routeName,
                        'RIF' => $item['cus_tax_id1'] ?? '',
                        'status' => 'Activo',
                        // Campos de texto reales mapeados
                        'Direccion' => $item['address'] ?? '',
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
                        'PIN' => '',
                        'vendedor' => '',
                        'tipoclienteplantactico' => '',
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
                    ];
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
                
                foreach ($clients as $clientData) {
                    \Illuminate\Support\Facades\DB::connection('tenant')
                        ->table('clientes')
                        ->updateOrInsert(
                            ['IdCliente' => $clientData['IdCliente']],
                            $clientData
                        );
                    $results['pushed_to_tenants']++;
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
}
