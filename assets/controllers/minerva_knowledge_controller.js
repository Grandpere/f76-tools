import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'searchInput', 'sourceFilter', 'state', 'bookList'];
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
        this.items = [];
        this.activePlayerId = null;
        this.searchQuery = '';
        this.searchDebounceId = null;
        this.openBookGroup = null;
        this.activeSourceFilters = [];
        this.translations = this.readUiTranslations();
        this.playerUiState = this.readPersistedState();
        this.bindEvents();
        await this.loadPlayers();
    }

    bindEvents() {
        this.playerSelectTarget.addEventListener('change', async () => {
            this.activePlayerId = this.playerSelectTarget.value;
            this.saveActivePlayerId(this.activePlayerId);
            this.openBookGroup = null;
            await this.loadItems();
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
            });
        });
    }

    async loadPlayers() {
        this.setState(this.t('loadingPlayers'));
        this.bookListTarget.innerHTML = '';

        const response = await fetch(this.appendLocaleToUrl(this.playersUrlValue), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setState(`${this.t('cannotLoadPlayers')} (${response.status}).`);
            return;
        }

        const payload = await response.json();
        this.players = Array.isArray(payload) ? payload : [];
        if (this.players.length === 0) {
            this.setState(this.t('noPlayers'));
            return;
        }

        this.renderPlayerSelect();
        this.applyInitialPlayer();
        await this.loadItems();
    }

    async loadItems() {
        if (!this.activePlayerId) {
            this.setState(this.t('noSelectedPlayer'));
            return;
        }

        this.setState(this.t('loadingItems'));

        const params = new URLSearchParams();
        params.set('type', 'BOOK');
        if (this.searchQuery !== '') {
            params.set('q', this.searchQuery);
        }
        const itemsUrl = `${this.playersBaseUrlValue}/${this.activePlayerId}/items?${params.toString()}`;
        const response = await fetch(this.appendLocaleToUrl(itemsUrl), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setState(`${this.t('loadItemsError')} (${response.status}).`);
            return;
        }

        const payload = await response.json();
        this.items = Array.isArray(payload) ? payload : [];
        this.renderItems();
        this.setState('');
    }

    renderItems() {
        const visibleItems = this.getVisibleItems();
        if (visibleItems.length === 0) {
            this.bookListTarget.innerHTML = `<p>${this.escape(this.t('noBookFound'))}</p>`;
            return;
        }
        this.bookListTarget.innerHTML = this.renderBookBlock(visibleItems);
        this.bindToggleButtons(this.bookListTarget);
        this.bindAccordionBehavior();
    }

    getVisibleItems() {
        return this.items.filter((item) => this.matchesSourceFilters(item));
    }

    matchesSourceFilters(item) {
        if (!Array.isArray(this.activeSourceFilters) || this.activeSourceFilters.length === 0) {
            return true;
        }
        return this.activeSourceFilters.some((filterName) => item[filterName] === true);
    }

    renderBookBlock(items) {
        const books = items.filter((item) => item.type === 'BOOK');
        if (books.length === 0) {
            return `<p>${this.escape(this.t('noBookFound'))}</p>`;
        }

        const listMap = new Map();
        books.forEach((item) => {
            const listNumbers = Array.isArray(item.listNumbers) ? item.listNumbers : [];
            if (listNumbers.length === 0) {
                if (!listMap.has(1)) {
                    listMap.set(1, []);
                }
                listMap.get(1).push(item);
                return;
            }
            listNumbers.forEach((listNumber) => {
                const numericList = Number(listNumber);
                if (!Number.isInteger(numericList) || numericList < 1) {
                    return;
                }
                if (!listMap.has(numericList)) {
                    listMap.set(numericList, []);
                }
                listMap.get(numericList).push(item);
            });
        });
        listMap.forEach((groupItems, listNumber) => {
            listMap.set(listNumber, this.sortItemsByName(groupItems));
        });

        const groups = Array.from(listMap.entries()).sort((a, b) => a[0] - b[0]);
        const listNumbers = groups.map(([listNumber]) => listNumber);
        if (this.openBookGroup === null || !listNumbers.includes(this.openBookGroup)) {
            this.openBookGroup = listNumbers[0] ?? null;
        }

        const groupsHtml = groups.map(([listNumber, groupItems]) => `
            <details class="catalog-subgroup" data-accordion-id="${listNumber}" ${listNumber === this.openBookGroup ? 'open' : ''}>
                <summary>${this.escape(this.t('listPrefix'))} ${this.escape(listNumber)}</summary>
                <ul class="item-grid">${groupItems.map((item) => this.renderItemCard(item)).join('')}</ul>
            </details>
        `).join('');

        return `
            <p class="catalog-note">${this.escape(this.t('sharedInfo'))}</p>
            ${groupsHtml}
        `;
    }

    renderItemCard(item) {
        const label = this.escape(item.name || item.nameKey);
        const description = item.description ? `<p>${this.escapeWithBreaks(item.description)}</p>` : '';
        const infoBlock = this.renderInfoBlock(item);
        const iconsFooter = this.renderIconsFooter(item);
        const dailyOpsLine = item.dropDailyOps && !this.infoContainsDailyOps(item.infoHtml)
            ? `<p class="item-extra-line">${this.escape(this.t('dailyOpsReward'))}</p>`
            : '';
        const priceBlock = this.renderPriceBlock(item);
        const learnedClass = item.learned ? 'is-learned' : 'is-unlearned';
        const checkedAttr = item.learned ? 'checked' : '';
        const newBadge = item.isNew ? '<span class="item-badge-new">NEW</span>' : '';

        return `
            <li class="item-card ${learnedClass}">
                <input class="item-learned-checkbox" type="checkbox" aria-label="${this.escape(this.t('ariaItemLearned'))}" data-item-checkbox="1" data-item-id="${item.id}" ${checkedAttr}>
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
        const sourceIcons = this.renderDropSources(item);
        const extraSourceIcons = this.renderExtraSourceIcons(item);

        if (sourceIcons === '' && extraSourceIcons === '') {
            return '';
        }

        return `<div class="item-icons-footer">${sourceIcons}${extraSourceIcons}</div>`;
    }

    renderPriceBlock(item) {
        const basePrice = Number.isInteger(item.price) ? item.price : null;
        const minervaPrice = Number.isInteger(item.priceMinerva) ? item.priceMinerva : null;
        const hasDiscount = basePrice !== null && minervaPrice !== null && minervaPrice < basePrice;
        const displayPrice = minervaPrice ?? basePrice;
        const displayPriceLabel = displayPrice === null ? '-' : this.escape(displayPrice);
        const oldPriceLabel = basePrice === null ? null : this.escape(basePrice);

        if (hasDiscount) {
            return `
                <p class="item-prices item-prices-discount">
                    <img src="/assets/icons/Fo76_Icon_Gold_Bullion.png" alt="Gold Bullion">
                    <span class="price-old">${oldPriceLabel}</span>
                    <span class="price-new">${this.escape(minervaPrice)}</span>
                </p>
            `;
        }

        return `
            <p class="item-prices">
                <img src="/assets/icons/Fo76_Icon_Gold_Bullion.png" alt="Gold Bullion">
                <span class="price-new">${displayPriceLabel}</span>
            </p>
        `;
    }

    bindToggleButtons(rootElement) {
        rootElement.querySelectorAll('[data-item-checkbox]').forEach((checkbox) => {
            checkbox.addEventListener('change', async (event) => {
                const target = event.currentTarget;
                if (!(target instanceof HTMLInputElement)) {
                    return;
                }

                const itemId = String(target.dataset.itemId || '');
                if (itemId === '') {
                    return;
                }

                await this.toggleLearned(itemId, target.checked);
            });
        });
    }

    bindAccordionBehavior() {
        this.bookListTarget.querySelectorAll('.catalog-subgroup').forEach((details) => {
            details.addEventListener('toggle', () => {
                if (!details.open) {
                    return;
                }
                const id = Number(details.dataset.accordionId);
                if (Number.isInteger(id)) {
                    this.openBookGroup = id;
                }
                this.bookListTarget.querySelectorAll('.catalog-subgroup').forEach((other) => {
                    if (other !== details && other.open) {
                        other.open = false;
                    }
                });
            });
        });
    }

    renderInfoBlock(item) {
        if (!item.infoHtml) {
            return '';
        }

        return `<div class="item-info-html">${this.sanitizeHtml(item.infoHtml)}</div>`;
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
            icons.push('<img src="/assets/icons/FO76_dailyops_uplink.png" alt="Daily Ops" title="Daily Ops">');
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
            this.setState(`${this.t('updateFailed')} (${response.status}).`);
            return;
        }

        this.items = this.items.map((item) => (String(item.id) === String(itemId)
            ? { ...item, learned: shouldBeLearned }
            : item));
        this.renderItems();
    }

    renderPlayerSelect() {
        this.playerSelectTarget.innerHTML = this.players
            .map((player) => `<option value="${player.id}">${this.escape(player.name)}</option>`)
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
        if (!key) {
            return;
        }
        const player = String(playerId || '').trim();
        if (!player) {
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
        const raw = String(this.uiTranslationsValue || '').trim();
        if (raw === '') {
            return {};
        }
        try {
            const parsed = JSON.parse(raw);
            if (typeof parsed === 'object' && parsed !== null) {
                return parsed;
            }
        } catch {
            return {};
        }

        return {};
    }

    appendLocaleToUrl(rawUrl) {
        const locale = String(this.localeValue || '').trim().toLowerCase();
        if (!locale) {
            return rawUrl;
        }
        const url = new URL(rawUrl, window.location.origin);
        if (!url.searchParams.get('locale')) {
            url.searchParams.set('locale', locale);
        }

        return `${url.pathname}${url.search}`;
    }

    sortItemsByName(items) {
        return [...items].sort((a, b) => String(a.name || a.nameKey || '').localeCompare(String(b.name || b.nameKey || ''), undefined, { sensitivity: 'base' }));
    }

    setState(message) {
        this.stateTarget.textContent = message;
    }

    t(key) {
        return String(this.translations[key] ?? key);
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

    escapeWithBreaks(value) {
        const escaped = this.escape(value);
        return escaped
            .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
            .replace(/\r?\n/g, '<br>');
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
