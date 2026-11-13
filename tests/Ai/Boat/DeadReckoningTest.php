<?php

declare(strict_types=1);

namespace App\Tests\Ai\Boat;

use App\Ai\Boat\DeadReckoning;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Map\Point;

final class DeadReckoningTest extends TestCase
{
    public function testSixtyMilesWestOnTheEquatorIsOneDegree(): void
    {
        $to = DeadReckoning::project(new Point(0.0, -100.0), 270.0, 60.0);

        self::assertEqualsWithDelta(0.0, $to->getLatitude(), 0.001);
        self::assertEqualsWithDelta(-101.0, $to->getLongitude(), 0.01, 'A nautical mile is a minute of arc.');
    }

    public function testSailingSouthOnlyChangesLatitude(): void
    {
        $to = DeadReckoning::project(new Point(-5.0, -120.0), 180.0, 120.0);

        self::assertEqualsWithDelta(-7.0, $to->getLatitude(), 0.01);
        self::assertEqualsWithDelta(-120.0, $to->getLongitude(), 0.001);
    }

    public function testCrossingTheAntimeridianWrapsLongitude(): void
    {
        $to = DeadReckoning::project(new Point(-17.0, -179.5), 270.0, 60.0);

        self::assertGreaterThan(179.0, $to->getLongitude());
    }
}
