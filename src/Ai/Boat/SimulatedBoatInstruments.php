<?php

declare(strict_types=1);

namespace App\Ai\Boat;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\UX\Map\Point;

/**
 * Instruments for the demo: a boat in the trade winds halfway between the Galápagos
 * and the Marquesas, broad reaching west-south-west. On board, this would read NMEA.
 */
#[AsAlias(BoatInstruments::class)]
final class SimulatedBoatInstruments implements BoatInstruments
{
    public function read(): BoatReading
    {
        return new BoatReading(
            position: new Point(-6.4, -112.7),
            speedKnots: 6.5,
            headingDegrees: 255.0,
            windSpeedKnots: 16.0,
            windDirectionDegrees: 110.0,
        );
    }
}
