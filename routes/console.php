<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generar reportes de ventas y obsequios a las 08:00 AM UTC (04:00 AM Venezuela), de lunes (1) a sábado (6)
Schedule::command('report:generate-daily-sales')->dailyAt('08:00')->days([1, 2, 3, 4, 5, 6]);

// Sincronizar clientes de distribuidoras a la tabla maestra a las 08:00 AM UTC, de lunes (1) a sábado (6)
Schedule::command('master-client:sync')->dailyAt('08:00')->days([1, 2, 3, 4, 5, 6]);

// Generar reporte consolidado de clientes sin CEP a las 08:05 AM UTC, de lunes (1) a sábado (6)
Schedule::command('report:generate-customer-consolidated')->dailyAt('08:05')->days([1, 2, 3, 4, 5, 6]);

// Generar reportes de solicitudes EP (Nuevos, Actualización, Cambio Estatus) a las 08:10 AM UTC, de lunes (1) a sábado (6)
Schedule::command('report:generate-ep-requests')->dailyAt('08:10')->days([1, 2, 3, 4, 5, 6]);
