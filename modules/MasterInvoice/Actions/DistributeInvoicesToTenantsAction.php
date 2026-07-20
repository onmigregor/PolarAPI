<?php

namespace Modules\MasterInvoice\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class DistributeInvoicesToTenantsAction
{
    /**
     * Distribuye las facturas recibidas a las tablas de compras de cada tenant.
     */
    public function execute(array $data)
    {
        if (empty($data)) {
            return [
                'success' => true,
                'tenants_processed' => 0,
                'errors' => [],
                'procedures' => [],
            ];
        }

        $errors = [];
        $successCount = 0;
        $tenantProcedureDetails = [];

        // 1. Agrupar facturas por la columna 'zona_venta' (que contiene el código de la ruta, ej: V1262A)
        $groupedByZone = collect($data)->groupBy('zona_venta');

        // 2. Distribuir a cada ruta correspondiente
        foreach ($groupedByZone as $zone => $items) {
            if (empty($zone)) {
                Log::warning("DistributeInvoicesToTenantsAction: Grupo de facturas sin 'zona_venta'. Saltando.");
                continue;
            }

            $prefix = config('tenants.prefix', 'www_');
            $suffix = config('tenants.suffix', 'p');

            $cleanZone = ltrim(strtolower(trim($zone)), 'v');
            
            // Si el código de zona termina en el sufijo "p" (ej: "0568ap" termina en "ap" que es "a" + "p"), obtenemos la versión sin "p"
            $cleanZoneWithoutP = str_ends_with($cleanZone, $suffix) ? substr($cleanZone, 0, -strlen($suffix)) : $cleanZone;

            // Reconstruir nombres de base de datos variantes según config
            $dbNameVariant1 = $prefix . 'v' . $cleanZone; // ej: www_v0568ap (si zone ya venía con P)
            $dbNameVariant2 = $prefix . 'v' . $cleanZoneWithoutP . $suffix; // ej: www_v0568ap (si zone venía sin P)

            // Buscar la ruta/tenant que corresponda a este código
            $company = DB::table('company_routes')
                ->where('is_active', true)
                ->where(function($query) use ($cleanZone, $cleanZoneWithoutP, $dbNameVariant1, $dbNameVariant2) {
                    $query->where('route_name', $cleanZone)
                          ->orWhere('route_name', $cleanZoneWithoutP)
                          ->orWhere('route_name', 'v' . $cleanZoneWithoutP)
                          ->orWhere('zone', $cleanZone)
                          ->orWhere('zone', $cleanZoneWithoutP)
                          ->orWhere('db_name', $dbNameVariant1)
                          ->orWhere('db_name', $dbNameVariant2)
                          ->orWhere('code', 'LIKE', "%_{$cleanZoneWithoutP}")
                          ->orWhere('code', 'LIKE', "%_{$cleanZone}");
                })
                ->first();

            if (!$company) {
                Log::warning("DistributeInvoicesToTenantsAction: No se encontró tenant para la ruta '$zone' (limpio: '$cleanZone', sin P: '$cleanZoneWithoutP'). Saltando grupo.");
                continue;
            }

            $dbName = $company->db_name;
            $routeName = $company->route_name ?? $company->name;

            try {
                // Configurar conexión dinámica
                $this->switchToTenant($dbName);

                // Verificar si la tabla compras existe en este tenant
                $hasTable = DB::connection('tenant')->select("SHOW TABLES LIKE 'compras'");
                if (empty($hasTable)) {
                    Log::warning("DistributeInvoicesToTenantsAction: La tabla 'compras' no existe en el tenant $dbName. Saltando tenant.");
                    continue;
                }

                // Agrupar por No Factura para crear cabeceras
                $groupedByInvoice = $items->groupBy('no_factura');

                foreach ($groupedByInvoice as $nroFactura => $lines) {
                    $validLines = [];
                    $firstLine = $lines->first();
                    $fecha = $this->formatDate($firstLine['fecha_creacion']);
                    $fechaVencimientoRaw = !empty($firstLine['fecha_vencimiento']) ? $firstLine['fecha_vencimiento'] : $firstLine['fecha_creacion'];
                    $fechaVencimiento = $this->formatDate($fechaVencimientoRaw);
                    $tasa = (float)($firstLine['tasa'] ?? 0);

                    // 1. Validar qué líneas tienen productos existentes
                    foreach ($lines as $line) {
                        $material = trim($line['material']);
                        $product = DB::connection('tenant')->table('productos')
                            ->where('codigoSKU', $material)
                            ->orWhere('codigoSKU', ltrim($material, '0'))
                            ->first();

                        if ($product) {
                            $validLines[] = [
                                'line' => $line,
                                'product' => $product
                            ];
                        } else {
                            Log::warning("DistributeInvoicesToTenantsAction: SKU $material no encontrado en tenant $dbName. Saltando linea.");
                        }
                    }

                    if (empty($validLines)) {
                        Log::warning("DistributeInvoicesToTenantsAction: La factura $nroFactura no tiene ningun producto valido en tenant $dbName. Saltando factura completa.");
                        continue;
                    }

                    // 2. Calcular totales solo con lineas validas
                    $totalFactura = collect($validLines)->sum(fn($vl) => ($vl['line']['cantidad'] ?? 0) * ($vl['line']['precio'] ?? 0));
                    $totalIvaAmount = collect($validLines)->sum(fn($vl) => ($vl['line']['iva'] ?? 0) * ($vl['line']['cantidad'] ?? 1));

                    // Obtener info del proveedor
                    $providerInfo = $this->getProviderInfo($firstLine['codigo_polar_negocio']);
                    $montoDivisas = $tasa > 0 ? (($totalFactura + $totalIvaAmount) / $tasa) : 0;

                    // 3. Crear/Actualizar Cabecera
                    DB::connection('tenant')->table('compras')->updateOrInsert(
                        ['nrofactura' => $nroFactura],
                        [
                            'idcompra_detalle' => 0,
                            'nrocontrol' => $firstLine['no_control'] ?? '',
                            'fecha' => $fecha,
                            'fechavencimiento' => $fechaVencimiento,
                            'ruta' => $routeName,
                            'proveedor' => $providerInfo['name'],
                            'tipofactura' => 'FAC',
                            'status' => 'PENDIENTE',
                            'aportes' => 0,
                            'envasesentregados' => 0,
                            'baseimponible' => $totalFactura,
                            'totalcomprasconiva' => $totalFactura + $totalIvaAmount,
                            'totalfactura' => $totalFactura + $totalIvaAmount,
                            'itf' => 0,
                            'montopendiente' => $totalFactura + $totalIvaAmount,
                            'iva' => $totalIvaAmount > 0 ? 1 : 0,
                            'ivaretenido' => 0,
                            'nrocomprobante' => '0',
                            'fecharetencion' => $fecha,
                            'periodofiscal' => '',
                            'c' => 0,
                            'm' => 0,
                            's' => 0,
                            'pcv' => 0,
                            'apc' => 0,
                            'pomar' => 0,
                            'pg' => 0,
                            'vaciocuarto' => 0,
                            'vaciotercio' => 0,
                            'vacio350' => 0,
                            'vacio125' => 0,
                            'debecuarto' => 0,
                            'debetercio' => 0,
                            'debe350' => 0,
                            'debe125' => 0,
                            'comentario' => '',
                            'fecha_tasa' => $fecha,
                            'tasa' => $tasa,
                            'montodivisas' => $montoDivisas,
                            'totalpagarbs' => $totalFactura + $totalIvaAmount,
                            'enlace' => '',
                            'horaRegistro' => now()->format('Y-m-d H:i:s'),
                            'cep' => $firstLine['fq_redi'] ?? '',
                            'idusuario' => 1,
                            'idproveedor' => $providerInfo['id'],
                            'eliminado' => 0,
                        ]
                    );

                    $idCompraReal = DB::connection('tenant')->table('compras')
                        ->where('nrofactura', $nroFactura)
                        ->value('idcompra');

                    DB::connection('tenant')->table('compras')
                        ->where('idcompra', $idCompraReal)
                        ->update(['idcompra_detalle' => $idCompraReal]);

                    // 4. Insertar Detalles
                    foreach ($validLines as $vl) {
                        $line = $vl['line'];
                        $product = $vl['product'];
                        $precioCompra = (float)($line['precio'] ?? 0);
                        $cantidad = (int)($line['cantidad'] ?? 0);

                        DB::connection('tenant')->table('compras_detalle')->updateOrInsert(
                            [
                                'ruta' => $routeName,
                                'idcompra' => $idCompraReal,
                                'idproducto' => $product->idproducto,
                            ],
                            [
                                'fecha' => $fecha,
                                'producto' => $product->producto,
                                'codigoSKU' => $product->codigoSKU ?? trim($line['material'] ?? ''),
                                'cantidad' => $cantidad,
                                'preciocompra' => $precioCompra,
                                'precioventa' => $precioCompra,
                                'tasa' => $tasa,
                                'fecha_tasa' => $fecha,
                                'montodivisas' => $cantidad * $precioCompra,
                                'porcentaje_rentab' => 0,
                                'rentabilidad' => 0,
                                'fideicomiso' => 0,
                                'sync' => 1,
                                'idusuario' => 1,
                                'clave_configuracion' => '',
                                'exento_iva' => ($line['iva'] ?? 0) > 0 ? 0 : 1,
                                'eliminado' => 0,
                            ]
                        );
                    }
                }

                // 5. Ejecución Automática de Stored Procedures (Optimizada y Aislada)
                $procLogs = [];
                $procSuccess = true;

                // Agrupar facturas por código de ruta y fecha mínima
                $routesSummary = [];
                foreach ($groupedByInvoice as $nroFactura => $lines) {
                    $firstLine = $lines->first();
                    $rotCode = strtoupper(trim($firstLine['zona_venta'] ?? ''));
                    $invoiceDate = $this->formatDate($firstLine['fecha_creacion']);

                    if (!empty($rotCode)) {
                        if (!isset($routesSummary[$rotCode]) || $invoiceDate < $routesSummary[$rotCode]) {
                            $routesSummary[$rotCode] = $invoiceDate;
                        }
                    }
                }

                foreach ($routesSummary as $rotCode => $minDate) {
                    try {
                        // 5a. validar_mov_entrada_inv
                        $proc1Exists = DB::connection('tenant')->select(
                            "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = 'validar_mov_entrada_inv'",
                            [$dbName]
                        );

                        if (!empty($proc1Exists)) {
                            DB::connection('tenant')->statement("CALL validar_mov_entrada_inv(?, ?)", [$rotCode, $minDate]);
                            $procLogs[] = "Ruta {$rotCode} (Fecha min {$minDate}): validar_mov_entrada_inv OK.";
                            Log::info("DistributeInvoicesToTenantsAction: Executed validar_mov_entrada_inv('$rotCode', '$minDate') on $dbName.");
                        } else {
                            $procLogs[] = "Ruta {$rotCode}: validar_mov_entrada_inv no existe en $dbName.";
                        }

                        // 5b. corregir_kardex_stock_ruta
                        $proc2Exists = DB::connection('tenant')->select(
                            "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = 'corregir_kardex_stock_ruta'",
                            [$dbName]
                        );

                        if (!empty($proc2Exists)) {
                            DB::connection('tenant')->statement("CALL corregir_kardex_stock_ruta(?)", [$rotCode]);
                            $procLogs[] = "Ruta {$rotCode}: corregir_kardex_stock_ruta OK.";
                            Log::info("DistributeInvoicesToTenantsAction: Executed corregir_kardex_stock_ruta('$rotCode') on $dbName.");
                        } else {
                            $procLogs[] = "Ruta {$rotCode}: corregir_kardex_stock_ruta no existe en $dbName.";
                        }

                    } catch (\Exception $procEx) {
                        $procSuccess = false;
                        $errStr = $procEx->getMessage();
                        Log::error("DistributeInvoicesToTenantsAction Procedure Error tenant $dbName, ruta $rotCode: " . $errStr);
                        $procLogs[] = "Ruta {$rotCode} Error Procedure: " . $errStr;
                    }
                }

                $tenantProcedureDetails[$dbName] = [
                    'success' => $procSuccess,
                    'logs' => $procLogs,
                ];

                $successCount++;
                Log::info("DistributeInvoicesToTenantsAction: Procesado tenant $dbName con " . count($items) . " registros de facturas.");

            } catch (\Exception $e) {
                Log::error("DistributeInvoicesToTenantsAction Error en tenant $dbName: " . $e->getMessage());
                $errors[$dbName] = $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'tenants_processed' => $successCount + count($errors),
            'errors' => $errors,
            'procedures' => $tenantProcedureDetails,
        ];
    }

    private function getProviderInfo($code)
    {
        $mapping = [
            'CYM' => ['name' => 'CERVECERIA POLAR C.A.', 'id' => 11],
            'C001' => ['name' => 'CERVECERIA POLAR C.A.', 'id' => 11],
            'PCV' => ['name' => 'PEPSI-COLA VENEZUELA, C.A.', 'id' => 12],
            'R200' => ['name' => 'PEPSI-COLA VENEZUELA, C.A.', 'id' => 12],
            'APC' => ['name' => 'ALIMENTOS POLAR COMERCIAL, C.A.', 'id' => 13],
            '0702' => ['name' => 'ALIMENTOS POLAR COMERCIAL, C.A.', 'id' => 13],
        ];

        return $mapping[strtoupper($code)] ?? ['name' => 'POLAR', 'id' => 0];
    }

    private function switchToTenant($dbName)
    {
        Config::set('database.connections.tenant.database', $dbName);
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function formatDate($dateStr)
    {
        if (empty($dateStr)) return now()->format('Y-m-d');
        
        $dateStr = trim($dateStr);
        
        // Normalizar todos los separadores (puntos y barras) a guiones
        $normalized = str_replace(['.', '/'], '-', $dateStr);
        
        if (str_contains($normalized, '-')) {
            $parts = explode('-', $normalized);
            if (count($parts) === 3) {
                // Si el año de 4 dígitos está al final (ej: DD-MM-YYYY)
                if (strlen($parts[2]) === 4) {
                    return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                }
                // Si el año de 4 dígitos ya está al inicio (ej: YYYY-MM-DD)
                if (strlen($parts[0]) === 4) {
                    return $normalized;
                }
            }
        }
        
        return $dateStr;
    }
}
