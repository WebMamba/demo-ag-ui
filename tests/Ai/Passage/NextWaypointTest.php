<?php

declare(strict_types=1);

namespace App\Tests\Ai\Passage;

use App\Ai\Passage\NextWaypoint;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Map\Point;

final class NextWaypointTest extends TestCase
{
    private const PLAN = [
        ['name' => 'Panama', 'lat' => 8.92, 'lng' => -79.52],
        ['name' => 'Galápagos', 'lat' => -0.9, 'lng' => -89.6],
        ['name' => 'Hiva Oa', 'lat' => -9.8, 'lng' => -139.03],
        ['name' => 'Tahiti', 'lat' => -17.53, 'lng' => -149.57],
    ];

    public function testTheNextWaypointIsTheEndOfTheLegTheBoatIsOn(): void
    {
        // Halfway between the Galápagos and the Marquesas.
        $next = NextWaypoint::ahead(new Point(-6.4, -112.7), self::PLAN);

        self::assertSame('Hiva Oa', $next?->name);
        self::assertEqualsWithDelta(262, $next->bearingDegrees, 2);
        self::assertEqualsWithDelta(1585, $next->distanceNm, 15);
    }

    public function testBearingsGoAllRoundTheCompass(): void
    {
        $at = new Point(0.0, 0.0);
        $bearingTo = static fn (float $lat, float $lng): int => NextWaypoint::ahead($at, [['name' => 'x', 'lat' => $lat, 'lng' => $lng]])->bearingDegrees;

        self::assertSame(0, $bearingTo(1.0, 0.0));
        self::assertSame(90, $bearingTo(0.0, 1.0));
        self::assertSame(180, $bearingTo(-1.0, 0.0));
        self::assertSame(270, $bearingTo(0.0, -1.0));
    }

    public function testTheCorrectionIsTheShortestTurn(): void
    {
        $next = NextWaypoint::ahead(new Point(0.0, 0.0), [['name' => 'North', 'lat' => 1.0, 'lng' => 0.0]]);

        self::assertSame(10, $next?->correctionFrom(350.0), 'From 350° to 000°: 10° to starboard, not 350° to port.');
        self::assertSame(-90, $next->correctionFrom(90.0));
    }

    public function testThereIsNoNextWaypointWithoutAPlan(): void
    {
        self::assertNull(NextWaypoint::ahead(new Point(0.0, 0.0), []));
    }
}
