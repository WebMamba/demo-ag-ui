<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Ai\Boat\BoatInstruments;
use App\Ai\Boat\BoatReading;
use App\Ai\Tool\PredictPosition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\Map\Point;

final class PredictPositionTest extends KernelTestCase
{
    public function testItPredictsWhereTheBoatWillBeFromItsInstruments(): void
    {
        self::bootKernel();
        self::getContainer()->set(BoatInstruments::class, new class implements BoatInstruments {
            public function read(): BoatReading
            {
                return new BoatReading(new Point(0.0, -100.0), 6.0, 270.0, 15.0, 90.0);
            }
        });

        $result = self::getContainer()->get(PredictPosition::class)([1, 10, 24]);

        self::assertSame(6.0, $result['now']['speed_knots']);
        self::assertCount(3, $result['predictions']);
        self::assertSame(10, $result['predictions'][1]['in_hours']);
        self::assertSame(60.0, $result['predictions'][1]['distance_nm']);
        self::assertEqualsWithDelta(-101.0, $result['predictions'][1]['longitude'], 0.01, '60 nm west on the equator.');
        self::assertEqualsWithDelta(-102.4, $result['predictions'][2]['longitude'], 0.01, '144 nm in a day at 6 knots.');
    }

    public function testItPredictsForTheHeadingTheSkipperPlansToSail(): void
    {
        self::bootKernel();
        self::getContainer()->set(BoatInstruments::class, new class implements BoatInstruments {
            public function read(): BoatReading
            {
                return new BoatReading(new Point(0.0, -100.0), 6.0, 270.0, 15.0, 90.0);
            }
        });

        $result = self::getContainer()->get(PredictPosition::class)([10], 180.0);

        self::assertSame(180.0, $result['heading_degrees']);
        self::assertEqualsWithDelta(-1.0, $result['predictions'][0]['latitude'], 0.01, '60 nm south.');
        self::assertEqualsWithDelta(-100.0, $result['predictions'][0]['longitude'], 0.001);
    }

    public function testTheAgentIsOfferedTheToolWithAListOfHours(): void
    {
        self::bootKernel();

        $tools = self::getContainer()->get('ai.toolbox.navigator')->getTools();
        $tool = current(array_filter($tools, static fn ($t) => 'predict_position' === $t->getName()));

        self::assertNotFalse($tool, 'predict_position is registered in the toolbox.');
        self::assertSame('array', $tool->getParameters()['properties']['hours']['type']);
        self::assertSame('integer', $tool->getParameters()['properties']['hours']['items']['type']);
    }
}
