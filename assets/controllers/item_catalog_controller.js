import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'searchInput', 'typeInput', 'state', 'list'];
    static values = {
        playersUrl: String,
        playersBaseUrl: String,
        initialPlayerId: String,
        initialType: String,
    };

    async connect() {
        this.loading = false;
        this.players = [];
        this.items = [];
        this.activeType = this.initialTypeValue || 'BOOK';
        this.activePlayerId = null;
        this.searchQuery = '';
        this.searchDebounceId = null;
        this.bindEvents();
        await this.loadPlayers();
    }

    bindEvents() {
        this.playerSelectTarget.addEventListener('change', async () => {
            this.activePlayerId = this.playerSelectTarget.value;
            this.syncPlayerQueryParam();
            await this.loadItems();
        });

        this.typeInputTargets.forEach((radio) => {
            radio.addEventListener('change', async () => {
                if (!radio.checked) {
                    return;
                }
                this.activeType = radio.value;
                await this.loadItems();
            });
        });

        this.searchInputTarget.addEventListener('input', async () => {
            if (this.searchDebounceId) {
                window.clearTimeout(this.searchDebounceId);
            }

            this.searchDebounceId = window.setTimeout(async () => {
                this.searchQuery = this.searchInputTarget.value.trim();
                await this.loadItems();
            }, 250);
        });
    }

    async loadPlayers() {
        this.setState('Chargement des players...');
        this.listTarget.innerHTML = '';

        const response = await fetch(this.playersUrlValue, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setState(`Impossible de charger les players (${response.status}).`);
            return;
        }

        const payload = await response.json();
        this.players = Array.isArray(payload) ? payload : [];
        if (this.players.length === 0) {
            this.setState('Aucun player. Cree ton premier personnage via l API /api/players.');
            return;
        }

        this.renderPlayerSelect();
        this.applyInitialPlayer();
        this.applyInitialType();
        await this.loadItems();
    }

    renderPlayerSelect() {
        this.playerSelectTarget.innerHTML = this.players
            .map((player) => `<option value="${player.id}">${this.escape(player.name)}</option>`)
            .join('');
    }

    applyInitialPlayer() {
        const fromQuery = new URLSearchParams(window.location.search).get('player');
        const candidate = fromQuery || this.initialPlayerIdValue || String(this.players[0].id);
        const found = this.players.find((player) => String(player.id) === String(candidate));
        const fallback = this.players[0];
        const selected = found || fallback;

        this.activePlayerId = String(selected.id);
        this.playerSelectTarget.value = this.activePlayerId;
        this.syncPlayerQueryParam();
    }

    applyInitialType() {
        const initial = this.activeType || 'BOOK';
        this.typeInputTargets.forEach((radio) => {
            radio.checked = radio.value === initial;
        });
    }

    async loadItems() {
        if (!this.activePlayerId) {
            this.setState('Aucun player selectionne.');
            return;
        }

        this.loading = true;
        this.renderItems();
        this.setState('Chargement des items...');

        const params = new URLSearchParams();
        params.set('type', this.activeType);
        if (this.searchQuery !== '') {
            params.set('q', this.searchQuery);
        }
        const itemsUrl = `${this.playersBaseUrlValue}/${this.activePlayerId}/items?${params.toString()}`;
        const response = await fetch(itemsUrl, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setState(`Erreur de chargement des items (${response.status}).`);
            this.loading = false;
            return;
        }

        const payload = await response.json();
        this.items = Array.isArray(payload) ? payload : [];
        this.loading = false;
        this.renderItems();
        const searchSuffix = this.searchQuery !== '' ? `, filtre: "${this.searchQuery}"` : '';
        this.setState(`${this.items.length} items (${this.activeType}) pour ce player${searchSuffix}.`);
    }

    async toggleLearned(itemId, learned) {
        if (!this.activePlayerId) {
            return;
        }

        const method = learned ? 'DELETE' : 'PUT';
        const url = `${this.playersBaseUrlValue}/${this.activePlayerId}/items/${itemId}/learned`;

        const response = await fetch(url, {
            method,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            this.setState(`Echec de mise a jour (${response.status}).`);
            return;
        }

        const index = this.items.findIndex((item) => Number(item.id) === Number(itemId));
        if (index !== -1) {
            this.items[index].learned = !learned;
        }
        this.renderItems();
    }

    renderItems() {
        if (this.loading) {
            this.listTarget.innerHTML = '';
            return;
        }

        if (this.items.length === 0) {
            this.listTarget.innerHTML = '';
            return;
        }

        this.listTarget.innerHTML = this.items
            .map((item) => {
                const metadata = item.type === 'MISC'
                    ? `Rank ${item.rank ?? '-'}`
                    : this.formatBookMeta(item);
                const label = this.escape(item.name || item.nameKey);
                const description = item.description ? `<p>${this.escape(item.description)}</p>` : '';
                const learnedClass = item.learned ? 'is-learned' : 'is-unlearned';
                const buttonText = item.learned ? 'Marquer non appris' : 'Marquer appris';
                const dataLearned = item.learned ? '1' : '0';

                return `
                    <li class="item-card ${learnedClass}">
                        <div class="item-card-head">
                            <strong>${label}</strong>
                            <span>${this.escape(item.type)}</span>
                        </div>
                        <p>${this.escape(metadata)}</p>
                        ${description}
                        <button type="button" data-item-id="${item.id}" data-learned="${dataLearned}">
                            ${this.escape(buttonText)}
                        </button>
                    </li>
                `;
            })
            .join('');

        this.listTarget.querySelectorAll('button[data-item-id]').forEach((button) => {
            button.addEventListener('click', async () => {
                const itemId = button.getAttribute('data-item-id');
                const learned = button.getAttribute('data-learned') === '1';
                if (!itemId) {
                    return;
                }
                await this.toggleLearned(itemId, learned);
            });
        });
    }

    formatBookMeta(item) {
        if (!Array.isArray(item.listNumbers) || item.listNumbers.length === 0) {
            return 'Liste inconnue';
        }

        const lists = item.listNumbers.join(', ');
        const suffix = item.isInSpecialList ? ' (inclut liste speciale)' : '';

        return `Listes ${lists}${suffix}`;
    }

    syncPlayerQueryParam() {
        if (!this.activePlayerId) {
            return;
        }
        const url = new URL(window.location.href);
        url.searchParams.set('player', this.activePlayerId);
        window.history.replaceState({}, '', url.toString());
    }

    setState(message) {
        this.stateTarget.textContent = message;
    }

    escape(value) {
        const normalized = String(value ?? '');

        return normalized
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}
