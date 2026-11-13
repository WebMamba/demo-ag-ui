<?php

declare(strict_types=1);

namespace App\Ai\Tool;

/**
 * One clickable entry in a rendered link list.
 *
 * Modelled as a class rather than an `array{...}` shape so the generated JSON
 * schema describes named properties; a phpdoc array shape degrades to an
 * untyped list and the model then sends positional tuples.
 */
final readonly class LinkItem
{
    public function __construct(
        /** Short label for the link, e.g. "Le Bouchon des Filles". */
        public string $title = '',
        /** Absolute http(s) URL. */
        public string $url = '',
        /** Optional one-sentence detail shown under the title. */
        public ?string $description = null,
    ) {
    }
}
