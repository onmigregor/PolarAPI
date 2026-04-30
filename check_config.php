<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "DB_NAME: " . config('database.connections.productos_polar.database') . "\n";
echo "DB_HOST: " . config('database.connections.productos_polar.host') . "\n";
