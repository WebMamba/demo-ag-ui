import { Controller } from '@hotwired/stimulus';

const SpeechRecognition = window.SpeechRecognition ?? window.webkitSpeechRecognition;
const LANGUAGES = { fr: 'fr-FR', en: 'en-GB' };

/**
 * Talk to the navigator, hands on the helm.
 *
 * The browser transcribes (Web Speech API), the words land in the input, and the form
 * is submitted: to the agent, a spoken question is a typed one. A question asked out
 * loud is answered out loud.
 */
export default class extends Controller {
    static targets = ['button', 'input'];
    static values = { listening: String };

    connect() {
        if (!SpeechRecognition) return;

        this.buttonTarget.classList.replace('hidden', 'inline-flex');
        this.spoken = false;
    }

    disconnect() {
        this.recognition?.abort();
        window.speechSynthesis?.cancel();
    }

    toggle() {
        this.recognition ? this.recognition.stop() : this.listen();
    }

    listen() {
        if (this.inputTarget.disabled) return;
        window.speechSynthesis?.cancel();

        const recognition = new SpeechRecognition();
        recognition.lang = this.language();
        recognition.interimResults = true;
        recognition.continuous = false;

        const placeholder = this.inputTarget.placeholder;
        this.inputTarget.placeholder = this.listeningValue;
        this.buttonTarget.dataset.listening = '';

        let transcript = '';
        recognition.onresult = (event) => {
            transcript = Array.from(event.results, (result) => result[0].transcript).join('');
            this.inputTarget.value = transcript;
        };
        recognition.onend = () => {
            this.recognition = null;
            delete this.buttonTarget.dataset.listening;
            this.inputTarget.placeholder = placeholder;

            if (transcript.trim() !== '') {
                this.spoken = true;
                this.element.requestSubmit();
            }
        };
        recognition.onerror = (event) => console.warn('Speech recognition:', event.error);

        this.recognition = recognition;
        recognition.start();
    }

    /** The navigator answered: read it out, if the question was spoken. */
    speak({ detail: { text } }) {
        if (!this.spoken || !window.speechSynthesis || !text) return;
        this.spoken = false;

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = this.language();
        utterance.voice = speechSynthesis.getVoices().find((voice) => voice.lang === utterance.lang) ?? null;
        speechSynthesis.speak(utterance);
    }

    /** Follows the page, which follows the set_locale effect. */
    language() {
        const lang = document.documentElement.lang.slice(0, 2);

        return LANGUAGES[lang] ?? lang;
    }
}
