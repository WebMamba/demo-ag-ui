<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Ai\Passage\PassagePlan;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'add_waypoint',
    description: <<<'TXT'
Add a waypoint to the passage plan the user sees next to the chat. Distances and days
at sea between waypoints are computed for you. Use one call per waypoint, in sailing order.
TXT,
)]
final readonly class AddWaypoint
{
    public function __construct(
        private PassagePlan $plan,
    ) {
    }

    /**
     * @param string   $name      Short name (e.g. "Hiva Oa, Marquesas").
     * @param float    $latitude  Latitude in decimal degrees, WGS84 (south is negative).
     * @param float    $longitude Longitude in decimal degrees, WGS84 (west is negative).
     * @param string   $note      Optional one-line tip (e.g. "Clear in at Atuona").
     * @param int|null $position  Optional 0-based position to insert at; appended when omitted.
     */
    public function __invoke(string $name, float $latitude, float $longitude, string $note = '', ?int $position = null): string
    {
        $this->plan->add($name, $latitude, $longitude, $note, $position);

        return \sprintf('Added waypoint "%s" to the passage plan.', $name);
    }
}
