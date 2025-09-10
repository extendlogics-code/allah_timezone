<?php
declare(strict_types=1);

// Lightweight API for React client
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) require $autoload;
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../src/Times.php';

use App\Times;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$dataDir = $root . '/data';

$csvFiles = [
    $dataDir . '/india_prayer_times_2025.csv',
    $dataDir . '/uae_prayer_times_2025.csv',
    $root    . '/india_prayer_times_2025.csv',
    $root    . '/uae_prayer_times_2025.csv',
];

$tz = $_ENV['APP_TIMEZONE'] ?? 'Asia/Kolkata';
$today = new DateTimeImmutable('today', new DateTimeZone($tz));
$date = $_GET['date'] ?? $today->format('Y-m-d');
$country = trim($_GET['country'] ?? '');
$state   = trim($_GET['state'] ?? '');
$city    = trim($_GET['city'] ?? '');

$payload = Times::load($csvFiles, $date, $country, $state, $city);
echo json_encode($payload, JSON_UNESCAPED_SLASHES);

