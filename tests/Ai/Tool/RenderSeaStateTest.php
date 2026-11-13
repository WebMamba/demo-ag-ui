<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Tests\Support\NavigatorToolbox;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RenderSeaStateTest extends KernelTestCase
{
    use NavigatorToolbox;

    public function testItRendersLiveWindWavesAndSwellForAPosition(): void
    {
        self::bootKernel();

        $requested = [];
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(static function (string $method, string $url) use (&$requested): JsonMockResponse {
            $requested[] = $url;

            return str_contains($url, 'marine-api')
                ? new JsonMockResponse(['current' => ['wave_height' => 2.1, 'wave_direction' => 120, 'wave_period' => 8, 'swell_wave_height' => 1.6, 'swell_wave_direction' => 190, 'swell_wave_period' => 12, 'sea_surface_temperature' => 27.4]])
                : new JsonMockResponse(['current' => ['wind_speed_10m' => 18.0, 'wind_direction_10m' => 110, 'wind_gusts_10m' => 24.0]]);
        }));

        $rendering = self::navigatorToolbox()
            ->execute(new ToolCall('call_1', 'render_sea_state', ['latitude' => -9.8, 'longitude' => -139.03, 'placeName' => 'Off Hiva Oa']))
            ->getResult();
        $html = $rendering->content['html'];

        self::assertStringContainsString('Off Hiva Oa', $html);
        self::assertStringContainsString('18 kn', $html);
        self::assertStringContainsString('F5', $html, '18 knots is a force 5.');
        self::assertStringContainsString('2.1 m', $html);
        self::assertStringContainsString('ESE', $html);
        self::assertSame('Displayed the Agent:SeaState component to the user.', (string) $rendering);
        self::assertCount(2, $requested);
        self::assertStringContainsString('latitude=-9.8', $requested[0]);
    }
}
