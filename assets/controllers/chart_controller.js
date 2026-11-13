import { Controller } from '@hotwired/stimulus';

/**
 * Styles the chart's lines before Leaflet draws them: the passage plan as a solid
 * route, the course to the next waypoint as a dashed line.
 *
 * And keeps the chart filling its panel: Leaflet measures its container once, so
 * any later change of layout (not only a window resize) must be told to it.
 */
export default class extends Controller {
    static styles = {
        route: { color: '#ea580c', weight: 3, opacity: 0.9 },
        course: { color: '#0ea5e9', weight: 2, dashArray: '6 8' },
    };

    connect() {
        // Leaflet already redraws on a window resize: report any resize of the panel
        // as one. Doesn't depend on the map being created before or after us.
        this.resizeObserver = new ResizeObserver(() => window.dispatchEvent(new Event('resize')));
        this.resizeObserver.observe(this.element);
    }

    disconnect() {
        this.resizeObserver.disconnect();
    }

    stylePolyline({ detail: { definition } }) {
        const style = this.constructor.styles[definition.extra?.style];
        if (style) definition.bridgeOptions = { ...definition.bridgeOptions, ...style };
    }
}
