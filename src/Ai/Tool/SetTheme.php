<?php

declare(strict_types=1);

namespace App\Ai\Tool;

use App\Ui\Theme;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'set_theme',
    description: <<<'TXT'
Switch the interface between the light, dark and night colour themes.
"night" is night-watch mode: everything in red light, which preserves night vision in
the cockpit.
Only call it when the user asks to change the display (a theme, dark mode, red light)
or says they are on night watch right now. Never switch on your own: questions about
tonight's weather, sunset or planning a passage are not a request to change the screen.
The change applies immediately, to the whole page, not just this message.
The current theme is in your context: if it is already the one asked for, say so
instead of calling this tool.
TXT,
)]
final readonly class SetTheme
{
    public function __construct(
        private Theme $theme,
    ) {
    }

    /**
     * @param string $theme "light", "dark" or "night".
     */
    public function __invoke(string $theme): string
    {
        $theme = strtolower(trim($theme));

        if (!\in_array($theme, Theme::VALUES, true)) {
            return \sprintf('"%s" is not a theme. Use "light", "dark" or "night".', $theme);
        }

        if ($theme === $this->theme->current()) {
            return "The theme was already $theme, so nothing changed.";
        }

        $this->theme->set($theme);

        return "Theme set to $theme.";
    }
}
