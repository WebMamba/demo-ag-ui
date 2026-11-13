<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsTool(
    name: 'get_forecast',
    description: <<<'TXT'
Get the forecast wind (knots), gusts (knots), wind direction and wave height at a
position, a number of hours from now (up to 7 days), from Open-Meteo. Use it with
predict_position to know the conditions the boat will meet where it will be: call it
once per predicted position, with that position's hours.
TXT,
)]
final readonly class GetForecast
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param float $latitude  Latitude in decimal degrees, WGS84 (south is negative)
     * @param float $longitude Longitude in decimal degrees, WGS84 (west is negative)
     * @param int   $inHours   Hours from now, 0 to 168
     *
     * @return array<string, mixed>
     */
    public function __invoke(float $latitude, float $longitude, int $inHours): array
    {
        $inHours = max(0, min(168, $inHours));
        $query = ['latitude' => $latitude, 'longitude' => $longitude, 'forecast_hours' => $inHours + 1, 'timezone' => 'GMT'];

        $wind = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => $query + ['hourly' => 'wind_speed_10m,wind_gusts_10m,wind_direction_10m', 'wind_speed_unit' => 'kn'],
            'timeout' => 5,
        ]);
        $marine = $this->httpClient->request('GET', 'https://marine-api.open-meteo.com/v1/marine', [
            'query' => $query + ['hourly' => 'wave_height'],
            'timeout' => 5,
        ]);

        $air = $wind->toArray(false)['hourly'] ?? [];
        $sea = $marine->toArray(false)['hourly'] ?? [];

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'in_hours' => $inHours,
            'time_utc' => $air['time'][$inHours] ?? null,
            'wind_knots' => $air['wind_speed_10m'][$inHours] ?? null,
            'gusts_knots' => $air['wind_gusts_10m'][$inHours] ?? null,
            'wind_from_degrees' => $air['wind_direction_10m'][$inHours] ?? null,
            'wave_height_m' => $sea['wave_height'][$inHours] ?? null,
        ];
    }
}
