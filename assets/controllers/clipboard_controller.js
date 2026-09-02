import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['source', 'button', 'feedback'];

    async copy(event) {
        event.preventDefault();
        const text = this.hasSourceTarget ? this.sourceTarget.value || this.sourceTarget.textContent : '';
        if (!text) return;

        try {
            await navigator.clipboard.writeText(text.trim());
            this.showFeedback();
        } catch (err) {
            console.error('Failed to copy text', err);
        }
    }

    showFeedback() {
        if (this.hasButtonTarget) {
            const originalText = this.buttonTarget.textContent;
            this.buttonTarget.textContent = '✓ Copié !';
            this.buttonTarget.classList.add('copied');
            setTimeout(() => {
                this.buttonTarget.textContent = originalText;
                this.buttonTarget.classList.remove('copied');
            }, 2000);
        }
        if (this.hasFeedbackTarget) {
            this.feedbackTarget.hidden = false;
            setTimeout(() => {
                this.feedbackTarget.hidden = true;
            }, 2000);
        }
    }
}
