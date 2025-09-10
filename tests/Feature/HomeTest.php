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
    public function testHomePageLoadsWithTitle(): void
    {
        $_GET = [];
        $app = new App();
        $res = $app->handle([]);
        // @intelephense-ignore
        $this->assertSame(200, $res['status']);
        // @intelephense-ignore
        $this->assertStringContainsString('Allah Timezone', $res['body']);
    }

    public function testShowsStatesForDefaultCountryIndia(): void
    {
        $_GET = [];
        $app = new App();
        $res = $app->handle([]);
        // Should render the state select and include Andhra Pradesh from CSV
        // @intelephense-ignore
        $this->assertStringContainsString('<select name="state"', $res['body']);
        // @intelephense-ignore
        $this->assertStringContainsString('Andhra Pradesh', $res['body']);
    }

    public function testHidesCityDropdownWhenCsvHasNoCity(): void
    {
        $_GET = [];
        $app = new App();
        $res = $app->handle([]);
        // Should not render a city select when CSV lacks city column
        $hasCity = strpos($res['body'], 'name="city"') !== false;
        // @intelephense-ignore
        $this->assertSame(false, $hasCity);
    }

    public function testInvalidSelectionShowsNoScheduleMessage(): void
    {
        $_GET = ['country' => 'ZZZ-NOT-EXIST'];
        $app = new App();
        $res = $app->handle([]);
        // @intelephense-ignore
        $this->assertSame(200, $res['status']);
        // Uses a in-message substring to avoid apostrophe issues
        // @intelephense-ignore
        $this->assertStringContainsString('find times for your selection', $res['body']);
    }

    public function testLocalVideoAutodetectedConstant(): void
    {
        $_GET = [];
        $app = new App();
        $res = $app->handle([]);
        // If local MP4 present in repo root, the page script exposes these constants
        // @intelephense-ignore
        $this->assertStringContainsString('const LOCAL_URL = "/media/azan.mp4"', $res['body']);
        // @intelephense-ignore
        $this->assertStringContainsString('const HAS_LOCAL = true', $res['body']);
    }

    public function testApiTimesReturnsJson(): void
    {
        $_GET = [];
        $api = __DIR__ . '/../../public/api.php';
        // @intelephense-ignore
        $this->assertSame(true, is_file($api), 'API file missing');
        ob_start();
        include $api;
        $json = ob_get_clean();
        $data = json_decode($json, true);
        // @intelephense-ignore
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Invalid JSON from /api/times');
        // @intelephense-ignore
        $this->assertStringContainsString('India', implode(',', (array)($data['countryList'] ?? [])));
        // @intelephense-ignore
        $this->assertStringContainsString('UAE', implode(',', (array)($data['countryList'] ?? [])));
    }

    public function testApiRespectsSelectionUaeAbuDhabi(): void
    {
        $_GET = ['country' => 'UAE', 'state' => 'Abu Dhabi'];
        $api = __DIR__ . '/../../public/api.php';
        ob_start();
        include $api;
        $json = ob_get_clean();
        $data = json_decode($json, true);
        // @intelephense-ignore
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'Invalid JSON from /api/times with selection');
        // Selection is normalized in response
        // @intelephense-ignore
        $this->assertSame('UAE', $data['selection']['country'] ?? null);
        // @intelephense-ignore
        $this->assertSame('Abu Dhabi', $data['selection']['state'] ?? null);
        // Should have some times for that selection
        // @intelephense-ignore
        $this->assertSame(true, !empty($data['scheduleTimes']));
    }
}

