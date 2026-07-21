<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class ReportRoutesSelector
{
    /**
     * Retorna el nombre de la tabla de rutas asignado al proceso
     * leyendo el valor correspondiente desde las variables de entorno (.env).
     */
    public static function getTableForProcess(string $processName): string
    {
        $envKeys = [
            'sales' => 'SALES_REPORT',
            'obsequios' => 'OBSEQUIOS_REPORT',
            'obsequios_sap' => 'OBSEQUIOS_SAP_REPORT',
            'clientes' => 'CLIENTES_REPORT',
            'customer_consolidated' => 'CUSTOMER_CONSOLIDATED_REPORT',
            'adc' => 'ADC_REPORT',
        ];

        $envKey = $envKeys[$processName] ?? null;
        if ($envKey) {
            return env($envKey, 'company_routes');
        }

        return 'company_routes';
    }

    /**
     * Retorna el nombre del disco configurado para el proceso.
     * Si la tabla asociada es 'company_routes_qa', modifica en caliente el 'root'
     * del disco SFTP para que apunte al directorio 'CAL' y purga el cache del driver filesystem.
     */
    public static function getSftpDiskForProcess(string $processName, string $defaultDisk): string
    {
        $table = self::getTableForProcess($processName);
        if ($table === 'company_routes_qa') {
            $root = config("filesystems.disks.{$defaultDisk}.root");
            // Reemplazar la carpeta de entorno (DEVELOP o PRD) con CAL
            $newRoot = str_replace(['DEVELOP', 'PRD'], 'CAL', $root);
            config(["filesystems.disks.{$defaultDisk}.root" => $newRoot]);
            
            // Forzar a Laravel a recrear el disco con la nueva configuración
            Storage::purge($defaultDisk);
        }
        return $defaultDisk;
    }
}
