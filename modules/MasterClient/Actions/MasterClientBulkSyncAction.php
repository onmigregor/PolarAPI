<?php

namespace Modules\MasterClient\Actions;

use Illuminate\Support\Facades\Log;
use Modules\MasterClient\Models\MasterClientPolar;
use Modules\CompanyRoute\Models\CompanyRoute;

class MasterClientBulkSyncAction
{
    public function execute(array $data, array $branches = []): array
    {
        $results = [
            'branches_synced' => 0,
            'created' => 0,
            'updated' => 0,
            'pushed_to_tenants' => 0,
            'errors' => [],
        ];

        // 0. Procesar Sucursales (Branches)
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

        // Si no vinieron branches en el payload, cargamos lo que tengamos en DB para el mapeo
        if (empty($branchesMap)) {
            $branchesMap = \Modules\MasterClient\Models\MasterClientBranch::pluck('tp2_name', 'tp2_code')->toArray();
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
                        'cep' => $item['cus_code'],
                        'Cliente' => $item['cus_business_name'] ?? ($item['cus_name'] ?? ''),
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
                        'segmento' => '',
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
                            ['cep' => $clientData['cep']],
                            $clientData
                        );
                    $results['pushed_to_tenants']++;
                }
                
                Log::info("MasterClientBulkSyncAction: Pushed " . count($clients) . " clients to {$dbName}");
            } catch (\Exception $e) {
                Log::error("Error pushing to tenant {$dbName}: " . $e->getMessage());
                $results['errors'][] = "Tenant {$dbName}: " . $e->getMessage();
            }
        }

        return $results;
    }
}
