import { Controller } from '@hotwired/stimulus';

/*
 * The basic chat's only JavaScript: Turbo submits the form and appends the answer,
 * this just shows that the agent is working, and tidies up once it has answered.
 */
export default class extends Controller {
    static targets = ['messages', 'input', 'thinking'];

    sending() {
        this.thinkingTarget.classList.remove('hidden');
        this.inputTarget.readOnly = true;
    }

    sent(event) {
        this.thinkingTarget.classList.add('hidden');
        this.inputTarget.readOnly = false;

        if (event.detail.success) {
            this.inputTarget.value = '';
            this.inputTarget.focus();
        }

        this.thinkingTarget.scrollIntoView({ block: 'end' });
    }
}
