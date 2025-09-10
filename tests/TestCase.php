<?php
declare(strict_types=1);

// Local TestCase shim for IDEs and for running tests without Composer.
// - If PHPUnit is installed, provides a global TestCase that extends it.
// - Otherwise defines a minimal TestCase with no-op assertion methods so
//   static analyzers like Intelephense can resolve types.

if (class_exists(\PHPUnit\Framework\TestCase::class)) {
    class TestCase extends \PHPUnit\Framework\TestCase {}
} else {
    class TestCase
    {
        protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void {}
        protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void {}
    }
}

