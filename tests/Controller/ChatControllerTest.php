<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\AI\Agent\MockAgent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChatControllerTest extends WebTestCase
{
    public function testTheChatPageHasAFormToAskTheNavigator(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/chat');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('form input[name="message"]'));
        self::assertCount(1, $crawler->filter('#messages'));
    }

    public function testTurboGetsTheQuestionAndTheAnswerAppendedToTheMessages(): void
    {
        $client = static::createClient();
        $agent = new MockAgent(['Should I roll the genoa?' => 'Yes, roll a third of it before dark.']);
        static::getContainer()->set('ai.agent.basic', $agent);

        $client->request('POST', '/chat', ['message' => 'Should I roll the genoa?'], server: [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/vnd.turbo-stream.html; charset=UTF-8');
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('<turbo-stream action="append" target="messages">', $html);
        self::assertStringContainsString('Should I roll the genoa?', $html);
        self::assertStringContainsString('Yes, roll a third of it before dark.', $html);
        self::assertSame(1, $agent->getCallCount());
    }

    public function testTheConversationIsKeptAcrossMessages(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $agent = new MockAgent([
            'Hello' => 'Ahoy!',
            'And tonight?' => 'Gusts up to 29 knots.',
        ]);
        static::getContainer()->set('ai.agent.basic', $agent);

        $client->request('POST', '/chat', ['message' => 'Hello']);
        $client->request('POST', '/chat', ['message' => 'And tonight?']);

        $messages = $agent->getLastCall()['messages']->getMessages();
        self::assertCount(3, $messages, 'Hello, Ahoy!, And tonight?');

        $crawler = $client->request('GET', '/chat');
        self::assertStringContainsString('Gusts up to 29 knots.', $crawler->filter('#messages')->text());
    }

    public function testAnEmptyMessageIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/chat', ['message' => '']);

        self::assertResponseStatusCodeSame(422);
    }
}
