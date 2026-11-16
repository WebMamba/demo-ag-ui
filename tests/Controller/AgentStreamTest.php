<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\Support\ScriptedPlatform;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What the demo adds on top of the AG-UI SDK, end to end: the controller, the bundle,
 * the bridge and the real agent, with only the model scripted.
 */
final class AgentStreamTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        ScriptedPlatform::reset();
        $this->client = self::createClient();
        $this->client->request('GET', '/');   // the session effects are keyed by
    }

    public function testAThemeChangeIsAnnouncedToThePageAsACustomEvent(): void
    {
        ScriptedPlatform::$rounds = [[new ToolCallComplete([new ToolCall('c1', 'set_theme', ['theme' => 'dark'])])], [new TextDelta('Done.')]];

        $events = $this->stream();

        $custom = self::first($events, 'CUSTOM');
        self::assertSame('turbo_agent.theme', $custom['name']);
        self::assertSame(['toolCallId' => 'c1', 'theme' => 'dark'], $custom['value']);
    }

    public function testALanguageChangeShipsTheTranslatedChrome(): void
    {
        ScriptedPlatform::$rounds = [[new ToolCallComplete([new ToolCall('c1', 'set_locale', ['locale' => 'fr'])])], [new TextDelta('Fait.')]];

        $custom = self::first($this->stream(), 'CUSTOM');

        self::assertSame('turbo_agent.locale', $custom['name']);
        self::assertSame('fr', $custom['value']['locale']);
        self::assertStringContainsString('<turbo-stream action="replace" target="app-chrome">', $custom['value']['html']);
        self::assertStringContainsString('Votre navigateur IA', $custom['value']['html'], 'The chrome is rendered in the new language.');
    }

    public function testTheAgentIsToldTheInterfaceAndWhatItsToolsChangedInIt(): void
    {
        ScriptedPlatform::$rounds = [[new ToolCallComplete([new ToolCall('c1', 'set_theme', ['theme' => 'night'])])], [new TextDelta('Night watch.')]];

        $events = $this->stream();

        self::assertStringContainsString('Interface theme: light', $this->sentToModel());
        self::assertStringContainsString('Interface language: en', $this->sentToModel());
        self::assertStringEndsWith('[context changed] Interface theme: night', self::first($events, 'TOOL_CALL_RESULT')['content']);
        self::assertSame(8192, ScriptedPlatform::$calls[0]['options']['max_tokens'] ?? null, 'The endpoint\'s options reach the model.');
    }

    public function testThePassagePlanIsTheSharedState(): void
    {
        ScriptedPlatform::$rounds = [
            [new ToolCallComplete([new ToolCall('c2', 'add_waypoint', ['name' => 'Hiva Oa', 'latitude' => -9.8, 'longitude' => -139.03])])],
            [new TextDelta('Added.')],
        ];

        $events = $this->stream(state: ['waypoints' => [['name' => 'Panama', 'lat' => 8.9, 'lng' => -79.5, 'note' => '']]]);

        self::assertSame([['op' => 'add', 'path' => '/waypoints/-', 'value' => ['name' => 'Hiva Oa', 'lat' => -9.8, 'lng' => -139.03, 'note' => '']]], self::first($events, 'STATE_DELTA')['delta']);
        self::assertStringContainsString('1. Panama', $this->sentToModel(), 'The model reads the plan as the crew sees it.');
    }

    public function testAPassagePlanThatIsNotOneIsRejected(): void
    {
        $this->client->request('POST', '/agent/stream', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['threadId' => 't', 'runId' => 'r', 'messages' => [], 'state' => ['waypoints' => [['name' => '', 'lat' => 1, 'lng' => 2]]]], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(422);
    }

    public function testTheSkipperConfirmsAMooringReservation(): void
    {
        ScriptedPlatform::$rounds = [[new ToolCallComplete([new ToolCall('c3', 'reserve_mooring', ['marina' => 'Marina Papeete', 'island' => 'Tahiti', 'arrival' => '2026-05-20', 'nights' => 5])])]];

        $events = $this->stream();

        $outcome = self::first($events, 'RUN_FINISHED')['outcome'];
        self::assertSame('interrupt', $outcome['type']);
        $interruptId = $outcome['interrupts'][0]['id'];
        self::assertStringStartsWith('int_c3.', $interruptId, 'Signed by the server.');

        $question = self::first($events, 'ACTIVITY_SNAPSHOT');
        self::assertSame('act_c3', $question['messageId'], 'The client finds the question by its tool call.');
        self::assertStringContainsString('Marina Papeete', $question['content']['html']);
        self::assertStringContainsString('ag-ui#resume', $question['content']['html']);
        self::assertSame($interruptId, $question['content']['interruptId'], 'ag-ui-activities marks the component with it.');

        // The browser sends the conversation back, with the skipper's answer.
        ScriptedPlatform::$rounds = [[new TextDelta('Reserved.')]];
        $events = $this->stream(messages: self::first($events, 'MESSAGES_SNAPSHOT')['messages'], resume: [['interruptId' => $interruptId, 'status' => 'resolved', 'payload' => ['approved' => true]]]);

        self::assertStringStartsWith('Mooring reserved at Marina Papeete', self::first($events, 'TOOL_CALL_RESULT')['content']);
        self::assertSame(['type' => 'success'], self::first($events, 'RUN_FINISHED')['outcome']);
    }

    /**
     * @param list<array<string, mixed>>|null $messages
     * @param list<array<string, mixed>>      $resume
     * @param array<string, mixed>|null       $state
     *
     * @return list<array<string, mixed>>
     */
    private function stream(?array $messages = null, array $resume = [], ?array $state = null): array
    {
        $input = array_filter([
            'threadId' => 'thread_1',
            'runId' => 'run_'.\count(ScriptedPlatform::$calls),
            'messages' => $messages ?? [['id' => 'u1', 'role' => 'user', 'content' => 'Hello']],
            'resume' => $resume,
            'state' => $state,
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);

        ob_start();
        $this->client->request('POST', '/agent/stream', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($input, \JSON_THROW_ON_ERROR));
        $body = ob_get_clean().$this->client->getInternalResponse()->getContent();

        self::assertResponseIsSuccessful();

        $events = [];
        foreach (explode("\n\n", trim($body)) as $frame) {
            if (str_starts_with($frame, 'data: ')) {
                $events[] = json_decode(substr($frame, 6), true, 512, \JSON_THROW_ON_ERROR);
            }
        }

        $last = end($events);
        self::assertNotSame('RUN_ERROR', $last['type'] ?? null, $last['message'] ?? '');

        return $events;
    }

    private function sentToModel(): string
    {
        return json_encode(ScriptedPlatform::$calls[0]['input'], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return array<string, mixed>
     */
    private static function first(array $events, string $type): array
    {
        foreach ($events as $event) {
            if ($type === $event['type']) {
                return $event;
            }
        }

        self::fail(\sprintf('No %s event in: %s', $type, implode(', ', array_column($events, 'type'))));
    }
}
