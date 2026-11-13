<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Tests\Support\NavigatorToolbox;
use AgUi\Bridge\SymfonyAi\Activity\Activity;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * render_map is the Agent:Map Twig component, exposed to the model by #[AsAgentComponent].
 * Going through the agent's toolbox proves the model's JSON and the component agree.
 */
final class RenderMapTest extends KernelTestCase
{
    use NavigatorToolbox;

    public function testTheToolboxRendersAMapFromJsonArguments(): void
    {
        $activity = $this->render([
            'centerLat' => 45.764,
            'centerLng' => 4.8357,
            'zoom' => 12,
            'markers' => [
                ['lat' => 45.7597, 'lng' => 4.8422, 'title' => 'Vieux Lyon'],
                ['lat' => 45.7704, 'lng' => 4.857, 'title' => 'Parc de la Tête d\'Or', 'description' => 'A large urban park.'],
            ],
        ]);

        self::assertStringContainsString('agent-component-map', $activity->content['html']);
        self::assertStringContainsString('Vieux Lyon', $activity->content['html']);
        self::assertStringContainsString('A large urban park.', $activity->content['html']);
        self::assertStringNotContainsString('<', (string) $activity, 'The model reads a sentence, never the markup.');
    }

    public function testItCanDrawTheRouteBetweenTheMarkers(): void
    {
        $activity = $this->render([
            'centerLat' => 0.0,
            'centerLng' => -110.0,
            'zoom' => 3,
            'markers' => [['lat' => 8.92, 'lng' => -79.52, 'title' => 'Panama'], ['lat' => -9.8, 'lng' => -139.03, 'title' => 'Hiva Oa']],
            'drawRoute' => true,
        ]);

        self::assertStringContainsString('polylines', $activity->content['html']);
        self::assertStringContainsString('-139.03', $activity->content['html']);
    }

    public function testTheModelMustGiveTheCenterButNotTheRest(): void
    {
        self::bootKernel();

        foreach (self::navigatorToolbox()->getTools() as $tool) {
            if ('render_map' === $tool->getName()) {
                self::assertSame(['centerLat', 'centerLng'], $tool->getParameters()['required'] ?? null);

                return;
            }
        }

        self::fail('render_map is not offered to the model.');
    }

    /** @param array<string, mixed> $arguments */
    private function render(array $arguments): Activity
    {
        self::bootKernel();
        $result = self::navigatorToolbox()->execute(new ToolCall('call_1', 'render_map', $arguments))->getResult();
        self::assertInstanceOf(Activity::class, $result);

        return $result;
    }
}
