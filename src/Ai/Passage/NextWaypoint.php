<?php

declare(strict_types=1);

namespace App\Ai\Passage;

use Symfony\UX\Map\Distance\DistanceCalculator;
use Symfony\UX\Map\Distance\DistanceUnit;
use Symfony\UX\Map\Distance\HaversineDistanceCalculator;
use Symfony\UX\Map\Point;

/**
 * The waypoint the boat is heading for, and the course to steer to get there: what
 * a chart plotter shows as BTW (bearing to waypoint) and DTW (distance to waypoint).
 */
final readonly class NextWaypoint
{
    public function __construct(
        public string $name,
        public Point $position,
        /** Initial great-circle bearing from the boat, in degrees true. */
        public int $bearingDegrees,
        public int $distanceNm,
        /** At the boat's average speed. */
        public float $days,
    ) {
    }

    /**
     * The boat sails the plan in order, so the next waypoint is the end of the leg it is
     * on: the leg it would lengthen least by passing through the boat's position.
     *
     * @param list<array{name: string, lat: float, lng: float, note?: string}> $waypoints
     */
    public static function ahead(Point $boat, array $waypoints): ?self
    {
        if ([] === $waypoints) {
            return null;
        }

        $distance = new DistanceCalculator(new HaversineDistanceCalculator(), DistanceUnit::NauticalMile);
        $points = array_map(static fn (array $w): Point => new Point((float) $w['lat'], (float) $w['lng']), $waypoints);

        $next = 0;
        $smallestDetour = \INF;
        for ($i = 1, $n = \count($points); $i < $n; ++$i) {
            $detour = $distance->calculateDistance($points[$i - 1], $boat)
                + $distance->calculateDistance($boat, $points[$i])
                - $distance->calculateDistance($points[$i - 1], $points[$i]);

            if ($detour < $smallestDetour) {
                $smallestDetour = $detour;
                $next = $i;
            }
        }

        $nm = $distance->calculateDistance($boat, $points[$next]);

        return new self(
            name: $waypoints[$next]['name'],
            position: $points[$next],
            bearingDegrees: self::bearing($boat, $points[$next]),
            distanceNm: (int) round($nm),
            days: round($nm / Legs::AVERAGE_SPEED_KNOTS / 24, 1),
        );
    }

    /** How far to turn from the heading to steer this course: negative to port, positive to starboard. */
    public function correctionFrom(float $headingDegrees): int
    {
        return (int) round(fmod($this->bearingDegrees - $headingDegrees + 540.0, 360.0) - 180.0);
    }

    private static function bearing(Point $from, Point $to): int
    {
        $φ1 = deg2rad($from->getLatitude());
        $φ2 = deg2rad($to->getLatitude());
        $Δλ = deg2rad($to->getLongitude() - $from->getLongitude());

        $θ = atan2(sin($Δλ) * cos($φ2), cos($φ1) * sin($φ2) - sin($φ1) * cos($φ2) * cos($Δλ));

        return ((int) round(rad2deg($θ)) + 360) % 360;
    }
}
