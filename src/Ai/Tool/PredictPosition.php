<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Ai\Boat\BoatInstruments;
use App\Ai\Boat\DeadReckoning;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'predict_position',
    description: <<<'TXT'
Read the boat's instruments (position, speed and heading over ground, true wind) and
predict where the boat will be in a number of hours, by dead reckoning. Pass a heading
to predict for a course the skipper plans to sail; otherwise the current heading is used.
Assumes speed and heading stay constant: the further ahead, the less reliable. Use it to answer
"where will we be tomorrow?", estimate arrival times, or find the position at which to
check the sea state ahead.
TXT,
)]
final class PredictPosition
{
    public function __construct(
        private readonly BoatInstruments $instruments,
    ) {
    }

    /**
     * @param list<int>  $hours   Hours from now to predict for, e.g. [1, 10, 24, 72]
     * @param float|null $heading Course to sail, in degrees true; the boat's current heading when omitted
     *
     * @return array<string, mixed>
     */
    public function __invoke(array $hours, ?float $heading = null): array
    {
        $boat = $this->instruments->read();
        $heading ??= $boat->headingDegrees;

        return [
            'now' => [
                'latitude' => $boat->position->getLatitude(),
                'longitude' => $boat->position->getLongitude(),
                'speed_knots' => $boat->speedKnots,
                'heading_degrees' => $boat->headingDegrees,
                'true_wind_knots' => $boat->windSpeedKnots,
                'true_wind_from_degrees' => $boat->windDirectionDegrees,
            ],
            'heading_degrees' => $heading,
            'predictions' => array_map(static function (int $h) use ($boat, $heading): array {
                $distance = $boat->speedKnots * $h;
                $at = DeadReckoning::project($boat->position, $heading, $distance);

                return [
                    'in_hours' => $h,
                    'latitude' => $at->getLatitude(),
                    'longitude' => $at->getLongitude(),
                    'distance_nm' => round($distance, 1),
                ];
            }, array_values($hours)),
            'assumption' => 'Constant speed and heading. Weather and wind shifts are not taken into account.',
        ];
    }
}
