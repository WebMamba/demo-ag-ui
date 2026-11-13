<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Ai\Tool\GetForecast;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GetForecastTest extends KernelTestCase
{
    public function testItReturnsTheForecastAtThatHour(): void
    {
        self::bootKernel();

        $requested = [];
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(static function (string $method, string $url) use (&$requested): JsonMockResponse {
            $requested[] = $url;

            return str_contains($url, 'marine-api')
                ? new JsonMockResponse(['hourly' => ['wave_height' => [1.8, 2.0, 2.4]]])
                : new JsonMockResponse(['hourly' => [
                    'time' => ['2026-11-04T18:00', '2026-11-04T19:00', '2026-11-04T20:00'],
                    'wind_speed_10m' => [15.0, 17.0, 21.0],
                    'wind_gusts_10m' => [19.0, 22.0, 28.0],
                    'wind_direction_10m' => [110, 105, 100],
                ]]);
        }));

        $forecast = self::getContainer()->get(GetForecast::class)(-6.8, -114.5, 2);

        self::assertSame('2026-11-04T20:00', $forecast['time_utc']);
        self::assertSame(21.0, $forecast['wind_knots']);
        self::assertSame(28.0, $forecast['gusts_knots']);
        self::assertSame(100, $forecast['wind_from_degrees']);
        self::assertSame(2.4, $forecast['wave_height_m']);
        self::assertCount(2, $requested);
        self::assertStringContainsString('forecast_hours=3', $requested[0]);
        self::assertStringContainsString('wind_speed_unit=kn', $requested[0]);
    }
}
