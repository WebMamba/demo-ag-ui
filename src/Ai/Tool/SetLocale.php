<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Ui\Locale;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'set_locale',
    description: <<<'TXT'
Switch the language the interface is displayed in. Supported: "en" (English), "fr" (French).
Use when the user asks for the site, app or interface in another language.
This changes the interface chrome, not the language you reply in.
The current interface language is in your context: if it is already the one asked
for, say so instead of calling this tool.
TXT,
)]
final readonly class SetLocale
{
    public function __construct(
        private Locale $locale,
    ) {
    }

    /**
     * @param string $locale Two-letter code of the interface language: "en" or "fr".
     */
    public function __invoke(string $locale): string
    {
        $locale = strtolower(trim($locale));

        if (!\in_array($locale, Locale::SUPPORTED, true)) {
            return \sprintf('"%s" is not a supported language. Supported: %s.', $locale, implode(', ', Locale::SUPPORTED));
        }

        if ($locale === $this->locale->current()) {
            return "The interface was already in \"$locale\", so nothing changed.";
        }

        $this->locale->set($locale);

        return "Interface language switched to \"$locale\".";
    }
}
