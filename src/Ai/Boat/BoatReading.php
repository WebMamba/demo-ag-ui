<?php

declare(strict_types=1);

namespace App\Ai\Boat;

use Symfony\UX\Map\Point;

/**
 * One reading of the boat's instruments: where it is, how fast and where it goes
 * over the ground, and the true wind it is sailing in.
 */
final readonly class BoatReading
{
    public function __construct(
        public Point $position,
        public float $speedKnots,
        public float $headingDegrees,
        public float $windSpeedKnots,
        public float $windDirectionDegrees,
    ) {
    }
}
