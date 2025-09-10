<?php
declare(strict_types=1);

use App\App;
use PHPUnit\Framework\TestCase;

/**
 * @method void assertSame(mixed $expected, mixed $actual, string $message = '')
 * @method void assertStringContainsString(string $needle, string $haystack, string $message = '')
 */
final class HomeTest extends TestCase
{
    public function testHandleOutputsPage(): void
    {
        $app = new App();
        $res = $app->handle([]);
        // @intelephense-ignore
        $this->assertSame(200, $res['status']);
        // @intelephense-ignore
        $this->assertStringContainsString('Allah Timezone', $res['body']);
    }
}
