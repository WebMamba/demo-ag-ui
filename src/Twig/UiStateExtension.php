<?php

declare(strict_types=1);

namespace App\Twig;

use App\Ui\Locale;
use App\Ui\Theme;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** The interface's theme and language, for the page to render with. */
final class UiStateExtension extends AbstractExtension
{
    public function __construct(
        private readonly Theme $theme,
        private readonly Locale $locale,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ui_theme', $this->theme->current(...)),
            new TwigFunction('ui_theme_classes', $this->theme->htmlClasses(...)),
            new TwigFunction('ui_locale', $this->locale->current(...)),
        ];
    }
}
