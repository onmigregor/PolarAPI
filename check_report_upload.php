<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

// Create connection to SFTP using the local docker host mapping
Config::set('filesystems.disks.sftp_root_test', [
    'driver' => 'sftp',
    'host' => 'host.docker.internal',
    'username' => env('SFTP_USERNAME'),
    'password' => env('SFTP_PASSWORD'),
    'port' => (int) env('SFTP_PORT', 50030),
    'root' => '/smartfq',
    'timeout' => 30,
]);
Storage::purge('sftp_root_test');

$disk = Storage::disk('sftp_root_test');

$paths = [
    'CAL/IN/Automatico',
    'DEVELOP/IN/Automatico',
];

echo "=== CHECKING RECENT UPLOADS ON SFTP ===\n\n";

foreach ($paths as $path) {
    echo "Directorio: /smartfq/{$path}\n";
    try {
        $files = $disk->files($path);
        if (empty($files)) {
            echo "  [Vacío]\n";
        } else {
            // Sort files by last modified time descending
            $filesWithTime = [];
            foreach ($files as $file) {
                $filesWithTime[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'time' => $disk->lastModified($file),
                    'size' => $disk->fileSize($file),
                ];
            }
            usort($filesWithTime, fn($a, $b) => $b['time'] - $a['time']);
            
            foreach ($filesWithTime as $f) {
                $dateStr = date('Y-m-d H:i:s', $f['time']);
                echo "  - {$f['name']} ({$f['size']} bytes, modificado: {$dateStr})\n";
            }
        }
    } catch (\Exception $e) {
        echo "  [Error]: " . $e->getMessage() . "\n";
    }
    echo "\n";
}
echo "=== FIN ===\n";
