import { Controller } from '@hotwired/stimulus';

/**
 * Real instruments never sit still: speed and wind flicker around their reading.
 * Purely cosmetic — the agent reads the boat, not this display.
 */
export default class extends Controller {
    static targets = ['fluctuating'];

    connect() {
        this.timer = setInterval(() => this.flicker(), 1200);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    flicker() {
        for (const el of this.fluctuatingTargets) {
            const value = parseFloat(el.dataset.value);
            const spread = parseFloat(el.dataset.spread ?? '0');
            el.textContent = (value + (Math.random() * 2 - 1) * spread).toFixed(1);
        }
    }
}
