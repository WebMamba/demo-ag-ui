<?php

declare(strict_types=1);

namespace App\Twig\Components\Agent;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use AgUi\Bundle\Attribute\AgentIgnore;
use AgUi\Bundle\Attribute\AsAgentComponent;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * Live sea state at a position: waves, swell and wind, from Open-Meteo's free
 * marine and forecast APIs. Fetched while the tool runs — which is why the
 * activity shows a placeholder first.
 */
#[AsTwigComponent]
#[AsAgentComponent(
    description: <<<'TXT'
Render a live sea-state card for a position: wind (knots, Beaufort), waves, swell and
sea temperature. The component fetches current conditions itself. Works anywhere at
sea or near a coast: pass coordinates, not a city name.
TXT,
    tool: 'render_sea_state',
)]
final class SeaState
{
    /** Human-readable name of the position (e.g. "Off Hiva Oa"). */
    public string $placeName = '';

    /** Latitude in decimal degrees, WGS84 (south is negative). */
    public float $latitude;

    /** Longitude in decimal degrees, WGS84 (west is negative). */
    public float $longitude;

    #[AgentIgnore]
    public ?float $waveHeight = null;

    #[AgentIgnore]
    public ?float $wavePeriod = null;

    #[AgentIgnore]
    public ?int $waveDirection = null;

    #[AgentIgnore]
    public ?float $swellHeight = null;

    #[AgentIgnore]
    public ?float $swellPeriod = null;

    #[AgentIgnore]
    public ?int $swellDirection = null;

    #[AgentIgnore]
    public ?float $seaTemperature = null;

    #[AgentIgnore]
    public ?float $windSpeed = null;

    #[AgentIgnore]
    public ?float $windGusts = null;

    #[AgentIgnore]
    public ?int $windDirection = null;

    #[AgentIgnore]
    public ?string $error = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[PostMount]
    public function fetchSeaState(): void
    {
        try {
            $position = ['latitude' => $this->latitude, 'longitude' => $this->longitude];

            $marine = $this->httpClient->request('GET', 'https://marine-api.open-meteo.com/v1/marine', [
                'query' => $position + ['current' => 'wave_height,wave_direction,wave_period,swell_wave_height,swell_wave_direction,swell_wave_period,sea_surface_temperature'],
                'timeout' => 5,
            ]);
            $wind = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => $position + ['current' => 'wind_speed_10m,wind_direction_10m,wind_gusts_10m', 'wind_speed_unit' => 'kn'],
                'timeout' => 5,
            ]);

            $sea = $marine->toArray(false)['current'] ?? [];
            $air = $wind->toArray(false)['current'] ?? [];

            if ([] === $sea && [] === $air) {
                $this->error = 'No marine data for this position.';

                return;
            }

            $this->waveHeight = self::float($sea['wave_height'] ?? null);
            $this->wavePeriod = self::float($sea['wave_period'] ?? null);
            $this->waveDirection = self::int($sea['wave_direction'] ?? null);
            $this->swellHeight = self::float($sea['swell_wave_height'] ?? null);
            $this->swellPeriod = self::float($sea['swell_wave_period'] ?? null);
            $this->swellDirection = self::int($sea['swell_wave_direction'] ?? null);
            $this->seaTemperature = self::float($sea['sea_surface_temperature'] ?? null);
            $this->windSpeed = self::float($air['wind_speed_10m'] ?? null);
            $this->windGusts = self::float($air['wind_gusts_10m'] ?? null);
            $this->windDirection = self::int($air['wind_direction_10m'] ?? null);
        } catch (\Throwable $e) {
            $this->logger->warning('SeaState fetch failed: '.$e->getMessage(), ['exception' => $e]);
            $this->error = 'Could not fetch the sea state.';
        }
    }

    /** 0–12 on the Beaufort scale, from the wind speed in knots. */
    public function getBeaufort(): ?int
    {
        if (null === $this->windSpeed) {
            return null;
        }

        foreach ([1, 3, 6, 10, 16, 21, 27, 33, 40, 47, 55, 63] as $force => $limit) {
            if ($this->windSpeed < $limit) {
                return $force;
            }
        }

        return 12;
    }

    /** "NE", "SSW"… — directions are where the wind and swell come *from*. */
    public function compass(?int $degrees): string
    {
        if (null === $degrees) {
            return '—';
        }

        $points = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];

        return $points[(int) round($degrees / 22.5) % 16];
    }

    /** A one-line, skipper's verdict. */
    public function getVerdict(): string
    {
        $wind = $this->windSpeed ?? 0;
        $waves = $this->waveHeight ?? 0;

        return match (true) {
            $wind >= 30 || $waves >= 4 => 'Heavy weather — reef early, or stay in port.',
            $wind >= 20 || $waves >= 2.5 => 'Lively sailing — second reef ready.',
            $wind >= 10 => 'Good sailing breeze.',
            default => 'Light airs — patience, or the engine.',
        };
    }

    private static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
