<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use AgUi\Bundle\Component\ComponentActivityFactory;
use AgUi\Run\ResumeEntry;
use AgUi\Run\ResumeStatus;
use AgUi\SymfonyAi\Interrupt\Interrupt;
use AgUi\SymfonyAi\Interrupt\ResumableTool;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'reserve_mooring',
    description: <<<'TXT'
Reserve a mooring buoy or a marina berth for the user's boat. The skipper is always
asked to confirm before anything is reserved: call it directly, do not ask in text first.
TXT,
)]
final readonly class ReserveMooring implements ResumableTool
{
    public function __construct(
        private ComponentActivityFactory $activities,
    ) {
    }

    /**
     * @param string $marina           Name of the marina or mooring field (e.g. "Marina Papeete").
     * @param string $island           Island or port (e.g. "Tahiti").
     * @param string $arrival          Expected arrival date, ISO 8601 (YYYY-MM-DD).
     * @param int    $nights           Number of nights.
     * @param float  $boatLengthMeters Length overall of the boat, in meters.
     */
    public function __invoke(string $marina, string $island, string $arrival, int $nights = 3, float $boatLengthMeters = 12.0): Interrupt
    {
        $props = compact('marina', 'island', 'arrival', 'nights', 'boatLengthMeters');

        return Interrupt::confirmation(\sprintf('Reserve a mooring at %s (%s) from %s for %d night(s), %.1f m boat?', $marina, $island, $arrival, $nights, $boatLengthMeters))
            ->withActivity($this->activities->create('Agent:MooringReservation', $props, 'Asked the skipper to confirm the reservation.'));
    }

    public function resume(array $arguments, ResumeEntry $resume): string
    {
        // A refusal is still "resolved": "cancelled" is for a skipper who walked away.
        $approved = ResumeStatus::Resolved === $resume->status && \is_array($resume->payload) && true === ($resume->payload['approved'] ?? false);

        if (!$approved) {
            return 'The skipper declined: nothing was reserved.';
        }

        // A demo: nothing is really reserved, but the agent gets a believable receipt.
        $reference = strtoupper(substr(hash('xxh3', serialize($arguments)), 0, 6));

        return \sprintf('Mooring reserved at %s from %s for %d night(s). Reference: %s. Call the harbour master on VHF 12 on approach.', $arguments['marina'] ?? 'the marina', $arguments['arrival'] ?? '?', (int) ($arguments['nights'] ?? 1), $reference);
    }
}
