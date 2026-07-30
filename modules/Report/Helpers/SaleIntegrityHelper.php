<?php

namespace Modules\Report\Helpers;

class SaleIntegrityHelper
{
    /**
     * Determina el estado de envío o error de integridad de una factura de venta.
     *
     * @param object $row Fila de la venta obtenida de la base de datos
     * @return string 'enviado' | 'pendiente' | 'factura_vacia' | 'monto_incorrecto' | 'cliente_inexistente'
     */
    public static function determineStatus(object $row): string
    {
        // 1. Si ya posee fecha de envío, su estado es enviado
        if (!empty($row->fecha_envio_sftp)) {
            return 'enviado';
        }

        // 2. Regla: Factura Vacía (Sin Renglones de detalle)
        if (isset($row->total_detalles) && (int)$row->total_detalles === 0) {
            return 'factura_vacia';
        }

        // 3. Regla: Monto Incorrecto (Ejemplo: Monto total menor o igual a cero)
        if (isset($row->MontoFactura) && (float)$row->MontoFactura <= 0) {
            return 'monto_incorrecto';
        }

        // 4. Regla: Cliente Inexistente (Falta de datos del deudor)
        if (empty($row->nombre_cliente) && empty($row->IdCliente)) {
            return 'cliente_inexistente';
        }

        // Si supera todas las validaciones y no tiene fecha_envio_sftp, está lista para enviarse (Pendiente)
        return 'pendiente';
    }
}
