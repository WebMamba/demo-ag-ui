<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Ai\Passage\PassagePlan;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'remove_waypoint',
    description: 'Remove a waypoint from the passage plan, by its name.',
)]
final readonly class RemoveWaypoint
{
    public function __construct(
        private PassagePlan $plan,
    ) {
    }

    /**
     * @param string $name Name of the waypoint, as shown in the passage plan.
     */
    public function __invoke(string $name): string
    {
        return $this->plan->remove($name)
            ? \sprintf('Removed waypoint "%s" from the passage plan.', $name)
            : \sprintf('There is no "%s" in the passage plan, so nothing changed.', $name);
    }
}
