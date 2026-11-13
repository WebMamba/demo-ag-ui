<?php

declare(strict_types=1);

namespace App\Ai\Boat;

use Symfony\UX\Map\Point;

/**
 * Dead reckoning: where you end up from a position, a heading and a distance,
 * following a great circle.
 */
final class DeadReckoning
{
    private const EARTH_RADIUS_NM = 3440.065;

    public static function project(Point $from, float $headingDegrees, float $distanceNm): Point
    {
        $lat = deg2rad($from->getLatitude());
        $lng = deg2rad($from->getLongitude());
        $heading = deg2rad($headingDegrees);
        $angle = $distanceNm / self::EARTH_RADIUS_NM;

        $toLat = asin(sin($lat) * cos($angle) + cos($lat) * sin($angle) * cos($heading));
        $toLng = $lng + atan2(sin($heading) * sin($angle) * cos($lat), cos($angle) - sin($lat) * sin($toLat));

        // Normalise to [-180, 180): crossing the antimeridian must not yield -181°.
        $toLngDegrees = fmod(rad2deg($toLng) + 540.0, 360.0) - 180.0;

        return new Point(round(rad2deg($toLat), 4), round($toLngDegrees, 4));
    }
}
