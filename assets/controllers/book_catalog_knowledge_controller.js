import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'state', 'results'];
    static values = {
        playersUrl: String,
        playersBaseUrl: String,
        initialPlayerId: String,
        storageKey: String,
        locale: String,
        uiTranslations: String,
    };

    async connect() {
        this.players = [];
        this.learnedMap = new Map();
        this.activePlayerId = null;
        this.playerUiState = this.readPersistedState();
        this.translations = this.readUiTranslations();
        this.playersAbortController = null;
        this.itemsAbortController = null;
        this.bindEvents();
        await this.loadPlayers();
    }

    disconnect() {
        if (this.playersAbortController) {
            this.playersAbortController.abort();
        }

        if (this.itemsAbortController) {
            this.itemsAbortController.abort();
        }
    }

    bindEvents() {
        this.playerSelectTarget.addEventListener('change', async () => {
            this.activePlayerId = this.playerSelectTarget.value;
            this.saveActivePlayerId(this.activePlayerId);
            await this.loadLearnedItems();
        });

        this.resultsTarget.addEventListener('change', async (event) => {
            const target = event.target;
            if (!(target instanceof HTMLInputElement) || target.dataset.bookCheckbox !== '1') {
                return;
            }

            const itemId = String(target.dataset.bookId || '').trim();
            if (itemId === '' || this.activePlayerId === null) {
                target.checked = this.learnedMap.get(itemId) === true;
                return;
            }

            target.disabled = true;
            const previous = this.learnedMap.get(itemId) === true;
            const next = target.checked;

            const ok = await this.toggleLearned(itemId, next);
            if (!ok) {
                target.checked = previous;
            }

            target.disabled = false;
            this.applyLearnedState();
        });

        this.element.addEventListener('catalog-filters:updated', () => {
            this.applyLearnedState();
        });
    }

    async loadPlayers() {
        this.setState(this.t('loadingPlayers'));
        this.playerSelectTarget.innerHTML = '';
        this.playerSelectTarget.disabled = true;

        if (this.playersAbortController) {
            this.playersAbortController.abort();
        }
        this.playersAbortController = new AbortController();

        const response = await fetch(this.appendLocaleToUrl(this.playersUrlValue), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: this.playersAbortController.signal,
        }).catch(() => null);

        if (!(response instanceof Response)) {
            this.setState(this.t('cannotLoadPlayers'));
            this.disableCheckboxes();
            return;
        }

        if (!response.ok) {
            this.setState(`${this.t('cannotLoadPlayers')} (${response.status}).`);
            this.disableCheckboxes();
            return;
        }

        const payload = await response.json();
        this.players = Array.isArray(payload) ? payload : [];
        if (this.players.length === 0) {
            this.setState(this.t('noPlayers'));
            this.disableCheckboxes();
            return;
        }

        this.renderPlayerSelect();
        this.applyInitialPlayer();
        this.playerSelectTarget.disabled = false;
        await this.loadLearnedItems();
    }

    renderPlayerSelect() {
        this.playerSelectTarget.innerHTML = this.players
            .map((player) => `<option value="${this.escape(player.id)}">${this.escape(player.name)}</option>`)
            .join('');
    }

    applyInitialPlayer() {
        const fromStorage = this.getSavedActivePlayerId();
        const candidate = fromStorage || this.initialPlayerIdValue || String(this.players[0].id);
        const found = this.players.find((player) => String(player.id) === String(candidate));
        const selected = found || this.players[0];

        this.activePlayerId = String(selected.id);
        this.saveActivePlayerId(this.activePlayerId);
        this.playerSelectTarget.value = this.activePlayerId;
    }

    async loadLearnedItems() {
        if (this.activePlayerId === null || this.activePlayerId === '') {
            this.learnedMap = new Map();
            this.disableCheckboxes();
            this.setState(this.t('noSelectedPlayer'));
            return;
        }

        this.setState(this.t('loadingItems'));
        this.disableCheckboxes();

        if (this.itemsAbortController) {
            this.itemsAbortController.abort();
        }
        this.itemsAbortController = new AbortController();

        const itemsUrl = `${this.playersBaseUrlValue}/${this.activePlayerId}/items?type=BOOK`;
        const response = await fetch(this.appendLocaleToUrl(itemsUrl), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: this.itemsAbortController.signal,
        }).catch(() => null);

        if (!(response instanceof Response)) {
            this.setState(this.t('updateFailed'));
            return;
        }

        if (!response.ok) {
            this.setState(`${this.t('updateFailed')} (${response.status}).`);
            return;
        }

        const payload = await response.json();
        const learnedMap = new Map();
        if (Array.isArray(payload)) {
            payload.forEach((row) => {
                if (!row || typeof row !== 'object') {
                    return;
                }

                const itemId = typeof row.id === 'string' ? row.id : '';
                const learned = row.learned === true;
                if (itemId !== '') {
                    learnedMap.set(itemId, learned);
                }
            });
        }

        this.learnedMap = learnedMap;
        this.applyLearnedState();
        this.setState('');
    }

    applyLearnedState() {
        this.resultsTarget.querySelectorAll('[data-book-checkbox="1"]').forEach((node) => {
            if (!(node instanceof HTMLInputElement)) {
                return;
            }

            const itemId = String(node.dataset.bookId || '').trim();
            const learned = itemId !== '' && this.learnedMap.get(itemId) === true;
            const card = node.closest('.item-card');

            node.checked = learned;
            node.disabled = this.activePlayerId === null || this.activePlayerId === '';

            if (card instanceof HTMLElement) {
                card.classList.toggle('is-learned', learned);
                card.classList.toggle('is-unlearned', !learned);
            }
        });
    }

    disableCheckboxes() {
        this.resultsTarget.querySelectorAll('[data-book-checkbox="1"]').forEach((node) => {
            if (node instanceof HTMLInputElement) {
                node.disabled = true;
            }
        });
    }

    async toggleLearned(itemId, shouldBeLearned) {
        if (this.activePlayerId === null || this.activePlayerId === '') {
            return false;
        }

        const method = shouldBeLearned ? 'PUT' : 'DELETE';
        const url = `${this.playersBaseUrlValue}/${this.activePlayerId}/items/${itemId}/learned`;

        const response = await fetch(this.appendLocaleToUrl(url), {
            method,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        }).catch(() => null);

        if (!(response instanceof Response) || !response.ok) {
            this.setState(`${this.t('updateFailed')}${response instanceof Response ? ` (${response.status}).` : ''}`);
            return false;
        }

        this.learnedMap.set(itemId, shouldBeLearned);
        this.setState('');

        return true;
    }

    readPersistedState() {
        const key = String(this.storageKeyValue || '').trim();
        if (key === '') {
            return {};
        }

        try {
            const raw = window.localStorage.getItem(key);
            if (!raw) {
                return {};
            }

            const parsed = JSON.parse(raw);
            if (typeof parsed === 'object' && parsed !== null) {
                return parsed;
            }
        } catch {
            return {};
        }

        return {};
    }

    getSavedActivePlayerId() {
        const meta = this.playerUiState?.__meta;
        if (!meta || typeof meta !== 'object') {
            return '';
        }

        return typeof meta.activePlayerId === 'string' ? meta.activePlayerId : '';
    }

    saveActivePlayerId(playerId) {
        const key = String(this.storageKeyValue || '').trim();
        const player = String(playerId || '').trim();
        if (key === '' || player === '') {
            return;
        }

        if (!this.playerUiState.__meta || typeof this.playerUiState.__meta !== 'object') {
            this.playerUiState.__meta = {};
        }
        this.playerUiState.__meta.activePlayerId = player;

        try {
            window.localStorage.setItem(key, JSON.stringify(this.playerUiState));
        } catch {
            // Ignore storage errors.
        }
    }

    readUiTranslations() {
        try {
            const decoded = JSON.parse(this.uiTranslationsValue || '{}');
            if (typeof decoded === 'object' && decoded !== null) {
                return decoded;
            }
        } catch {
            return {};
        }

        return {};
    }

    t(key) {
        const value = this.translations?.[key];

        return typeof value === 'string' && value !== '' ? value : key;
    }

    setState(message) {
        if (!this.hasStateTarget) {
            return;
        }

        this.stateTarget.textContent = message;
        this.stateTarget.hidden = message === '';
    }

    appendLocaleToUrl(rawUrl) {
        const url = new URL(rawUrl, window.location.origin);
        const locale = String(this.localeValue || '').trim();
        if (locale !== '') {
            url.searchParams.set('locale', locale);
        }

        return url.toString();
    }

    escape(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
}
