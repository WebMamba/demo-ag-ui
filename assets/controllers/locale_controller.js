import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/**
 * Switches the page's language when the agent changes it.
 *
 * Translated markup can only come from the server: it arrives as a <turbo-stream>,
 * which performs its action the moment it enters the document — no navigation, so the
 * conversation is untouched. <html lang> is the part with no markup, which assistive
 * technology reads. Live Components marked data-follows-locale re-render in it: the
 * language lives in no session, so their re-render request carries it as a prop.
 */
export default class extends Controller {
    connect() {
        this.onLocale = (event) => this.apply(event.detail);
        document.addEventListener('turbo-agent:locale', this.onLocale);
    }

    disconnect() {
        document.removeEventListener('turbo-agent:locale', this.onLocale);
    }

    apply({ locale, html }) {
        if (locale) document.documentElement.lang = locale;
        if (html) document.body.insertAdjacentHTML('beforeend', html);
        if (!locale) return;

        document.querySelectorAll('[data-follows-locale]').forEach(async (element) => {
            (await getComponent(element)).set('locale', locale, true);
        });
    }
}
