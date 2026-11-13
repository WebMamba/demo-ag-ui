import { Controller } from '@hotwired/stimulus';

/**
 * Owns the theme classes on <html>: `dark`, plus `night` for night-watch mode.
 *
 * Knows nothing about the agent: it listens for a theme change, whoever caused it.
 * The server renders the classes on first load, so this only has to handle changes.
 */
export default class extends Controller {
    connect() {
        this.onTheme = (event) => this.apply(event.detail);
        document.addEventListener('turbo-agent:theme', this.onTheme);
    }

    disconnect() {
        document.removeEventListener('turbo-agent:theme', this.onTheme);
    }

    apply({ theme }) {
        // Night watch is dark mode seen through a red filter.
        document.documentElement.classList.toggle('dark', theme === 'dark' || theme === 'night');
        document.documentElement.classList.toggle('night', theme === 'night');
    }
}
