<?php

declare(strict_types=1);

namespace App\Ui;

use AgUi\Bundle\Attribute\AsAgentContext;

/**
 * The interface's colour theme. set_theme changes it, and the agent is told what it is.
 */
final class Theme
{
    /** "night" is night-watch mode: red light only, to keep night vision in the cockpit. */
    public const array VALUES = ['light', 'dark', 'night'];

    private string $current = 'light';

    #[AsAgentContext('Interface theme')]
    public function current(): string
    {
        return $this->current;
    }

    public function set(string $theme): void
    {
        $this->current = $theme;
    }

    /** The classes on <html>: night watch is dark mode, seen through red. */
    public function htmlClasses(): string
    {
        return match ($this->current) {
            'dark' => 'dark',
            'night' => 'dark night',
            default => '',
        };
    }
}
