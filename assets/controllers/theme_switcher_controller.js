import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'f76-theme';
const THEMES = new Set(['default', 'official', 'terminal']);

export default class extends Controller {
    static targets = ['select'];

    connect() {
        this.applyTheme(this.readStoredTheme());
    }

    change(event) {
        const value = typeof event?.target?.value === 'string' ? event.target.value : 'default';
        this.applyTheme(value);
        this.storeTheme(value);
        this.syncAllSelects(value);
    }

    applyTheme(theme) {
        const normalized = THEMES.has(theme) ? theme : 'default';
        document.documentElement.setAttribute('data-theme', normalized);

        if (this.hasSelectTarget) {
            this.selectTarget.value = normalized;
        }
    }

    syncAllSelects(theme) {
        document.querySelectorAll('[data-theme-switcher-target="select"]').forEach((node) => {
            if (node instanceof HTMLSelectElement) {
                node.value = theme;
            }
        });
    }

    readStoredTheme() {
        try {
            const value = localStorage.getItem(STORAGE_KEY);
            return THEMES.has(value) ? value : 'default';
        } catch {
            return 'default';
        }
    }

    storeTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, THEMES.has(theme) ? theme : 'default');
        } catch {
            // Ignore storage failures.
        }
    }
}
