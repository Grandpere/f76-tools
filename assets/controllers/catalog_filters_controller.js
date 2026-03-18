import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['searchInput'];

    connect() {
        this.searchDebounceId = null;
        this.bindEvents();
    }

    disconnect() {
        if (this.searchDebounceId) {
            window.clearTimeout(this.searchDebounceId);
        }
    }

    bindEvents() {
        this.element.querySelectorAll('input[type="checkbox"], input[type="radio"], select').forEach((field) => {
            field.addEventListener('change', () => {
                this.submit();
            });
        });

        if (this.hasSearchInputTarget) {
            this.searchInputTarget.addEventListener('input', () => {
                if (this.searchDebounceId) {
                    window.clearTimeout(this.searchDebounceId);
                }

                this.searchDebounceId = window.setTimeout(() => {
                    this.submit();
                }, 250);
            });
        }
    }

    submit() {
        this.element.requestSubmit();
    }
}
