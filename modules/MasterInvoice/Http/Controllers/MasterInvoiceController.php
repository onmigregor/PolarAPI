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
                $upsertData[] = [
                    'fq_redi' => isset($item['fq_redi']) ? trim($item['fq_redi']) : null,
                    'fecha_creacion' => isset($item['fecha_creacion']) ? trim($item['fecha_creacion']) : null,
                    'fecha_vencimiento' => isset($item['fecha_vencimiento']) ? trim($item['fecha_vencimiento']) : null,
                    'codigo_polar_negocio' => isset($item['codigo_polar_negocio']) ? trim($item['codigo_polar_negocio']) : null,
                    'no_factura' => isset($item['no_factura']) ? trim($item['no_factura']) : null,
                    'no_control' => isset($item['no_control']) ? trim($item['no_control']) : null,
                    'zona_venta' => isset($item['zona_venta']) ? trim($item['zona_venta']) : null,
                    'material' => isset($item['material']) ? trim($item['material']) : null,
                    'cantidad' => $item['cantidad'] ?? 0,
                    'um' => $item['um'] ?? null,
                    'precio' => $item['precio'] ?? 0,
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
            try {
                $distributor = app(\Modules\MasterInvoice\Actions\DistributeInvoicesToTenantsAction::class);
                $distributor->execute($data);
            } catch (\Exception $e) {
                Log::error("Error distribuyendo a tenants: " . $e->getMessage());
                // No fallamos la respuesta principal si la distribución falla
            }

            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada. ' . count($data) . ' registros procesados en el Hub y distribuidos a los tenants.'
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
