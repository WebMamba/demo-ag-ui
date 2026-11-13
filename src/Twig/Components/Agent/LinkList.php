<?php

declare(strict_types=1);

namespace App\Twig\Components\Agent;

use AgUi\Bundle\Attribute\AsAgentComponent;
use App\Ai\Tool\LinkItem;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
#[AsAgentComponent(
    description: <<<'TXT'
Render a card list of external links (cruising guides, official formalities, places…).
Use when your answer benefits from a set of clickable pointers rather than plain text.
Never invent URLs.
TXT,
    tool: 'render_link_list',
)]
final class LinkList
{
    /** Short heading for the list (e.g. "Formalities in French Polynesia"). */
    public string $title;

    /** @var list<LinkItem> Clickable entries: each has a title, a URL, and an optional short description. */
    public array $items;

    /**
     * Guards against LLM-supplied URLs with unsafe schemes (javascript:, data:, etc.).
     * Anything not http(s) is rewritten to a safe placeholder.
     */
    public function safeUrl(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) ? $url : '#';
    }
}
