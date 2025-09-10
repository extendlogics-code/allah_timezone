<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__);

// Load environment if Dotenv is available and .env exists
$envPath = $root . '/.env';
if (class_exists('Dotenv\\Dotenv') && file_exists($envPath)) {
    // Use string-based call to avoid static analysis error on undefined type
    $dotenv = call_user_func(['Dotenv\\Dotenv', 'createImmutable'], $root);
    $dotenv->safeLoad();
}

// Default timezone from env, else UTC
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? $_SERVER['APP_TIMEZONE'] ?? 'UTC');
