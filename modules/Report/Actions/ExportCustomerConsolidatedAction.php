<?php

namespace Modules\Report\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\CompanyRoute\Models\CompanyRoute;

class ExportCustomerConsolidatedAction
{
    /**
     * Ejecuta la consolidación de clientes de todos los tenants activos.
     */
    public function execute(string $table = 'company_routes'): array
    {
        $tenants = CompanyRoute::from($table)->where('is_active', 1)->get();
        $allData = [];

        Log::info("Iniciando consolidación de CLIENTES desde " . $tenants->count() . " tenants.");

        foreach ($tenants as $tenant) {
            try {
                $dbName = $tenant->db_name;
                
                // Verificar si la base de datos existe
                $dbExists = DB::connection('mysql')->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
                if (empty($dbExists)) {
                    Log::warning("Tenant DB {$dbName} no existe físicamente. Saltando...");
                    continue;
                }

                // Configurar conexión dinámica
                config(['database.connections.tenant_report_cust' => array_merge(
                    config('database.connections.mysql'),
                    ['database' => $dbName]
                )]);
                DB::purge('tenant_report_cust');
                $tenantConn = DB::connection('tenant_report_cust');

                // Verificar si la tabla clientes existe
                $tableExists = !empty($tenantConn->select("SHOW TABLES LIKE 'clientes'"));
                if (!$tableExists) {
                    Log::warning("Tabla 'clientes' no existe en {$dbName}. Saltando...");
                    continue;
                }

                // Verificar columnas existentes para evitar errores SQL
                $columns = array_column($tenantConn->select("SHOW COLUMNS FROM clientes"), 'Field');
                
                // Definir mapeo con fallbacks
                $selectFields = [
                    DB::raw("'{$tenant->cep}' as fq_redi"),
                    'cep as codigo_cliente',
                    'Cliente as nombre',
                    'RIF as rif',
                    in_array('TipoCliente', $columns) ? 'TipoCliente as tipo_cliente' : (in_array('tp1_code', $columns) ? 'tp1_code as tipo_cliente' : DB::raw("'' as tipo_cliente")),
                    'Direccion as direccion',
                    'Ruta as ruta',
                    'TelefonoContacto as telefono',
                    'email',
                    'PersonaContacto as contacto',
                    'latitud',
                    'longitud',
                    in_array('con_code', $columns) ? 'con_code as condicion_pago' : DB::raw("'' as condicion_pago"),
                    in_array('prc_code_for_sale', $columns) ? 'prc_code_for_sale as lista_precios' : DB::raw("'' as lista_precios"),
                    in_array('brc_code', $columns) ? 'brc_code as sucursal' : DB::raw("'' as sucursal"),
                    'status as estado',
                    in_array('motivo_no_cep', $columns) ? 'motivo_no_cep' : DB::raw("'' as motivo_no_cep")
                ];

                $data = $tenantConn->table('clientes')
                    ->select($selectFields)
                    ->where(function ($query) {
                        $query->whereNull('cep')
                            ->orWhere('cep', '');
                    })
                    ->get();

                foreach ($data as $row) {
                    $allData[] = (array)$row;
                }

            } catch (\Exception $e) {
                Log::error("Error extrayendo CLIENTES del tenant {$tenant->db_name}: " . $e->getMessage());
                continue;
            }
        }

        Log::info("Consolidación de clientes finalizada. Total registros: " . count($allData));
        return $allData;
    }

    /**
     * Consolidates customers, formats as CSV, zips it, and uploads to SFTP or saves locally.
     */
    public function executeAndUpload(string $table = 'company_routes', string $disk = 'sftp_obsequios'): array
    {
        $rows = $this->execute($table);
        
        $headers = [
            'FQ/REDI',
            'Codigo Cliente',
            'Nombre',
            'RIF',
            'Tipo Cliente',
            'Direccion',
            'Ruta',
            'Telefono',
            'Email',
            'Contacto',
            'Latitud',
            'Longitud',
            'Condicion Pago',
            'Lista Precios',
            'Sucursal',
            'Estado',
            'Motivo no CEP'
        ];

        $filename = "CLIENTES_CONSOLIDADO_" . now()->format('Ymd_His') . ".txt";
        
        $csvContent = implode(';', $headers) . "\r\n";
        foreach ($rows as $row) {
            $csvContent .= implode(';', [
                $row['fq_redi'] ?? '',
                $row['codigo_cliente'] ?? '',
                $row['nombre'] ?? '',
                $row['rif'] ?? '',
                $row['tipo_cliente'] ?? '',
                $row['direccion'] ?? '',
                $row['ruta'] ?? '',
                $row['telefono'] ?? '',
                $row['email'] ?? '',
                $row['contacto'] ?? '',
                $row['latitud'] ?? '',
                $row['longitud'] ?? '',
                $row['condicion_pago'] ?? '',
                $row['lista_precios'] ?? '',
                $row['sucursal'] ?? '',
                $row['estado'] ?? '',
                $row['motivo_no_cep'] ?? '',
            ]) . "\r\n";
        }

        $isLocal = config('app.env') === 'local';
        $destination = $isLocal ? 'Local Storage' : 'SFTP';

        if ($isLocal) {
            if (!file_exists(storage_path('ftp'))) {
                mkdir(storage_path('ftp'), 0777, true);
            }
            file_put_contents(storage_path("ftp/{$filename}"), $csvContent);
            $finalFilename = $filename;
        } else {
            $zipFilename = str_replace('.txt', '.zip', $filename);
            $zipContent = $this->createZipContent($filename, $csvContent);
            Storage::disk($disk)->put($zipFilename, $zipContent);
            $finalFilename = $zipFilename;
        }

        return [
            'filename' => $finalFilename,
            'count' => count($rows),
            'destination' => $destination
        ];
    }

    /**
     * Creates a ZIP file in memory.
     */
    private function createZipContent(string $filenameInZip, string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive();
        
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFromString($filenameInZip, $content);
            $zip->close();
            
            $zipContent = file_get_contents($tempFile);
            unlink($tempFile);
            return $zipContent;
        }

        throw new \Exception("No se pudo crear el archivo ZIP temporal.");
    }
}
