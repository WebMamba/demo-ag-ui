<?php

declare(strict_types=1);

namespace App\Ai\Passage;

use AgUi\Bridge\SymfonyAi\State\DescribesItself;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The AG-UI shared state: the passage plan the crew sees next to the chat.
 *
 * The tools that change it inject it like any service; the AG-UI SDK fills it from the
 * state the browser sends, turns every change into a STATE_DELTA, and resets it after
 * the run.
 */
final class PassagePlan implements DescribesItself, ResetInterface
{
    /** @var list<Waypoint> */
    #[Assert\Valid]
    public array $waypoints = [];

    public function add(string $name, float $lat, float $lng, string $note = '', ?int $position = null): void
    {
        $waypoint = new Waypoint(trim($name), round($lat, 4), round($lng, 4), trim($note));

        if (null === $position || $position >= \count($this->waypoints)) {
            $this->waypoints[] = $waypoint;
        } else {
            array_splice($this->waypoints, max(0, $position), 0, [$waypoint]);
        }
    }

    public function remove(string $name): bool
    {
        foreach ($this->waypoints as $i => $waypoint) {
            if (0 === strcasecmp($waypoint->name, trim($name))) {
                array_splice($this->waypoints, $i, 1);

                return true;
            }
        }

        return false;
    }

    /** The model reads distances and days at sea, which the state only stores implicitly. */
    public function describeForAgent(): string
    {
        if ([] === $this->waypoints) {
            return 'The passage plan panel is empty.';
        }

        $lines = [];
        foreach ($this->waypoints as $i => $waypoint) {
            $lines[] = \sprintf('%d. %s (%.4F, %.4F)%s', $i + 1, $waypoint->name, $waypoint->lat, $waypoint->lng, '' !== $waypoint->note ? ' — '.$waypoint->note : '');
        }

        $legs = Legs::between(array_map(static fn (Waypoint $w): array => ['name' => $w->name, 'lat' => $w->lat, 'lng' => $w->lng], $this->waypoints));
        foreach ($legs as $leg) {
            $lines[] = \sprintf('Leg %s → %s: %d nm, %.1f days', $leg['from'], $leg['to'], $leg['nm'], $leg['days']);
        }
        if ([] !== $legs) {
            $lines[] = \sprintf('Total: %d nm, about %.1f days at sea at %.0f knots.', Legs::totalNm($legs), Legs::totalDays($legs), Legs::AVERAGE_SPEED_KNOTS);
        }

        return "The passage plan panel currently contains (the user may have edited it):\n".implode("\n", $lines);
    }

    public function reset(): void
    {
        $this->waypoints = [];
    }
}
