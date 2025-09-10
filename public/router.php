<?php
/*
 How to run (PHP built‑in server)
 -----------------------------------------------------------------
 From the repo root:
   php -S 127.0.0.1:8000 -t public public/router.php

 Or via Composer:
   composer start

 Alternate port (if 8000 is busy):
   php -S 127.0.0.1:8080 -t public public/router.php

 Windows PowerShell example:
   php -S localhost:8000 -t public public\router.php

 Verify it’s working:
   - Open http://localhost:8000
   - Test media route (serves local MP4 if found): http://localhost:8000/media/azan.mp4

 Troubleshooting:
   - Requires PHP 8.1+ (check with: php -v)
   - Run the command from the repo root and ensure docroot (-t) is 'public'
   - If you see 404s, confirm you used this router: public/router.php
   - Apache: enable public/.htaccess; Nginx: point root to public/ and route to index.php
*/
// Development router for PHP built-in server.
// - Serves existing static files from public/
// - Streams local Azan MP4 from known locations via /media/azan.mp4

// If the requested file exists under public/, let the server handle it
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = __DIR__ . $uri;
if ($uri !== '/' && is_file($path)) {
    return false; // serve static
}

// Simple API routing for React client
if (strpos($uri, '/api/times') === 0) {
    require __DIR__ . '/api.php';
    return true;
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
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store');
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
