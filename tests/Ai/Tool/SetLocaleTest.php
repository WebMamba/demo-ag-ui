<?php

declare(strict_types=1);

namespace App\Tests\Ai\Tool;

use App\Ui\Locale;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SetLocaleTest extends KernelTestCase
{
    public function testItSwitchesTheLanguage(): void
    {
        self::assertSame('Interface language switched to "fr".', $this->call('fr'));
        self::assertSame('fr', self::getContainer()->get(Locale::class)->current());
    }

    public function testAskingForTheLanguageAlreadySetChangesNothing(): void
    {
        self::assertStringContainsString('already in "en"', $this->call('en'));
    }

    public function testAnUnsupportedLanguageChangesNothing(): void
    {
        self::assertStringContainsString('not a supported language', $this->call('de'));
        self::assertSame('en', self::getContainer()->get(Locale::class)->current());
    }

    private function call(string $locale): string
    {
        return self::getContainer()->get('ai.toolbox.navigator')
            ->execute(new ToolCall('call_'.bin2hex(random_bytes(4)), 'set_locale', ['locale' => $locale]))
            ->getResult();
    }
}
