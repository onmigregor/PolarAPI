<?php

namespace Modules\CompanyRoute\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class InitializeTenantDatabaseAction
{
    private const CREATE_CLIENTES_TABLE_SQL = "
        CREATE TABLE IF NOT EXISTS `clientes` (
          `IdCliente` bigint(20) NOT NULL AUTO_INCREMENT,
          `cep` varchar(20) DEFAULT NULL,
          `Cliente` varchar(255) NOT NULL,
          `RIF` varchar(50) NOT NULL,
          `PIN` varchar(100) NOT NULL,
          `Direccion` text NOT NULL,
          `email` varchar(30) NOT NULL,
          `instagram` varchar(30) NOT NULL,
          `DiaDespacho1` varchar(255) NOT NULL,
          `DiaDespacho2` varchar(255) NOT NULL,
          `DiaDespacho3` varchar(255) NOT NULL,
          `FormaPago` varchar(20) NOT NULL,
          `diasCredito` int(11) NOT NULL,
          `MontoCredito` int(11) NOT NULL,
          `Activo` tinyint(4) NOT NULL,
          `PorcentajeAcuerdoComercial` int(11) NOT NULL,
          `tipoclienteplantactico` varchar(20) NOT NULL,
          `AgenteRetencion` tinyint(4) NOT NULL,
          `PorcentajeRetencion` int(11) NOT NULL,
          `prontopago` int(11) NOT NULL,
          `TipoCliente` varchar(100) NOT NULL,
          `idgrupo` int(11) NOT NULL,
          `PersonaContacto` varchar(100) NOT NULL,
          `TelefonoContacto` varchar(100) NOT NULL,
          `Ruta` varchar(50) NOT NULL,
          `categoria` varchar(3) NOT NULL,
          `licencialicor` varchar(30) NOT NULL,
          `nota` text NOT NULL,
          `segmento` varchar(30) NOT NULL,
          `ADC_CP` int(11) NOT NULL,
          `ADC_PCV` int(11) NOT NULL,
          `PCV" . "` int(11) NOT NULL,
          `APC` int(11) NOT NULL,
          `CP` int(11) NOT NULL,
          `status` varchar(20) NOT NULL,
          `prioridad` int(11) NOT NULL,
          `perfilUsuario` varchar(10) NOT NULL,
          `perfilUsuarioApp` varchar(10) NOT NULL,
          `vendedor` varchar(100) NOT NULL,
          `promoAPC` int(11) NOT NULL DEFAULT 0,
          `promoCP` int(11) NOT NULL DEFAULT 0,
          `promoPCV` int(11) NOT NULL DEFAULT 0,
          `Descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
          `latitud` varchar(20) NOT NULL,
          `longitud` varchar(20) NOT NULL,
          `imagen_negocio` varchar(50) NOT NULL,
          `ubicacion_imagen_negocio` varchar(150) NOT NULL,
          `requiere_pasos_visita` int(11) NOT NULL DEFAULT 0,
          PRIMARY KEY (`IdCliente`),
          UNIQUE KEY `idx_cep` (`cep`),
          KEY `idx_ruta` (`Ruta`),
          KEY `idx_vendedor` (`vendedor`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    public function execute(string $dbName): bool
    {
        try {
            // 1. Asegurar que la base de datos física existe
            $dbExists = DB::connection('mysql')->select("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);

            if (empty($dbExists)) {
                Log::info("InitializeTenantDatabaseAction: Creating database `{$dbName}`");
                DB::connection('mysql')->statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // 2. Configurar conexión dinámica para este tenant
            Config::set('database.connections.tenant.database', $dbName);
            DB::purge('tenant');

            // 3. Crear tablas necesarias si no existen
            // Tabla clientes
            $tableExists = DB::connection('tenant')->select("SHOW TABLES LIKE 'clientes'");
            if (empty($tableExists)) {
                Log::info("InitializeTenantDatabaseAction: Creating 'clientes' table in `{$dbName}`");
                DB::connection('tenant')->statement(self::CREATE_CLIENTES_TABLE_SQL);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("InitializeTenantDatabaseAction Error for {$dbName}: " . $e->getMessage());
            return false;
        }
    }
}
