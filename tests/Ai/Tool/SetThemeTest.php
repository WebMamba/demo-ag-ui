<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Ui\Theme;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SetThemeTest extends KernelTestCase
{
    public function testItSwitchesTheTheme(): void
    {
        self::assertSame('Theme set to dark.', $this->call('dark'));
        self::assertSame('dark', $this->theme()->current());
    }

    /** Night watch is dark mode seen through red light. */
    public function testNightWatchModeIsDarkModeInRed(): void
    {
        $this->call('night');

        self::assertSame('dark night', $this->theme()->htmlClasses());
    }

    public function testAskingForTheThemeAlreadySetChangesNothing(): void
    {
        self::assertStringContainsString('already light', $this->call('light'));
    }

    public function testAnUnknownThemeChangesNothing(): void
    {
        self::assertStringContainsString('not a theme', $this->call('neon'));
        self::assertSame('light', $this->theme()->current());
    }

    /** Through the toolbox: the model sends JSON, and this proves schema and signature agree. */
    private function call(string $theme): string
    {
        return self::getContainer()->get('ai.toolbox.navigator')
            ->execute(new ToolCall('call_'.bin2hex(random_bytes(4)), 'set_theme', ['theme' => $theme]))
            ->getResult();
    }

    private function theme(): Theme
    {
        return self::getContainer()->get(Theme::class);
    }
}
