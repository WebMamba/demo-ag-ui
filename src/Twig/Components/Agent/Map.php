<?php

declare(strict_types=1);

namespace App\Twig\Components\Agent;

use App\Ai\Tool\MapMarker;
use AgUi\Bundle\Attribute\AsAgentComponent;
use Symfony\UX\Map\InfoWindow;
use Symfony\UX\Map\Map as UxMap;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\Map\Polyline;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
#[AsAgentComponent(
    description: <<<'TXT'
Render an interactive chart (Leaflet map) with optional markers, and optionally the
route joining them in order. Use it to show anchorages, landfalls or a passage.
Coordinates must be real WGS84 latitudes/longitudes (south and west are negative).
TXT,
    tool: 'render_map',
)]
final class Map
{
    /** Latitude of the map center in decimal degrees, WGS84 (e.g. -17.5350 for Papeete). */
    public float $centerLat;

    /** Longitude of the map center in decimal degrees, WGS84 (e.g. -149.5696 for Papeete). */
    public float $centerLng;

    /** Zoom level from 2 (world) to 18 (street). Use 3 for an ocean crossing, 8 for an archipelago, 13 for an anchorage. */
    public int $zoom = 12;

    /** @var list<MapMarker> Pinpoints to display: each has a lat, lng and a short human-readable title. */
    public array $markers = [];

    /** Draw the route joining the markers, in order (e.g. a passage). */
    public bool $drawRoute = false;

    /**
     * The `<twig:ux:map>` tag takes either a Map object or scalar/array parameters,
     * never both — building the object here keeps the component props flat while
     * handing UX Map the shape it expects.
     */
    public function getMap(): UxMap
    {
        $map = (new UxMap())
            ->center(new Point($this->centerLat, $this->centerLng))
            ->zoom((float) $this->zoom);

        foreach ($this->markers as $marker) {
            $map->addMarker(new Marker(
                position: new Point($marker->lat, $marker->lng),
                title: '' !== $marker->title ? $marker->title : null,
                // Popup content is injected as HTML client-side and these props come
                // from the model, so keep them text.
                infoWindow: null !== $marker->description ? new InfoWindow(
                    headerContent: htmlspecialchars($marker->title),
                    content: htmlspecialchars($marker->description),
                ) : null,
            ));
        }

        if ($this->drawRoute && \count($this->markers) > 1) {
            $map->addPolyline(new Polyline(
                points: array_map(static fn (MapMarker $m): Point => new Point($m->lat, $m->lng), $this->markers),
            ));
        }

        return $map;
    }
}
