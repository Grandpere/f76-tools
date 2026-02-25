import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.handleInput = this.handleInput.bind(this);
        this.textareas = Array.from(this.element.querySelectorAll('textarea[data-autosize="true"]'));

        this.textareas.forEach((textarea) => {
            this.resize(textarea);
            textarea.addEventListener('input', this.handleInput);
        });
    }

    disconnect() {
        if (!this.textareas) {
            return;
        }

        this.textareas.forEach((textarea) => {
            textarea.removeEventListener('input', this.handleInput);
        });
    }

    handleInput(event) {
        const target = event.target;
        if (!(target instanceof HTMLTextAreaElement)) {
            return;
        }

        this.resize(target);
    }

    resize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = `${textarea.scrollHeight}px`;
    }
}
