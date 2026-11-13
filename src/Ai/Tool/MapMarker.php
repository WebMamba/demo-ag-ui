<?php

declare(strict_types=1);

namespace App\Ai\Tool;

/**
 * One pinpoint on a rendered map.
 *
 * Modelled as a class rather than an `array{...}` shape so the generated JSON
 * schema describes named properties; a phpdoc array shape degrades to an
 * untyped list and the model then sends positional tuples.
 */
final readonly class MapMarker
{
    public function __construct(
        /** Latitude of the marker in decimal degrees, WGS84. */
        public float $lat = 0.0,
        /** Longitude of the marker in decimal degrees, WGS84. */
        public float $lng = 0.0,
        /** Short human-readable label, e.g. "Parc de la Tête d'Or". */
        public string $title = '',
        /** Optional one-sentence detail shown in the marker popup. */
        public ?string $description = null,
    ) {
    }
}
