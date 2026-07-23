<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * 1. SINCRONIZACIÓN DE CLIENTES OFICIALES POLAR (08:30 AM)
 * Sincroniza la data maestra de clientes proveniente del origen (productosPolarApi) hacia el HUB
 * y asegura la infraestructura de tablas/columnas en las BDs de los tenants (homologación por cep/IdCliente).
 * Ejecutarlo a las 08:30 AM brinda un margen de 30 minutos antes del inicio de reportes matutinos de las 09:00 AM.
 */
Schedule::command('customers:sync')
    ->dailyAt('08:30')
    ->days([1, 2, 3, 4, 5, 6]);

/**
 * 2. GENERACIÓN DE REPORTES DIARIOS DE VENTAS Y OBSEQUIOS (09:00 AM)
 * Extrae y consolida las transacciones de ventas y productos bonificados (obsequios) desde todas las BDs
 * de los tenants activos para el día anterior. Construye los archivos CSV bajo el estándar Polar y los sube al SFTP.
 */
Schedule::command('report:generate-daily-sales')
    ->dailyAt('09:00')
    ->days([1, 2, 3, 4, 5, 6]);

/**
 * 3. SINCRONIZACIÓN DE CLIENTES DE TENANTS AL HUB (09:00 AM)
 * Recorre todas las BDs de los tenants activos extrayendo los registros de la tabla 'clientes' local
 * para actualizar e indexar centralizadamente la tabla maestra 'master_client_polar' en la base de datos del HUB.
 */
Schedule::command('master-client:sync')
    ->dailyAt('09:00')
    ->days([1, 2, 3, 4, 5, 6]);

/**
 * 4. REPORTE CONSOLIDADO DE CLIENTES NO VINCULADOS / SIN CEP (09:05 AM)
 * Escanea las BDs de los tenants activos buscando clientes que carecen de código CEP oficial de Polar.
 * Genera un archivo comprimido CSV/ZIP con los clientes pendientes y lo deposita en el SFTP para auditoría.
 */
Schedule::command('report:generate-customer-consolidated')
    ->dailyAt('09:05')
    ->days([1, 2, 3, 4, 5, 6]);

/**
 * 5. REPORTE DE SOLICITUDES EP DE CLIENTES (09:10 AM)
 * Consolida las solicitudes de modificación de clientes (Creación de Clientes, Cambio de Estatus y Data Maestra)
 * procesadas en los tenants. Formatea la data en archivos CSV homologados y los transmite al SFTP de producción.
 */
Schedule::command('report:generate-ep-requests')
    ->dailyAt('09:10')
    ->days([1, 2, 3, 4, 5, 6]);
