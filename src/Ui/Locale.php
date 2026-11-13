<?php

declare(strict_types=1);

namespace App\Ui;

use AgUi\Bundle\Attribute\AsAgentContext;

/**
 * The language the interface is displayed in. set_locale changes it, and the agent is
 * told what it is.
 */
final class Locale
{
    public const array SUPPORTED = ['en', 'fr'];

    private string $current = 'en';

    #[AsAgentContext('Interface language')]
    public function current(): string
    {
        return $this->current;
    }

    public function set(string $locale): void
    {
        $this->current = $locale;
    }
}
