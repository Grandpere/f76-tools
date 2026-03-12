import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'prompt', 'toast'];
    static values = {
        copiedLabel: { type: String, default: 'Copied' },
    };

    connect() {
        this.modalTarget.setAttribute('hidden', 'hidden');
        this.hideToast();
    }

    open() {
        this.hideToast();
        this.modalTarget.removeAttribute('hidden');
    }

    close() {
        this.hideToast();
        this.modalTarget.setAttribute('hidden', 'hidden');
    }

    backdropClose(event) {
        if (event.target === this.modalTarget) {
            this.close();
        }
    }

    async copy(event) {
        const button = event.currentTarget;
        const original = button.textContent;
        await this.copyToClipboard();
        button.textContent = this.copiedLabelValue;
        this.showToast(this.copiedLabelValue);

        setTimeout(() => {
            button.textContent = original;
            this.close();
        }, 1200);
    }

    async copyToClipboard() {
        const content = this.promptTarget.value;

        try {
            await navigator.clipboard.writeText(content);
        } catch (error) {
            this.promptTarget.focus();
            this.promptTarget.select();
            document.execCommand('copy');
        }
    }

    showToast(message) {
        this.toastTarget.textContent = message;
        this.toastTarget.removeAttribute('hidden');
        clearTimeout(this.toastTimeout);
        this.toastTimeout = setTimeout(() => this.hideToast(), 1200);
    }

    hideToast() {
        this.toastTarget.setAttribute('hidden', 'hidden');
    }
}
