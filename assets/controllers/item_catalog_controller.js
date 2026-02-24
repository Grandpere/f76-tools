import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'createNameInput', 'createButton', 'searchInput', 'state', 'miscList', 'bookList'];
    static values = {
        playersUrl: String,
        playersBaseUrl: String,
        initialPlayerId: String,
    };

    async connect() {
        this.players = [];
        this.items = [];
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

        this.createButtonTarget.addEventListener('click', async () => {
            await this.createPlayerFromInput();
        });

        this.createNameInputTarget.addEventListener('keydown', async (event) => {
            if (event.key !== 'Enter') {
                return;
            }
            event.preventDefault();
            await this.createPlayerFromInput();
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
        this.miscListTarget.innerHTML = '';
        this.bookListTarget.innerHTML = '';

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
        await this.loadItems();
    }

    async createPlayerFromInput() {
        const name = this.createNameInputTarget.value.trim();
        if (name === '') {
            this.setState('Le nom du player est requis.');
            return;
        }

        this.createButtonTarget.disabled = true;
        this.setState('Creation du player...');

        const response = await fetch(this.playersUrlValue, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ name }),
        });

        this.createButtonTarget.disabled = false;

        if (!response.ok) {
            if (response.status === 409) {
                this.setState('Un player avec ce nom existe deja.');
                return;
            }
            this.setState(`Echec creation player (${response.status}).`);
            return;
        }

        const created = await response.json();
        const createdId = String(created.id ?? '');
        this.createNameInputTarget.value = '';
        await this.loadPlayers();
        if (createdId !== '') {
            const found = this.players.find((player) => String(player.id) === createdId);
            if (found) {
                this.activePlayerId = createdId;
                this.playerSelectTarget.value = createdId;
                this.syncPlayerQueryParam();
                await this.loadItems();
            }
        }
        this.setState(`Player cree: ${name}.`);
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

    async loadItems() {
        if (!this.activePlayerId) {
            this.setState('Aucun player selectionne.');
            return;
        }

        this.renderItems();
        this.setState('Chargement des items...');

        const params = new URLSearchParams();
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
            return;
        }

        const payload = await response.json();
        this.items = Array.isArray(payload) ? payload : [];
        this.renderItems();
        const searchSuffix = this.searchQuery !== '' ? `, filtre: "${this.searchQuery}"` : '';
        this.setState(`${this.items.length} items pour ce player${searchSuffix}.`);
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

        this.items = this.items.map((item) => (Number(item.id) === Number(itemId)
            ? { ...item, learned: !learned }
            : item));
        this.renderItems();
    }

    renderItems() {
        if (this.items.length === 0) {
            this.miscListTarget.innerHTML = '<p>Aucun legendary mod trouve.</p>';
            this.bookListTarget.innerHTML = '<p>Aucun plan Minerva trouve.</p>';
            return;
        }
        this.miscListTarget.innerHTML = this.renderMiscBlock();
        this.bookListTarget.innerHTML = this.renderBookBlock();
        this.bindToggleButtons(this.miscListTarget);
        this.bindToggleButtons(this.bookListTarget);
    }

    renderMiscBlock() {
        const miscItems = this.items.filter((item) => item.type === 'MISC');
        if (miscItems.length === 0) {
            return '<p>Aucun legendary mod trouve.</p>';
        }

        const rankMap = new Map();
        miscItems.forEach((item) => {
            const rank = Number.isInteger(item.rank) ? Number(item.rank) : 0;
            if (!rankMap.has(rank)) {
                rankMap.set(rank, []);
            }
            rankMap.get(rank).push(item);
        });

        const ranks = Array.from(rankMap.keys()).sort((a, b) => a - b);
        return ranks.map((rank) => `
            <section class="catalog-subgroup">
                <h3>Rank ${this.escape(rank)}</h3>
                <ul class="item-grid">${rankMap.get(rank).map((item) => this.renderItemCard(item)).join('')}</ul>
            </section>
        `).join('');
    }

    renderBookBlock() {
        const books = this.items.filter((item) => item.type === 'BOOK');
        if (books.length === 0) {
            return '<p>Aucun plan Minerva trouve.</p>';
        }

        const listMap = new Map([
            [1, []],
            [2, []],
            [3, []],
            [4, []],
        ]);

        books.forEach((item) => {
            const listNumbers = Array.isArray(item.listNumbers) ? item.listNumbers : [];
            if (listNumbers.length === 0) {
                listMap.get(1).push(item);
                return;
            }
            listNumbers.forEach((listNumber) => {
                const numericList = Number(listNumber);
                if (!listMap.has(numericList)) {
                    listMap.set(numericList, []);
                }
                listMap.get(numericList).push(item);
            });
        });

        return Array.from(listMap.entries())
            .sort((a, b) => a[0] - b[0])
            .map(([listNumber, items]) => `
                <section class="catalog-subgroup">
                    <h3>Liste ${this.escape(listNumber)}</h3>
                    <ul class="item-grid">${items.map((item) => this.renderItemCard(item, `Liste ${listNumber}`)).join('')}</ul>
                </section>
            `)
            .join('');
    }

    renderItemCard(item, subtitle = null) {
        const label = this.escape(item.name || item.nameKey);
        const description = item.description ? `<p>${this.escape(item.description)}</p>` : '';
        const learnedClass = item.learned ? 'is-learned' : 'is-unlearned';
        const buttonText = item.learned ? 'Marquer non appris' : 'Marquer appris';
        const dataLearned = item.learned ? '1' : '0';
        const subtitleText = subtitle || this.escape(item.type);

        return `
            <li class="item-card ${learnedClass}">
                <div class="item-card-head">
                    <strong>${label}</strong>
                    <span>${subtitleText}</span>
                </div>
                ${description}
                <button type="button" data-item-id="${item.id}" data-learned="${dataLearned}">
                    ${this.escape(buttonText)}
                </button>
            </li>
        `;
    }

    bindToggleButtons(container) {
        container.querySelectorAll('button[data-item-id]').forEach((button) => {
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
