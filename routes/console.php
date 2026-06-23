<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generar reportes de ventas y obsequios a las 09:00 AM, de lunes (1) a sábado (6)
Schedule::command('report:generate-daily-sales')->dailyAt('09:00')->days([1, 2, 3, 4, 5, 6]);
