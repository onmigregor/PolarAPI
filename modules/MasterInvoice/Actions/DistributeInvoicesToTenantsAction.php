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
        if (empty($data)) return;

        // Agrupar por Zona de Venta para minimizar cambios de conexión
        $groupedByZone = collect($data)->groupBy('zona_venta');

        foreach ($groupedByZone as $zone => $items) {
            $prefix = config('tenants.prefix', 'www_');
            $suffix = config('tenants.suffix', 'p');
            
            // Buscar la base de datos correcta usando FQ/REDI y Zona
            $firstItem = $items->first();
            $redi = $firstItem['fq_redi'] ?? '';
            
            $company = DB::table('company_routes')
                ->where('cep', $redi)
                ->first();

            if (!$company) {
                Log::warning("DistributeInvoicesToTenantsAction: No se encontró tenant para REDI $redi y Zona $zone. Saltando grupo.");
                continue;
            }

            $dbName = $company->db_name;
            
            try {
                // Configurar conexión dinámica
                $this->switchToTenant($dbName);

                // Agrupar por No Factura para crear cabeceras
                $groupedByInvoice = $items->groupBy('no_factura');

                foreach ($groupedByInvoice as $nroFactura => $lines) {
                    $firstLine = $lines->first();
                    $fecha = $this->formatDate($firstLine['fecha_creacion']);
                    $tasa = (float)($firstLine['tasa'] ?? 0);
                    
                    $totalFactura = $lines->sum(fn($l) => ($l['cantidad'] ?? 0) * ($l['precio'] ?? 0));
                    $totalIvaAmount = $lines->sum(fn($l) => ($l['iva'] ?? 0) * ($l['cantidad'] ?? 1));

                    // 1. Insertar Cabecera (compras) - Status PENDIENTE
                    $montoDivisas = $tasa > 0 ? (($totalFactura + $totalIvaAmount) / $tasa) : 0;

                    DB::connection('tenant')->table('compras')->updateOrInsert(
                        ['nrofactura' => $nroFactura],
                        [
                            'idcompra_detalle' => 0,
                            'nrocontrol' => $firstLine['no_control'] ?? '',
                            'fecha' => $fecha,
                            'fechavencimiento' => $fecha,
                            'ruta' => $zone,
                            'proveedor' => $this->getProviderName($firstLine['codigo_polar_negocio']),
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
                            'nrocomprobante' => '',
                            'fecharetencion' => $fecha,
                            'periodofiscal' => '',
                            'c' => 0,
                            'm' => 0,
                            's' => 0,
                            'pcv' => 0,
                            'apc' => 0,
                            'comentario' => '',
                            'tasa' => $tasa,
                            'fecha_tasa' => $fecha,
                            'montodivisas' => $montoDivisas,
                            'totalpagarbs' => $totalFactura + $totalIvaAmount,
                            'enlace' => '',
                            'horaRegistro' => now()->format('Y-m-d H:i:s'),
                            'cep' => $firstLine['fq_redi'] ?? '',
                            'eliminado' => 0,
                        ]
                    );

                    // 1.5 Obtener el idcompra generado (o existente)
                    $compra = DB::connection('tenant')->table('compras')
                        ->where('nrofactura', $nroFactura)
                        ->first();
                        
                    if (!$compra) {
                        Log::error("No se pudo obtener el idcompra para la factura $nroFactura en tenant $dbName");
                        continue;
                    }
                    
                    $idCompraReal = $compra->idcompra;

                    // 2. Insertar Detalles (compras_detalle)
                    foreach ($lines as $line) {
                        $material = $line['material'];
                        
                        // BUSQUEDA DE IDPRODUCTO REAL POR SKU
                        $product = DB::connection('tenant')->table('productos')
                            ->where('codigoSKU', $material)
                            ->first();
                        
                        $idProductoReal = $product ? $product->idproducto : 0;
                        $precioCompra = (float)($line['precio'] ?? 0);
                        $cantidad = (int)($line['cantidad'] ?? 0);

                        DB::connection('tenant')->table('compras_detalle')->updateOrInsert(
                            [
                                'ruta' => $zone,
                                'idcompra' => $idCompraReal, // Match perfecto con el PK de la cabecera
                                'idproducto' => $idProductoReal,
                            ],
                            [
                                'fecha' => $fecha,
                                'producto' => $material,
                                'cantidad' => $cantidad,
                                'preciocompra' => $precioCompra,
                                'precioventa' => $precioCompra,
                                'tasa' => $tasa,
                                'fecha_tasa' => $fecha,
                                'montodivisas' => $cantidad * $precioCompra, // Cantidad * Precio
                                'porcentaje_rentab' => 0,
                                'rentabilidad' => 0,
                                'fideicomiso' => 0,
                                'sync' => 1,
                                'eliminado' => 0,
                            ]
                        );
                    }
                }

                Log::info("DistributeInvoicesToTenantsAction: Procesado tenant $dbName con " . count($items) . " registros.");

            } catch (\Exception $e) {
                Log::error("DistributeInvoicesToTenantsAction Error en tenant $dbName: " . $e->getMessage());
                // Continuamos con el siguiente tenant
            }
        }
    }

    private function getProviderName($code)
    {
        $mapping = [
            'CYM' => 'CERVECERIA POLAR C.A.',
            'PCV' => 'PEPSI-COLA VENEZUELA, C.A.',
            'APC' => 'ALIMENTOS POLAR COMERCIAL, C.A.',
        ];

        return $mapping[strtoupper($code)] ?? 'POLAR';
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
        
        // Manejar formato 17.04.2026
        if (str_contains($dateStr, '.')) {
            $parts = explode('.', $dateStr);
            if (count($parts) === 3) {
                return "{$parts[2]}-{$parts[1]}-{$parts[0]}";
            }
        }
        
        return $dateStr;
    }
}
