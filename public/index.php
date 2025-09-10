<?php
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

// Serve local MP4 via rewritten route when using Apache/.htaccess
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($uri === '/media/azan.mp4') {
    $root = dirname(__DIR__);
    $candidates = [
        __DIR__ . '/AzanSong.mp4',
        __DIR__ . '/Azan 7 times.mp4',
        $root . '/Azan 7 times.mp4',
        $root . '/AzanSong.mp4',
    ];
    $file = null;
    foreach ($candidates as $c) { if (is_readable($c)) { $file = $c; break; } }
    if ($file) {
        header('Content-Type: video/mp4');
        header('Content-Disposition: inline; filename="azan.mp4"');
        header('Accept-Ranges: bytes');
        readfile($file);
        return;
    }
    http_response_code(404);
    echo 'Azan MP4 not found';
    return;
}

require __DIR__ . '/../bootstrap/app.php';

use App\App;

$app = new App();
$response = $app->handle($_SERVER);

http_response_code($response['status']);
foreach ($response['headers'] as $name => $value) {
    header($name . ': ' . $value);
}
echo $response['body'];
