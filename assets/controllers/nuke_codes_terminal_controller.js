import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['head', 'codes', 'state'];
    static values = {
        apiUrl: String,
        locale: String,
        uiTranslations: String,
    };

    connect() {
        this.translations = this.readUiTranslations();
        this.expiresAt = null;
        this.intervalId = null;

        this.stateTarget.textContent = this.t('loading');
        this.load();
    }

    disconnect() {
        this.stopTicker();
    }

    async load() {
        try {
            const response = await fetch(this.apiUrlValue, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            this.render(payload);
            this.stateTarget.textContent = '';
            this.startTicker();
        } catch {
            this.stateTarget.textContent = this.t('loadFailed');
            this.headTarget.innerHTML = '';
            this.codesTarget.innerHTML = '';
        }
    }

    render(payload) {
        this.expiresAt = this.parseDate(payload.expiresAt);
        const validFrom = this.computeValidFrom(this.expiresAt);
        const validTo = this.computeValidTo(this.expiresAt);
        const staleTag = payload.stale ? `<span class="nuke-stale-badge">${this.escapeHtml(this.t('stale'))}</span>` : '';

        this.headTarget.innerHTML = `
            <div class="nuke-terminal-line">&gt; ${this.escapeHtml(this.t('title'))}</div>
            <div class="nuke-terminal-line">&gt; ${this.escapeHtml(this.t('validFrom'))} <strong>${this.escapeHtml(this.formatDateTime(validFrom))}</strong></div>
            <div class="nuke-terminal-line">&gt; ${this.escapeHtml(this.t('validTo'))} <strong>${this.escapeHtml(this.formatDateTime(validTo))}</strong></div>
            <div class="nuke-terminal-line">&gt; ${this.escapeHtml(this.t('accessingSilos'))} <span class="cursor">&nbsp;</span> ${staleTag}</div>
            <div class="nuke-terminal-line nuke-countdown-line">${this.escapeHtml(this.t('resetIn'))} <strong data-nuke-codes-terminal-target="countdown"></strong></div>
        `;

        this.codesTarget.innerHTML = `
            <div class="nuke-code-block"><small>${this.escapeHtml(this.t('alpha'))}</small><br>${this.escapeHtml(this.formatCode(payload.alpha))}</div>
            <div class="nuke-code-block"><small>${this.escapeHtml(this.t('bravo'))}</small><br>${this.escapeHtml(this.formatCode(payload.bravo))}</div>
            <div class="nuke-code-block"><small>${this.escapeHtml(this.t('charlie'))}</small><br>${this.escapeHtml(this.formatCode(payload.charlie))}</div>
        `;

        this.renderCountdown();
    }

    startTicker() {
        this.stopTicker();
        this.intervalId = window.setInterval(() => {
            this.renderCountdown();
        }, 1000);
    }

    stopTicker() {
        if (this.intervalId !== null) {
            window.clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    renderCountdown() {
        const node = this.headTarget.querySelector('[data-nuke-codes-terminal-target="countdown"]');
        if (!node) {
            return;
        }

        if (!(this.expiresAt instanceof Date) || Number.isNaN(this.expiresAt.getTime())) {
            node.textContent = '—';
            return;
        }

        const seconds = Math.max(0, Math.floor((this.expiresAt.getTime() - Date.now()) / 1000));
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;

        node.textContent = `${days}d ${hours}h ${minutes}m ${remainingSeconds}s`;

        if (seconds === 0) {
            this.stopTicker();
            this.load();
        }
    }

    computeValidFrom(expiresAt) {
        if (!(expiresAt instanceof Date) || Number.isNaN(expiresAt.getTime())) {
            return null;
        }

        return new Date(expiresAt.getTime() - 7 * 24 * 60 * 60 * 1000);
    }

    computeValidTo(expiresAt) {
        if (!(expiresAt instanceof Date) || Number.isNaN(expiresAt.getTime())) {
            return null;
        }

        return new Date(expiresAt.getTime() - 1000);
    }

    parseDate(value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return null;
        }

        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    formatCode(value) {
        const digits = String(value ?? '').replace(/\D+/g, '');
        if (digits.length !== 8) {
            return String(value ?? '');
        }

        return `${digits.slice(0, 3)} ${digits.slice(3, 5)} ${digits.slice(5, 8)}`;
    }

    formatDateTime(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
            return '—';
        }

        const formatter = new Intl.DateTimeFormat(this.localeValue || 'en', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'UTC',
        });

        return formatter.format(date);
    }

    readUiTranslations() {
        const raw = this.uiTranslationsValue;
        if (!raw || typeof raw !== 'string') {
            return {};
        }

        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch {
            return {};
        }
    }

    t(key) {
        const value = this.translations?.[key];
        return typeof value === 'string' && value.trim() !== '' ? value : key;
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
