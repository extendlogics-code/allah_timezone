<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\App;

final class HomeTest extends TestCase
{
    public function testHandleOutputsPage(): void
    {
        $app = new App();
        $res = $app->handle([]);
        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('Allah Timezone', $res['body']);
    }
}
