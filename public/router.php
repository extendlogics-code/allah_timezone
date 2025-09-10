<?php
// Development router for PHP built-in server.
// - Serves existing static files from public/
// - Streams local Azan MP4 from known locations via /media/azan.mp4

// If the requested file exists under public/, let the server handle it
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = __DIR__ . $uri;
if ($uri !== '/' && is_file($path)) {
    return false; // serve static
}

// Special media route for local MP4 outside/inside public
if ($uri === '/media/azan.mp4') {
    $root = dirname(__DIR__);
    $candidates = [
        __DIR__ . '/AzanSong.mp4',
        __DIR__ . '/Azan 7 times.mp4',
        $root . '/Azan 7 times.mp4',
        $root . '/AzanSong.mp4',
    ];
    $file = null;
    foreach ($candidates as $c) {
        if (is_readable($c)) { $file = $c; break; }
    }
    if ($file) {
        header('Content-Type: video/mp4');
        header('Content-Disposition: inline; filename="azan.mp4"');
        header('Accept-Ranges: bytes');
        // Simple file output; for large files consider range handling
        readfile($file);
        return true;
    }
    http_response_code(404);
    echo 'Azan MP4 not found';
    return true;
}

// Fallback to the app front controller
require __DIR__ . '/index.php';

