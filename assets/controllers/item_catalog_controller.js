import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'createNameInput', 'createButton', 'searchInput', 'sourceFilter', 'state', 'miscList', 'bookList'];
    static values = {
        playersUrl: String,
        playersBaseUrl: String,
        initialPlayerId: String,
    };

    async connect() {
        this.players = [];
        this.items = [];
        this.activePlayerId = null;
        this.activeLocale = this.readActiveLocale();
        this.searchQuery = '';
        this.searchDebounceId = null;
        this.activeSourceFilters = [];
        this.openMiscGroup = null;
        this.openBookGroup = null;
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

        this.sourceFilterTargets.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                this.activeSourceFilters = this.sourceFilterTargets
                    .filter((node) => node.checked)
                    .map((node) => node.value);
                this.renderItems();
                this.updateStateCounter();
            });
        });
    }

    async loadPlayers() {
        this.setState('Chargement des personnages...');
        this.miscListTarget.innerHTML = '';
        this.bookListTarget.innerHTML = '';

        const response = await fetch(this.appendLocaleToUrl(this.playersUrlValue), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setState(`Impossible de charger les personnages (${response.status}).`);
            return;
        }

        const payload = await response.json();
        this.players = Array.isArray(payload) ? payload : [];
        if (this.players.length === 0) {
            this.setState('Aucun personnage. Cree ton premier personnage via l API /api/players.');
            return;
        }

        this.renderPlayerSelect();
        this.applyInitialPlayer();
        await this.loadItems();
    }

    async createPlayerFromInput() {
        const name = this.createNameInputTarget.value.trim();
        if (name === '') {
            this.setState('Le nom du personnage est requis.');
            return;
        }

        this.createButtonTarget.disabled = true;
        this.setState('Creation du personnage...');

        const response = await fetch(this.appendLocaleToUrl(this.playersUrlValue), {
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
                this.setState('Un personnage avec ce nom existe deja.');
                return;
            }
            this.setState(`Echec de creation du personnage (${response.status}).`);
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
        this.setState(`Personnage cree: ${name}.`);
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
            this.setState('Aucun personnage selectionne.');
            return;
        }

        this.renderItems();
        this.setState('Chargement des items...');

        const params = new URLSearchParams();
        if (this.searchQuery !== '') {
            params.set('q', this.searchQuery);
        }
        const itemsUrl = `${this.playersBaseUrlValue}/${this.activePlayerId}/items?${params.toString()}`;
        const response = await fetch(this.appendLocaleToUrl(itemsUrl), {
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
        this.updateStateCounter();
    }

    async toggleLearned(itemId, shouldBeLearned) {
        if (!this.activePlayerId) {
            return;
        }

        const method = shouldBeLearned ? 'PUT' : 'DELETE';
        const url = `${this.playersBaseUrlValue}/${this.activePlayerId}/items/${itemId}/learned`;

        const response = await fetch(this.appendLocaleToUrl(url), {
            method,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            this.setState(`Echec de mise a jour (${response.status}).`);
            return;
        }

        this.items = this.items.map((item) => (Number(item.id) === Number(itemId)
            ? { ...item, learned: shouldBeLearned }
            : item));
        this.renderItems();
    }

    renderItems() {
        const visibleItems = this.getVisibleItems();
        if (visibleItems.length === 0) {
            this.miscListTarget.innerHTML = '<p>Aucun mod legendaire trouve.</p>';
            this.bookListTarget.innerHTML = '<p>Aucun plan Minerva trouve.</p>';
            return;
        }
        this.miscListTarget.innerHTML = this.renderMiscBlock(visibleItems);
        this.bookListTarget.innerHTML = this.renderBookBlock(visibleItems);
        this.bindToggleButtons(this.miscListTarget);
        this.bindToggleButtons(this.bookListTarget);
        this.bindAccordionBehavior(this.miscListTarget, 'misc');
        this.bindAccordionBehavior(this.bookListTarget, 'book');
    }

    renderMiscBlock(items) {
        const miscItems = items.filter((item) => item.type === 'MISC');
        if (miscItems.length === 0) {
            return '<p>Aucun mod legendaire trouve.</p>';
        }

        const rankMap = new Map();
        miscItems.forEach((item) => {
            const rank = Number.isInteger(item.rank) ? Number(item.rank) : 0;
            if (!rankMap.has(rank)) {
                rankMap.set(rank, []);
            }
            rankMap.get(rank).push(item);
        });
        rankMap.forEach((items, rank) => {
            rankMap.set(rank, this.sortItemsByName(items));
        });

        const ranks = Array.from(rankMap.keys()).sort((a, b) => a - b);
        if (this.openMiscGroup === null || !ranks.includes(this.openMiscGroup)) {
            this.openMiscGroup = ranks[0] ?? null;
        }
        return ranks.map((rank, index) => `
            <details class="catalog-subgroup" data-accordion-kind="misc" data-accordion-id="${rank}" ${rank === this.openMiscGroup ? 'open' : ''}>
                <summary>Rang ${this.escape(rank)}</summary>
                <ul class="item-grid">${rankMap.get(rank).map((item) => this.renderItemCard(item)).join('')}</ul>
            </details>
        `).join('');
    }

    renderBookBlock(items) {
        const books = items.filter((item) => item.type === 'BOOK');
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

        const groups = Array.from(listMap.entries()).sort((a, b) => a[0] - b[0]);
        const listNumbers = groups.map(([listNumber]) => listNumber);
        if (this.openBookGroup === null || !listNumbers.includes(this.openBookGroup)) {
            this.openBookGroup = listNumbers[0] ?? null;
        }

        const groupsHtml = groups
            .sort((a, b) => a[0] - b[0])
            .map(([listNumber, items]) => `
                <details class="catalog-subgroup" data-accordion-kind="book" data-accordion-id="${listNumber}" ${listNumber === this.openBookGroup ? 'open' : ''}>
                    <summary>Liste ${this.escape(listNumber)}</summary>
                    <ul class="item-grid">${items.map((item) => this.renderItemCard(item)).join('')}</ul>
                </details>
            `)
            .join('');

        return `
            <p class="catalog-note">Info: un plan present dans plusieurs listes partage le meme etat appris. Cocher une occurrence met a jour toutes les autres.</p>
            ${groupsHtml}
        `;
    }

    renderItemCard(item) {
        const label = this.escape(item.name || item.nameKey);
        const description = item.description ? `<p>${this.escape(item.description)}</p>` : '';
        const infoBlock = this.renderInfoBlock(item);
        const iconsFooter = this.renderIconsFooter(item);
        const dailyOpsLine = item.type === 'BOOK' && item.dropDailyOps && !this.infoContainsDailyOps(item.infoHtml)
            ? '<p class="item-extra-line">Disponible aussi en recompense d une operation quotidienne reussie.</p>'
            : '';
        const priceBlock = this.renderPriceBlock(item);
        const learnedClass = item.learned ? 'is-learned' : 'is-unlearned';
        const checkedAttr = item.learned ? 'checked' : '';
        const newBadge = item.isNew ? '<span class="item-badge-new">NEW</span>' : '';

        return `
            <li class="item-card ${learnedClass}">
                <input class="item-learned-checkbox" type="checkbox" aria-label="Objet appris" data-item-checkbox="1" data-item-id="${item.id}" ${checkedAttr}>
                <div class="item-card-head">
                    <strong>${label}</strong>
                    ${newBadge}
                </div>
                ${priceBlock}
                ${description}
                ${infoBlock}
                ${dailyOpsLine}
                ${iconsFooter}
            </li>
        `;
    }

    renderIconsFooter(item) {
        const relationsBlock = this.renderRelationsBlock(item);
        const sourceIcons = this.renderDropSources(item);
        const extraSourceIcons = this.renderExtraSourceIcons(item);

        if (relationsBlock === '' && sourceIcons === '' && extraSourceIcons === '') {
            return '';
        }

        return `<div class="item-icons-footer">${relationsBlock}${sourceIcons}${extraSourceIcons}</div>`;
    }

    renderPriceBlock(item) {
        if (item.type !== 'BOOK') {
            return '';
        }

        const basePrice = Number.isInteger(item.price) ? item.price : null;
        const minervaPrice = Number.isInteger(item.priceMinerva) ? item.priceMinerva : null;
        const hasDiscount = basePrice !== null && minervaPrice !== null && minervaPrice < basePrice;
        const displayPrice = minervaPrice ?? basePrice;
        const displayPriceLabel = displayPrice === null ? '-' : this.escape(displayPrice);
        const oldPriceLabel = basePrice === null ? null : this.escape(basePrice);

        if (hasDiscount) {
            return `
                <p class="item-prices item-prices-discount">
                    <img src="/assets/icons/Fo76_Icon_Gold_Bullion.png" alt="Lingot d or">
                    <span class="price-old">${oldPriceLabel}</span>
                    <span class="price-new">${this.escape(minervaPrice)}</span>
                </p>
            `;
        }

        return `
            <p class="item-prices">
                <img src="/assets/icons/Fo76_Icon_Gold_Bullion.png" alt="Lingot d or">
                <span class="price-new">${displayPriceLabel}</span>
            </p>
        `;
    }

    sortItemsByName(items) {
        return [...items].sort((a, b) => {
            const aName = String(a.name || a.nameKey || '').toLocaleLowerCase();
            const bName = String(b.name || b.nameKey || '').toLocaleLowerCase();

            return aName.localeCompare(bName, 'fr');
        });
    }

    getVisibleItems() {
        if (this.activeSourceFilters.length === 0) {
            return this.items;
        }

        return this.items.filter((item) => this.activeSourceFilters.every((filterKey) => item[filterKey] === true));
    }

    updateStateCounter() {
        const visibleCount = this.getVisibleItems().length;
        const searchSuffix = this.searchQuery !== '' ? `, filtre texte: "${this.searchQuery}"` : '';
        const sourceSuffix = this.activeSourceFilters.length > 0
            ? `, filtres sources: ${this.activeSourceFilters.join(', ')}`
            : '';

        this.setState(`${visibleCount} items visibles${searchSuffix}${sourceSuffix}.`);
    }

    renderInfoBlock(item) {
        if (!item.infoHtml) {
            return '';
        }

        return `<div class="item-info-html">${this.sanitizeHtml(item.infoHtml)}</div>`;
    }

    renderRelationsBlock(item) {
        if (item.type !== 'MISC' || !item.relationsHtml) {
            return '';
        }

        return `<div class="item-relations-html">${this.sanitizeHtml(item.relationsHtml)}</div>`;
    }

    infoContainsDailyOps(infoHtml) {
        if (!infoHtml || typeof infoHtml !== 'string') {
            return false;
        }

        const plain = infoHtml.replace(/<[^>]*>/g, ' ').toLowerCase();

        return plain.includes('daily operation');
    }

    renderDropSources(item) {
        if (!item.dropSourcesHtml) {
            return '';
        }

        return `<div class="item-drop-sources">${this.sanitizeHtml(item.dropSourcesHtml)}</div>`;
    }

    renderExtraSourceIcons(item) {
        const icons = [];
        if (item.dropDailyOps) {
            icons.push('<img src="/assets/icons/FO76_dailyops_uplink.png" alt="Operations quotidiennes" title="Operations quotidiennes">');
        }
        if (item.vendorRegs) {
            icons.push('<img src="/assets/icons/Vault79Marker.svg" alt="Regs" title="Regs">');
        }
        if (item.vendorSamuel) {
            icons.push('<img src="/assets/icons/HammerWingMarker.svg" alt="Samuel" title="Samuel">');
        }
        if (item.vendorMortimer) {
            icons.push('<img src="/assets/icons/SkullRingMarker.svg" alt="Mortimer" title="Mortimer">');
        }

        if (icons.length === 0) {
            return '';
        }

        return `<p class="item-source-icons">${icons.join('')}</p>`;
    }

    bindToggleButtons(container) {
        container.querySelectorAll('input[data-item-checkbox="1"]').forEach((checkbox) => {
            checkbox.addEventListener('change', async () => {
                const itemId = checkbox.getAttribute('data-item-id');
                const learned = checkbox.checked;
                if (!itemId) {
                    return;
                }
                await this.toggleLearned(itemId, learned);
            });
        });
    }

    bindAccordionBehavior(container, kind) {
        const detailsNodes = Array.from(container.querySelectorAll('details[data-accordion-kind]'));
        detailsNodes.forEach((node) => {
            node.addEventListener('toggle', () => {
                if (!node.open) {
                    return;
                }

                const openedId = Number(node.getAttribute('data-accordion-id'));
                if (kind === 'misc') {
                    this.openMiscGroup = openedId;
                } else {
                    this.openBookGroup = openedId;
                }

                detailsNodes.forEach((other) => {
                    if (other !== node) {
                        other.open = false;
                    }
                });
            });
        });
    }

    syncPlayerQueryParam() {
        if (!this.activePlayerId) {
            return;
        }
        const url = new URL(window.location.href);
        if (this.activeLocale) {
            url.searchParams.set('locale', this.activeLocale);
        }
        url.searchParams.set('player', this.activePlayerId);
        window.history.replaceState({}, '', url.toString());
    }

    readActiveLocale() {
        const locale = new URLSearchParams(window.location.search).get('locale');
        if (!locale) {
            return '';
        }

        return String(locale).trim().toLowerCase();
    }

    appendLocaleToUrl(rawUrl) {
        if (!this.activeLocale) {
            return rawUrl;
        }

        const url = new URL(rawUrl, window.location.origin);
        if (!url.searchParams.get('locale')) {
            url.searchParams.set('locale', this.activeLocale);
        }

        return `${url.pathname}${url.search}`;
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

    sanitizeHtml(html) {
        const template = document.createElement('template');
        template.innerHTML = String(html);

        const allowedTags = new Set(['P', 'STRONG', 'EM', 'SPAN', 'BR', 'IMG']);
        const allowedAttrs = new Set(['class', 'src', 'title', 'alt']);

        template.content.querySelectorAll('*').forEach((node) => {
            if (!allowedTags.has(node.tagName)) {
                node.replaceWith(...node.childNodes);
                return;
            }

            Array.from(node.attributes).forEach((attr) => {
                const name = attr.name.toLowerCase();
                const value = attr.value;
                if (!allowedAttrs.has(name)) {
                    node.removeAttribute(attr.name);
                    return;
                }
                if (name === 'src' && (value.startsWith('javascript:') || value.startsWith('data:'))) {
                    node.removeAttribute(attr.name);
                    return;
                }
                if (name === 'src' && value.startsWith('/cms/')) {
                    const filename = value.split('/').pop() || '';
                    if (filename !== '') {
                        const mappedFilename = filename === 'raid_icon_black_128.png'
                            ? 'GleamingDepthsMarker.svg'
                            : filename;
                        node.setAttribute('src', `/assets/icons/${mappedFilename}`);
                    } else {
                        node.removeAttribute(attr.name);
                    }
                }
            });
        });

        return template.innerHTML;
    }
}
