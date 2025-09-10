<?php
declare(strict_types=1);

$vendor = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendor)) {
    require $vendor;
}

// Ensure our TestCase shim is available for both IDEs and phpunit runtime
require __DIR__ . '/TestCase.php';

