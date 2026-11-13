<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Ai\Boat\BoatInstruments;
use App\Ai\Boat\BoatReading;
use App\Ai\Passage\Legs;
use App\Ai\Passage\NextWaypoint;
use App\Ui\Locale;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Icon\Icon;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\Polyline;

/**
 * Renders the AG-UI shared state — the passage plan — the way a chart plotter does:
 * the course to steer to the next waypoint, the boat's instruments, and the chart.
 *
 * Two writers, one component. The agent's STATE_DELTA patches reach it through
 * agent-chat, which sets the `passage` prop; the user's edits go through LiveActions,
 * which announce the new plan with a `passage:changed` browser event — its detail is
 * the new state, which ag-ui#setState sends as RunAgentInput.state — then tell the
 * navigator what the skipper did with `passage:edited`, which starts that run.
 */
#[AsLiveComponent]
final class PassagePanel
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    /** @var array{waypoints?: list<array{name: string, lat: float, lng: float, note?: string}>} */
    #[LiveProp(writable: true)]
    public array $passage = ['waypoints' => []];

    /**
     * The interface language. It lives in no session — the agent changes it during its
     * run — so a re-render carries it: locale_controller sets it when it changes.
     */
    #[LiveProp(writable: true)]
    public string $locale = 'en';

    public function __construct(
        private readonly BoatInstruments $instruments,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly Locale $uiLocale,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[PostMount]
    public function rememberLocale(): void
    {
        $this->locale = $this->uiLocale->current();
    }

    #[PostHydrate]
    public function renderInLocale(): void
    {
        if (\in_array($this->locale, Locale::SUPPORTED, true)) {
            $this->localeSwitcher->setLocale($this->locale);
        }
    }

    #[LiveAction]
    public function removeWaypoint(#[LiveArg] int $index): void
    {
        $waypoints = $this->passage['waypoints'] ?? [];
        $removed = array_splice($waypoints, $index, 1);
        $this->passage['waypoints'] = $waypoints;

        $this->dispatchBrowserEvent('passage:changed', $this->passage);
        if ([] !== $removed) {
            $this->tellTheNavigator($this->translator->trans('passage.removed_message', ['%name%' => $removed[0]['name']]));
        }
    }

    #[LiveAction]
    public function clear(): void
    {
        $this->passage['waypoints'] = [];

        $this->dispatchBrowserEvent('passage:changed', $this->passage);
        $this->tellTheNavigator($this->translator->trans('passage.cleared_message'));
    }

    /**
     * Editing the plan is talking to the navigator: the console says what the skipper
     * did, and sends it as their message — after the new plan, so the run carries it.
     */
    private function tellTheNavigator(string $message): void
    {
        $this->dispatchBrowserEvent('passage:edited', ['message' => $message]);
    }

    /** @return list<array{name: string, lat: float, lng: float, note?: string}> */
    public function getWaypoints(): array
    {
        return array_values($this->passage['waypoints'] ?? []);
    }

    /** @return list<array{from: string, to: string, nm: int, days: float}> */
    public function getLegs(): array
    {
        return Legs::between($this->getWaypoints());
    }

    public function getTotalNm(): int
    {
        return Legs::totalNm($this->getLegs());
    }

    public function getTotalDays(): float
    {
        return Legs::totalDays($this->getLegs());
    }

    public function getBoat(): BoatReading
    {
        return $this->instruments->read();
    }

    public function getNext(): ?NextWaypoint
    {
        return NextWaypoint::ahead($this->getBoat()->position, $this->getWaypoints());
    }

    /** True wind angle: where the wind comes from, relative to the bow. Negative is port. */
    public function getTrueWindAngle(): int
    {
        $boat = $this->getBoat();

        return (int) round(fmod($boat->windDirectionDegrees - $boat->headingDegrees + 540.0, 360.0) - 180.0);
    }

    /** A position the way a GPS shows it: degrees and decimal minutes, e.g. 06°24.0′S. */
    public static function formatCoordinate(float $value, string $positive, string $negative): string
    {
        $abs = abs($value);
        $degrees = (int) floor($abs);

        return \sprintf('%02d°%04.1f′%s', $degrees, ($abs - $degrees) * 60, $value < 0 ? $negative : $positive);
    }

    /**
     * The chart: the boat, the plan, and the course to the next waypoint. Built anew on
     * every render; UX Map only redraws what changed, and keeps the user's pan and zoom.
     */
    public function getChart(): Map
    {
        $boat = $this->getBoat();
        $waypoints = $this->getWaypoints();

        $chart = (new Map())
            ->center($boat->position)
            ->zoom(4)
            ->fitBoundsToMarkers([] !== $waypoints)
            ->options(new LeafletOptions());

        $points = [];
        foreach ($waypoints as $i => $waypoint) {
            $points[] = $point = new Point((float) $waypoint['lat'], (float) $waypoint['lng']);
            $chart->addMarker(new Marker(
                position: $point,
                title: $waypoint['name'],
                icon: Icon::svg(\sprintf(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><circle cx="12" cy="12" r="10" fill="#ea580c" stroke="#fff" stroke-width="2"/><text x="12" y="16" text-anchor="middle" font-size="11" font-weight="700" font-family="sans-serif" fill="#fff">%d</text></svg>',
                    $i + 1,
                )),
            ));
        }

        if (\count($points) > 1) {
            $chart->addPolyline(new Polyline(points: $points, extra: ['style' => 'route']));
        }

        if (null !== $next = $this->getNext()) {
            $chart->addPolyline(new Polyline(points: [$boat->position, $next->position], extra: ['style' => 'course']));
        }

        $chart->addMarker(new Marker(
            position: $boat->position,
            title: 'Boat',
            icon: Icon::svg(\sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40"><g transform="rotate(%d 20 20)"><path d="M20 3 L27 30 L20 25 L13 30 Z" fill="#0ea5e9" stroke="#fff" stroke-width="2" stroke-linejoin="round"/></g></svg>',
                (int) $boat->headingDegrees,
            )),
        ));

        return $chart;
    }
}
