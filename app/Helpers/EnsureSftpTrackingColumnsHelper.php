<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class EnsureSftpTrackingColumnsHelper
{
    /**
     * Asegura que la columna fecha_envio_sftp exista en ventaspxc y seguimiento_cajas_promocion
     * en la conexión activa del tenant.
     */
    public static function ensureColumnsForCurrentTenantConnection(): void
    {
        try {
            if (Schema::connection('tenant')->hasTable('ventaspxc')) {
                if (!Schema::connection('tenant')->hasColumn('ventaspxc', 'fecha_envio_sftp')) {
                    Schema::connection('tenant')->table('ventaspxc', function (Blueprint $table) {
                        $table->dateTime('fecha_envio_sftp')->nullable();
                    });
                }
            }

            if (Schema::connection('tenant')->hasTable('seguimiento_cajas_promocion')) {
                if (!Schema::connection('tenant')->hasColumn('seguimiento_cajas_promocion', 'fecha_envio_sftp')) {
                    Schema::connection('tenant')->table('seguimiento_cajas_promocion', function (Blueprint $table) {
                        $table->dateTime('fecha_envio_sftp')->nullable();
                    });
                }
            }
        } catch (\Throwable $e) {
            // Manejo silencioso si la columna ya existe o hay restricciones DDL
        }
    }
}
