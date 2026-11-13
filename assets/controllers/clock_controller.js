import { Controller } from '@hotwired/stimulus';

/** The plotter's UTC clock: on passage, ship's time is UTC. */
export default class extends Controller {
    static targets = ['time'];

    connect() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        clearInterval(this.timer);
    }

    tick() {
        this.timeTarget.textContent = new Date().toISOString().slice(11, 19);
    }
}
