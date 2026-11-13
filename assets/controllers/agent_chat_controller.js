import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

/**
 * The navigator's console: renders what the ag-ui controller (the AG-UI client of
 * webmamba/ag-ui-bundle) dispatches. It speaks no protocol — every event arrives as an
 * `ag-ui:*` DOM event, wired in the template with data-action.
 *
 *  - text, reasoning      → streamed into the assistant's log line (text as markdown)
 *  - tool calls           → a "calling…" line, which is also the call's activity slot:
 *                           ag-ui-activities puts the server-rendered component in its place
 *  - the shared state     → the passage plan, rendered by the PassagePanel Live Component
 *  - the skipper edits it → passage:edited: what they did is said to the navigator,
 *                           as if typed — the interface is the conversation too
 *  - CUSTOM turbo_agent.* → re-dispatched as turbo-agent:<name> (theme, locale)
 *  - get_boat_position    → a frontend tool, registered on the client
 */
export default class extends Controller {
    static targets = ['messages', 'input', 'submit', 'placeholder', 'status', 'userTemplate', 'actionTemplate', 'assistantTemplate'];
    static values = { waiting: String, thinking: String, thought: String };

    static toolLabels = { get_boat_position: '🛰️' };

    connect() {
        this.texts = new Map();       // messageId → { el, markdown, frame }
        this.reasonings = new Map();  // messageId → <details>
        this.toolLines = new Map();   // toolCallId → its line
        this.waiting = false;
        this.defaultPlaceholder = this.inputTarget.placeholder;
    }

    /** ag-ui:connect — the client is ready: give it the tools the browser runs. */
    connected({ detail: { client } }) {
        client.registerTool('get_boat_position', {
            description: "Get the boat's current GPS position (latitude and longitude) from the skipper's device. Use it when the user says \"here\", \"where we are\", \"our position\"…",
            parameters: { type: 'object', properties: {} },
        }, () => new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not available in this browser.'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                ({ coords }) => resolve({ latitude: coords.latitude, longitude: coords.longitude, accuracyMeters: Math.round(coords.accuracy) }),
                (error) => reject(new Error(`The boat's position is unavailable: ${error.message}`)),
                { timeout: 10000 },
            );
        }));
    }

    /** Enter without Shift submits; Shift+Enter inserts a newline. */
    maybeSend(event) {
        if (event.shiftKey) return;
        event.preventDefault();
        event.target.form.requestSubmit();
    }

    /** Before ag-ui#send takes the input: the skipper's line, and the navigator's, empty. */
    send() {
        const text = this.inputTarget.value.trim();
        if (text === '') return;

        this.hidePlaceholder();
        this.appendUserBubble(text);
        this.bubble = this.appendAssistantBubble();
    }

    /**
     * passage:edited — the skipper changed the plan on the chart: the console says so,
     * and agent-chat:edited has ag-ui send it, with the new plan as the state. While the
     * navigator is busy, the new plan simply goes with the next run.
     */
    edited({ detail: { message } }) {
        if (!message || this.submitTarget.disabled) return;

        this.hidePlaceholder();
        const node = this.actionTemplateTarget.content.firstElementChild.cloneNode(true);
        node.querySelector('[data-role="text"]').textContent = message;
        this.messagesTarget.appendChild(node);
        this.bubble = this.appendAssistantBubble();
        this.dispatch('edited', { detail: { message } });
    }

    /** A button of an interrupt's component: show the choice, lock the others. */
    chose(event) {
        event.target.closest('[data-ag-ui-interrupt-id]')?.querySelectorAll('button').forEach((button) => { button.disabled = true; });
        event.target.classList.add('ring-2', 'ring-offset-2', 'ring-amber-400');
    }

    // — Lifecycle —

    runStarted() {
        this.waiting = false;
        this.setBusy(true);
    }

    runFinished({ detail: event }) {
        this.waiting = event.outcome?.type === 'interrupt';
    }

    runError({ detail: event }) {
        this.showError(event.message);
    }

    error({ detail: { message } }) {
        this.showError(message);
    }

    /** ag-ui:idle — the turn is over, or waits for the skipper. */
    idle() {
        this.bubble?.querySelectorAll('.streaming-cursor').forEach((el) => el.classList.remove('streaming-cursor'));
        this.setBusy(false);
        if (!this.waiting) this.inputTarget.focus();
        // For whoever reads answers out (the voice controller): the turn's text, as displayed.
        const text = Array.from(this.bubble?.querySelectorAll('.assistant-text') ?? [], (el) => el.textContent).join('\n');
        this.dispatch('answered', { detail: { text } });
    }

    // — Text —

    textStart({ detail: { messageId } }) {
        if (this.texts.has(messageId)) return;
        this.texts.set(messageId, { el: this.append('div', 'assistant-text streaming-cursor'), markdown: '', frame: null });
    }

    textContent({ detail: { messageId, delta } }) {
        const text = this.texts.get(messageId);
        if (!text) return;
        text.markdown += delta;
        // Re-render the whole message, at most once a frame: a half-written "**bold"
        // only becomes bold once its closing "**" arrives.
        text.frame ??= requestAnimationFrame(() => {
            text.frame = null;
            this.renderMarkdown(text);
        });
    }

    textEnd({ detail: { messageId } }) {
        const text = this.texts.get(messageId);
        if (!text) return;
        if (text.frame) cancelAnimationFrame(text.frame);
        text.frame = null;
        this.renderMarkdown(text);
        text.el.classList.remove('streaming-cursor');
    }

    // — Reasoning —

    reasoningStart({ detail: { messageId } }) {
        const details = this.append('details', 'reasoning my-2 text-xs text-slate-500 dark:text-slate-400');
        details.innerHTML = '<summary class="cursor-pointer select-none animate-pulse"></summary><div class="mt-1 pl-3 border-l-2 border-slate-200 dark:border-slate-600 whitespace-pre-wrap"></div>';
        details.querySelector('summary').textContent = `💭 ${this.thinkingValue}`;
        this.reasonings.set(messageId, details);
    }

    reasoningContent({ detail: { delta } }) {
        // A reasoning message sits inside the span opened last.
        Array.from(this.reasonings.values()).at(-1)?.querySelector('div').append(delta);
    }

    reasoningEnd({ detail: { messageId } }) {
        const summary = this.reasonings.get(messageId)?.querySelector('summary');
        if (!summary) return;
        summary.textContent = `💭 ${this.thoughtValue}`;
        summary.classList.remove('animate-pulse');
    }

    // — Tool calls —

    /** The line is the call's activity slot too: a component the call renders takes its place. */
    toolCallStart({ detail: { toolCallId, toolCallName } }) {
        const slot = this.append('div', '');
        slot.dataset.agUiActivity = `act_${toolCallId}`;

        const line = document.createElement('div');
        line.className = 'tool-call my-1 text-xs text-slate-400 italic flex items-center gap-2';
        const dot = document.createElement('span');
        dot.className = 'inline-block w-2.5 h-2.5 rounded-full bg-indigo-300 animate-pulse';
        const label = document.createElement('code');
        label.textContent = `${this.constructor.toolLabels[toolCallName] ?? ''} ${toolCallName}`.trim();
        line.append(dot, label);
        slot.append(line);

        this.toolLines.set(toolCallId, { line, name: toolCallName });
        this.scrollToBottom();
    }

    toolCallEnd({ detail: { toolCallId } }) {
        const call = this.toolLines.get(toolCallId);
        // A component is on its way: the line keeps pulsing until it takes its place.
        if (call && !call.name.startsWith('render_')) call.line.querySelector('span').classList.remove('animate-pulse');
    }

    activityChanged() {
        this.scrollToBottom();
    }

    // — Application events —

    /** CUSTOM is application space: names we don't know are ignored. */
    custom({ detail: event }) {
        if (!event.name?.startsWith('turbo_agent.')) return;
        const value = event.value ?? {};
        // The page changing is the evidence: no tool line left in the transcript.
        this.toolLines.get(value.toolCallId)?.line.remove();
        document.dispatchEvent(new CustomEvent(`turbo-agent:${event.name.slice('turbo_agent.'.length)}`, { detail: value }));
    }

    /** The PassagePanel Live Component renders the plan: hand it the new value, if it is one. */
    async stateChanged({ detail: { state } }) {
        const element = document.getElementById('passage-panel');
        if (!element) return;
        const component = await getComponent(element);
        const passage = state ?? { waypoints: [] };
        if (JSON.stringify(component.valueStore.get('passage')) === JSON.stringify(passage)) return;
        component.set('passage', passage, true);
    }

    // — Rendering —

    /** The model writes markdown: render it, and never trust its HTML. */
    renderMarkdown(text) {
        text.el.innerHTML = DOMPurify.sanitize(marked.parse(text.markdown, { gfm: true, breaks: true }));
        this.scrollToBottom();
    }

    append(tag, className) {
        this.bubble ??= this.appendAssistantBubble();
        const el = document.createElement(tag);
        el.className = className;
        this.bubble.appendChild(el);
        return el;
    }

    appendUserBubble(text) {
        const node = this.userTemplateTarget.content.firstElementChild.cloneNode(true);
        node.querySelector('[data-role="text"]').textContent = text;
        this.messagesTarget.appendChild(node);
        this.scrollToBottom();
    }

    /** @returns {HTMLElement} the bubble's body, where everything of the turn is appended */
    appendAssistantBubble() {
        const node = this.assistantTemplateTarget.content.firstElementChild.cloneNode(true);
        this.messagesTarget.appendChild(node);
        this.scrollToBottom();
        return node.querySelector('[data-role="body"]');
    }

    showError(message) {
        const el = this.append('div', 'mt-2 text-xs text-red-600');
        el.textContent = `⚠ ${message}`;
    }

    hidePlaceholder() {
        if (this.hasPlaceholderTarget) this.placeholderTarget.remove();
    }

    setBusy(busy) {
        const locked = busy || this.waiting;
        this.submitTarget.disabled = locked;
        this.inputTarget.disabled = locked;
        this.inputTarget.placeholder = this.waiting ? this.waitingValue : this.defaultPlaceholder;
        if (this.hasStatusTarget) {
            this.statusTarget.classList.toggle('animate-pulse', busy);
            this.statusTarget.classList.toggle('bg-amber-500', locked);
            this.statusTarget.classList.toggle('bg-emerald-500', !locked);
        }
    }

    scrollToBottom() {
        requestAnimationFrame(() => {
            this.messagesTarget.scrollTop = this.messagesTarget.scrollHeight;
        });
    }
}
