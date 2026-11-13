<?php

declare(strict_types=1);

namespace App\Ai\Passage;

use Symfony\UX\Map\Distance\DistanceCalculator;
use Symfony\UX\Map\Distance\DistanceUnit;
use Symfony\UX\Map\Distance\HaversineDistanceCalculator;
use Symfony\UX\Map\Point;

/**
 * The legs between consecutive waypoints: great-circle distance in nautical miles,
 * and days at sea at the boat's average speed.
 */
final class Legs
{
    /** A cruising sailboat's honest average, day and night. */
    public const AVERAGE_SPEED_KNOTS = 6.0;

    /**
     * @param list<array{name: string, lat: float, lng: float, note?: string}> $waypoints
     *
     * @return list<array{from: string, to: string, nm: int, days: float}>
     */
    public static function between(array $waypoints): array
    {
        $calculator = new DistanceCalculator(new HaversineDistanceCalculator(), DistanceUnit::NauticalMile);
        $legs = [];

        for ($i = 1, $n = \count($waypoints); $i < $n; ++$i) {
            $from = $waypoints[$i - 1];
            $to = $waypoints[$i];
            $nm = $calculator->calculateDistance(new Point((float) $from['lat'], (float) $from['lng']), new Point((float) $to['lat'], (float) $to['lng']));

            $legs[] = [
                'from' => $from['name'],
                'to' => $to['name'],
                'nm' => (int) round($nm),
                'days' => round($nm / self::AVERAGE_SPEED_KNOTS / 24, 1),
            ];
        }

        return $legs;
    }

    /** @param list<array{nm: int, days: float}> $legs */
    public static function totalNm(array $legs): int
    {
        return array_sum(array_column($legs, 'nm'));
    }

    /** @param list<array{nm: int, days: float}> $legs */
    public static function totalDays(array $legs): float
    {
        return round(self::totalNm($legs) / self::AVERAGE_SPEED_KNOTS / 24, 1);
    }
}
