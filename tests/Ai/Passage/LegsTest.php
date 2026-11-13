<?php

declare(strict_types=1);

namespace App\Tests\Ai\Passage;

use App\Ai\Passage\Legs;
use PHPUnit\Framework\TestCase;

final class LegsTest extends TestCase
{
    public function testItComputesGreatCircleLegsInNauticalMiles(): void
    {
        $legs = Legs::between([
            ['name' => 'Panama', 'lat' => 8.92, 'lng' => -79.52],
            ['name' => 'Galápagos', 'lat' => -0.74, 'lng' => -90.31],
            ['name' => 'Hiva Oa', 'lat' => -9.8, 'lng' => -139.03],
        ]);

        self::assertCount(2, $legs);
        self::assertSame('Panama', $legs[0]['from']);
        self::assertSame('Galápagos', $legs[0]['to']);
        // ~870 nm Panama → Galápagos, ~2,900 nm Galápagos → Marquesas: the classic Puddle Jump.
        self::assertEqualsWithDelta(870, $legs[0]['nm'], 30);
        self::assertEqualsWithDelta(2930, $legs[1]['nm'], 60);
        self::assertEqualsWithDelta(20.3, $legs[1]['days'], 0.5, 'About three weeks at 6 knots.');
        self::assertSame($legs[0]['nm'] + $legs[1]['nm'], Legs::totalNm($legs));
    }

    public function testASingleWaypointHasNoLeg(): void
    {
        self::assertSame([], Legs::between([['name' => 'Panama', 'lat' => 8.92, 'lng' => -79.52]]));
    }
}
