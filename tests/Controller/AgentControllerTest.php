<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use App\Tests\Support\ScriptedPlatform;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AgentControllerTest extends WebTestCase
{
    /**
     * Effects are persisted, so the page has to come back already wearing them —
     * the browser only handles the change, never the initial state.
     */
    public function testThePageRendersTheCurrentThemeAndLocaleOnTheHtmlElement(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        $html = $crawler->filter('html');

        self::assertSame('en', $html->attr('lang'));
        self::assertStringNotContainsString('dark', (string) $html->attr('class'));
        self::assertCount(1, $crawler->filter('#app-chrome'), 'A language change replaces this by id.');
    }

    /**
     * The streaming endpoint can create no session, so the page it is opened from must.
     * Asserted on the session id rather than on a Set-Cookie header: the test
     * environment's mock storage never emits one, since it bypasses PHP's session
     * machinery entirely.
     */
    public function testThePageStreamsToTheAgUiEndpointAndShowsThePassagePlan(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('/agent/stream', $crawler->filter('[data-controller~="ag-ui"]')->attr('data-ag-ui-endpoint-value'));
        self::assertCount(1, $crawler->filter('#passage-panel'));
    }

    public function testTheStreamEndpointRejectsABodyThatIsNotARunAgentInput(): void
    {
        $client = static::createClient();
        $client->request('POST', '/agent/stream', server: ['CONTENT_TYPE' => 'application/json'], content: '{"messages": []}');

        self::assertResponseStatusCodeSame(422);
    }

    public function testARunAgentInputIsAnsweredWithAStreamOfAgUiEvents(): void
    {
        $client = static::createClient();

        ScriptedPlatform::$rounds = [[new TextDelta('Hi '), new TextDelta('there!')]];

        $client->request('POST', '/agent/stream', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'threadId' => 'thread_1',
            'runId' => 'run_1',
            'messages' => [['id' => 'u1', 'role' => 'user', 'content' => 'Hello']],
            'tools' => [],
            'state' => [],
            'context' => [],
            'forwardedProps' => new \stdClass(),
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/event-stream; charset=UTF-8');

        $events = array_map(
            static fn (string $frame): array => json_decode(substr($frame, 6), true, 512, \JSON_THROW_ON_ERROR),
            array_filter(explode("\n\n", $client->getInternalResponse()->getContent())),
        );

        self::assertSame(
            ['RUN_STARTED', 'STATE_SNAPSHOT', 'TEXT_MESSAGE_START', 'TEXT_MESSAGE_CONTENT', 'TEXT_MESSAGE_CONTENT', 'TEXT_MESSAGE_END', 'MESSAGES_SNAPSHOT', 'RUN_FINISHED'],
            array_column($events, 'type'),
        );
        self::assertSame('run_1', $events[0]['runId']);

        $snapshot = array_values(array_filter($events, static fn (array $e): bool => 'MESSAGES_SNAPSHOT' === $e['type']))[0];
        self::assertSame('Hi there!', $snapshot['messages'][1]['content']);
    }
}
