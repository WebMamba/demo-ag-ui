<?php

declare(strict_types=1);

namespace App\Ui;

use AgUi\Event\CustomEvent;
use AgUi\SymfonyAi\Mapping\ToolResultMapping;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

/**
 * Tells the page the agent changed the interface, with a CUSTOM event — the protocol's
 * escape hatch for application signals. A client that does not know the name ignores it.
 *
 *  - turbo_agent.theme: the browser toggles the classes on <html>;
 *  - turbo_agent.locale: translated markup only the server can render, as a <turbo-stream>.
 */
final readonly class UiMapping implements ToolResultMapping
{
    public function __construct(
        private Theme $theme,
        private Locale $locale,
        private Environment $twig,
        private LocaleAwareInterface $translator,
    ) {
    }

    public function map(ToolResult $result): iterable
    {
        $toolCallId = $result->getToolCall()->getId();

        return match ($result->getToolCall()->getName()) {
            'set_theme' => [new CustomEvent('turbo_agent.theme', [
                'toolCallId' => $toolCallId,
                'theme' => $this->theme->current(),
            ])],
            'set_locale' => [new CustomEvent('turbo_agent.locale', [
                'toolCallId' => $toolCallId,
                'locale' => $this->locale->current(),
                'html' => $this->chrome($this->locale->current()),
            ])],
            default => [],
        };
    }

    /** The page's chrome in that language, without changing the language of the rest of this response. */
    private function chrome(string $locale): string
    {
        $previous = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        try {
            return $this->twig->render('agent/_chrome.stream.html.twig', ['locale' => $locale]);
        } finally {
            $this->translator->setLocale($previous);
        }
    }
}
