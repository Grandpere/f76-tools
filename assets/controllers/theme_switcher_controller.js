import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'f76-theme';
const THEMES = new Set(['default', 'terminal']);

export default class extends Controller {
    static targets = ['toggle'];

    connect() {
        const storedTheme = this.readStoredTheme();
        this.applyTheme(storedTheme);
        this.syncAllToggles(storedTheme);
    }

    toggle(event) {
        const isChecked = event?.target instanceof HTMLInputElement ? event.target.checked : false;
        const theme = isChecked ? 'terminal' : 'default';
        this.applyTheme(theme);
        this.storeTheme(theme);
        this.syncAllToggles(theme);
    }

    applyTheme(theme) {
        const normalized = this.normalizeTheme(theme);
        document.documentElement.setAttribute('data-theme', normalized);

        if (this.hasToggleTarget) {
            this.toggleTarget.checked = normalized === 'terminal';
        }
    }

    syncAllToggles(theme) {
        const normalized = this.normalizeTheme(theme);
        document.querySelectorAll('[data-theme-switcher-target="toggle"]').forEach((node) => {
            if (node instanceof HTMLInputElement && node.type === 'checkbox') {
                node.checked = normalized === 'terminal';
            }
        });
    }

    readStoredTheme() {
        try {
            const value = localStorage.getItem(STORAGE_KEY);
            const normalized = this.normalizeTheme(value);
            if (normalized !== value) {
                this.storeTheme(normalized);
            }

            return normalized;
        } catch {
            return 'default';
        }
    }

    storeTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, this.normalizeTheme(theme));
        } catch {
            // Ignore storage failures.
        }
    }

    normalizeTheme(theme) {
        if (theme === 'official') {
            return 'default';
        }

        return THEMES.has(theme) ? theme : 'default';
    }
}
