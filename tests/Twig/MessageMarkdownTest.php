<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * The agent answers in markdown: the chat renders it, and never trusts its HTML.
 */
final class MessageMarkdownTest extends KernelTestCase
{
    public function testAnAssistantMessageIsRenderedFromMarkdown(): void
    {
        $html = $this->render("**Reef** now:\n\n- wind 25 kn\n- [chart](https://example.com)");

        self::assertStringContainsString('<strong>Reef</strong>', $html);
        self::assertStringContainsString('<li>wind 25 kn</li>', $html);
        self::assertStringContainsString('<a href="https://example.com">chart</a>', $html);
    }

    public function testTheModelsHtmlAndRiskyLinksAreNotTrusted(): void
    {
        $html = $this->render("<script>alert(1)</script>\n\n[click](javascript:alert(1))");

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html, 'Raw HTML is shown as text.');
        self::assertStringNotContainsString('javascript:', $html);
    }

    public function testAUserMessageStaysPlainText(): void
    {
        self::assertStringContainsString('**not bold**', $this->render('**not bold**', 'user'));
    }

    private function render(string $content, string $role = 'assistant'): string
    {
        $twig = self::getContainer()->get(Environment::class);
        \assert($twig instanceof Environment);

        return $twig->render('chat/_message.html.twig', ['role' => $role, 'content' => $content]);
    }
}
