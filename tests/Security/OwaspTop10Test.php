<?php
declare(strict_types=1);

use App\App;

/**
 * OWASP Top 10 inspired checks for this app’s surface area.
 */
final class OwaspTop10Test extends TestCase
{
    public function testReflectedXssIsEscapedInPage(): void
    {
        $_GET = ['country' => '<script>alert(1)</script>'];
        $app = new App();
        $res = $app->handle([]);
        $this->assertSame(200, $res['status']);
        // Raw script must not appear
        $this->assertSame(false, strpos($res['body'], '<script>alert(1)</script>') !== false);
        // Escaped content should appear
        $this->assertSame(true, strpos($res['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') !== false);
    }

    public function testSecurityHeadersPresentInPage(): void
    {
        $_GET = [];
        $app = new App();
        $res = $app->handle([]);
        $this->assertSame(200, $res['status']);
        $this->assertSame('nosniff', $res['headers']['X-Content-Type-Options'] ?? null);
        $this->assertSame('no-referrer', $res['headers']['Referrer-Policy'] ?? null);
        $this->assertSame('SAMEORIGIN', $res['headers']['X-Frame-Options'] ?? null);
    }

    public function testMediaRouteDoesNotAllowTraversal(): void
    {
        // Simulate traversal attempt; index.php should not serve arbitrary files
        $_SERVER['REQUEST_URI'] = '/media/../../../etc/passwd';
        ob_start();
        include __DIR__ . '/../../public/index.php';
        $out = ob_get_clean();
        // Expect HTML page, not file contents
        $this->assertSame(true, strpos($out, '<!DOCTYPE html>') !== false);
    }

    public function testApiRespondsWithJsonAndSecurityHeaders(): void
    {
        // Clear headers then include API
        if (function_exists('header_remove')) { @header_remove(); }
        $_GET = [];
        ob_start();
        include __DIR__ . '/../../public/api.php';
        $json = ob_get_clean();
        $data = json_decode($json, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
        if (function_exists('headers_list')) {
            $h = implode("\n", headers_list());
            $this->assertSame(true, strpos($h, 'Content-Type: application/json') !== false);
            $this->assertSame(true, strpos($h, 'X-Content-Type-Options: nosniff') !== false);
            $this->assertSame(true, strpos($h, 'Referrer-Policy: no-referrer') !== false);
        }
        // Minimal payload expectations
        $this->assertSame(true, !empty($data['countryList'] ?? []));
        $this->assertSame(true, is_array($data['scheduleTimes'] ?? []));
    }
}

