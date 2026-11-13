<?php

declare(strict_types=1);

namespace App\Ai\Boat;

/**
 * The boat's instruments: GPS, speed and course over ground, wind.
 */
interface BoatInstruments
{
    public function read(): BoatReading;
}
