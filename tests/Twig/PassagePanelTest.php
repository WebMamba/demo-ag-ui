<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\Components\PassagePanel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class PassagePanelTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private const PLAN = ['waypoints' => [
        ['name' => 'Panama', 'lat' => 8.92, 'lng' => -79.52, 'note' => ''],
        ['name' => 'Hiva Oa', 'lat' => -9.8, 'lng' => -139.03, 'note' => 'Clear in at Atuona'],
    ]];

    public function testItRendersTheWaypointsWithTheirLegs(): void
    {
        $html = (string) $this->createLiveComponent(PassagePanel::class, ['passage' => self::PLAN])->render();

        self::assertMatchesRegularExpression('/Panama.*nm.*days.*Hiva Oa.*Clear in at Atuona.*Total/s', $html);
    }

    public function testItShowsTheCourseToSteerToTheNextWaypoint(): void
    {
        $html = (string) $this->createLiveComponent(PassagePanel::class, ['passage' => self::PLAN])->render();

        // The simulated boat is past Panama, so it is heading for Hiva Oa.
        self::assertMatchesRegularExpression('/Course to steer.*\d{3}°.*→ Hiva Oa/s', $html);
        self::assertStringContainsString('passage-chart', $html);
    }

    public function testWithoutAPlanThereIsNoCourseToSteer(): void
    {
        $html = (string) $this->createLiveComponent(PassagePanel::class)->render();

        self::assertStringContainsString('---°', $html);
    }

    public function testARenderAfterTheLanguageChangedIsInThatLanguage(): void
    {
        $component = $this->createLiveComponent(PassagePanel::class, ['passage' => self::PLAN]);

        $component->set('locale', 'fr');

        self::assertStringContainsString('Cap à suivre', (string) $component->render(), 'The locale lives in no session: the re-render must carry it.');
    }

    public function testRemovingAWaypointUpdatesThePlanAndTellsTheChat(): void
    {
        $component = $this->createLiveComponent(PassagePanel::class, ['passage' => self::PLAN]);

        $component->call('removeWaypoint', ['index' => 0]);

        self::assertSame('Hiva Oa', $component->component()->passage['waypoints'][0]['name']);
        self::assertCount(1, $component->component()->passage['waypoints']);
        self::assertStringContainsString('passage:changed', (string) $component->render());
    }

    public function testRemovingAWaypointTellsTheNavigatorWhatTheSkipperDid(): void
    {
        $component = $this->createLiveComponent(PassagePanel::class, ['passage' => self::PLAN]);

        $component->call('removeWaypoint', ['index' => 1]);

        preg_match('/data-live-events-to-dispatch-value="([^"]*)"/', (string) $component->render(), $match);
        $events = array_column(json_decode(html_entity_decode($match[1]), true), 'payload', 'event');

        self::assertSame(['message' => 'I removed the waypoint “Hiva Oa” from the passage plan.'], $events['passage:edited']);
    }
}
