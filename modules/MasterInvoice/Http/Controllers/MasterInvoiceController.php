<?php

namespace Modules\MasterInvoice\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MasterInvoice\Models\MasterInvoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MasterInvoiceController extends Controller
{
    /**
     * Recibe el payload masivo desde el Admin y lo inserta/actualiza en la base de datos local.
     */
    public function syncFromAdmin(Request $request)
    {
        Log::info("MasterInvoiceController: syncFromAdmin hit.");
        try {
            $data = $request->input('data', []);

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibieron datos para sincronizar.'
                ], 400);
            }

            Log::info("MasterInvoiceController: Recibidas " . count($data) . " facturas para sincronizar.");

            DB::beginTransaction();

            $upsertData = [];
            $batchSize = 500;
            $now = now();

            foreach ($data as $item) {
                $materialRaw = isset($item['material']) ? trim($item['material']) : null;
                $materialClean = $materialRaw !== null ? ltrim($materialRaw, '0') : null;
                if (empty($materialClean) && !empty($materialRaw)) {
                    $materialClean = $materialRaw; // Preservar si era '0'
                }

                $cantidad = (float)($item['cantidad'] ?? 0);
                $precio = (float)($item['precio'] ?? 0);

                // Omitir renglones basura sin material o con cantidad <= 0 y precio <= 0
                if (empty($materialClean) || ($cantidad <= 0 && $precio <= 0)) {
                    Log::warning("MasterInvoiceController: Omitiendo renglón inválido o en cero para factura " . ($item['no_factura'] ?? 'N/A') . " Material: $materialRaw");
                    continue;
                }

                $upsertData[] = [
                    'fq_redi' => isset($item['fq_redi']) ? trim($item['fq_redi']) : null,
                    'fecha_creacion' => isset($item['fecha_creacion']) ? trim($item['fecha_creacion']) : null,
                    'fecha_vencimiento' => isset($item['fecha_vencimiento']) ? trim($item['fecha_vencimiento']) : null,
                    'codigo_polar_negocio' => isset($item['codigo_polar_negocio']) ? (trim($item['codigo_polar_negocio']) === '702' ? '0702' : trim($item['codigo_polar_negocio'])) : null,
                    'no_factura' => isset($item['no_factura']) ? trim($item['no_factura']) : null,
                    'no_control' => isset($item['no_control']) ? trim($item['no_control']) : null,
                    'zona_venta' => isset($item['zona_venta']) ? trim($item['zona_venta']) : null,
                    'material' => $materialClean,
                    'cantidad' => $cantidad,
                    'um' => $item['um'] ?? null,
                    'precio' => $precio,
                    'iva' => $item['iva'] ?? 0,
                    'descuento' => $item['descuento'] ?? 0,
                    'otro_margen' => $item['otro_margen'] ?? 0,
                    'envases' => $item['envases'] ?? 0,
                    'lisaea_unidad' => $item['lisaea_unidad'] ?? 0,
                    'tasa' => $item['tasa'] ?? 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($upsertData) >= $batchSize) {
                    MasterInvoice::upsert(
                        $upsertData,
                        ['no_factura', 'material'],
                        [
                            'fq_redi', 'fecha_creacion', 'fecha_vencimiento', 'codigo_polar_negocio', 'no_control', 'zona_venta',
                            'cantidad', 'um', 'precio', 'iva', 'descuento', 'otro_margen', 'envases', 'lisaea_unidad', 'tasa', 'updated_at'
                        ]
                    );
                    $upsertData = [];
                }
            }

            if (count($upsertData) > 0) {
                MasterInvoice::upsert(
                    $upsertData,
                    ['no_factura', 'material'],
                    [
                        'fq_redi', 'fecha_creacion', 'fecha_vencimiento', 'codigo_polar_negocio', 'no_control', 'zona_venta',
                        'cantidad', 'um', 'precio', 'iva', 'descuento', 'otro_margen', 'envases', 'lisaea_unidad', 'tasa', 'updated_at'
                    ]
                );
            }

            DB::commit();

            // 3. Distribuir a los tenants (compras y compras_detalle)
            $distResult = [
                'success' => true,
                'tenants_processed' => 0,
                'errors' => []
            ];

            try {
                $distributor = app(\Modules\MasterInvoice\Actions\DistributeInvoicesToTenantsAction::class);
                $distResult = $distributor->execute($data);
            } catch (\Exception $e) {
                Log::error("Error distribuyendo a tenants: " . $e->getMessage());
                $distResult['success'] = false;
                $distResult['errors']['general'] = $e->getMessage();
            }

            if (!$distResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al distribuir las facturas a los tenants: ' . implode(', ', $distResult['errors']),
                    'data' => $distResult
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada exitosamente en el Hub.',
                'data' => $distResult
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("MasterInvoiceController Exception: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error interno en el Hub: ' . $e->getMessage()
            ], 500);
        }
    }
}
