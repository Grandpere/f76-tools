import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'createNameInput', 'createButton', 'searchInput', 'sourceFilter', 'state', 'statsPanel', 'miscList', 'bookList', 'exportButton', 'importFileInput', 'importMergeCheckbox', 'importButton', 'importUnknownPanel'];
    static values = {
        playersUrl: String,
        playersBaseUrl: String,
        initialPlayerId: String,
        storageKey: String,
        uiTranslations: String,
    };

    async connect() {
        this.players = [];
        this.items = [];
        this.activePlayerId = null;
        this.activeLocale = this.readActiveLocale();
        this.translations = this.readUiTranslations();
        this.searchQuery = '';
        this.searchDebounceId = null;
        this.activeSourceFilters = [];
        this.openMiscGroup = null;
        this.openBookGroup = null;
        this.stats = null;
        this.playerUiState = this.readPersistedState();
        this.bindEvents();
        await this.loadPlayers();
    }

    bindEvents() {
        this.playerSelectTarget.addEventListener('change', async () => {
            this.persistCurrentPlayerState();
            this.activePlayerId = this.playerSelectTarget.value;
            this.saveActivePlayerId(this.activePlayerId);
            this.restoreUiStateForPlayer(this.activePlayerId);
            await this.loadItems();
        });

        this.createButtonTarget.addEventListener('click', async () => {
            await this.createPlayerFromInput();
        });
        this.exportButtonTarget.addEventListener('click', async () => {
            await this.exportKnowledge();
        });
        this.importButtonTarget.addEventListener('click', async () => {
            await this.importKnowledge();
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
                this.persistCurrentPlayerState();
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
                this.persistCurrentPlayerState();
            });
        });
    }

    async loadPlayers() {
        this.setState(this.t('loadingPlayers'));
        this.miscListTarget.innerHTML = '';
        if (this.hasBookListTarget) {
            this.bookListTarget.innerHTML = '';
        }
        this.statsPanelTarget.innerHTML = '';

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

    async createPlayerFromInput() {
        const name = this.createNameInputTarget.value.trim();
        if (name === '') {
            this.setState(this.t('playerNameRequired'));
            return;
        }

        this.createButtonTarget.disabled = true;
        this.setState(this.t('creatingPlayer'));

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
                this.setState(this.t('playerNameExists'));
                return;
            }
            this.setState(`${this.t('createPlayerFailed')} (${response.status}).`);
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
                this.saveActivePlayerId(this.activePlayerId);
                await this.loadItems();
            }
        }
        this.setState(this.t('playerCreated', { '%name%': name }));
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
        const fallback = this.players[0];
        const selected = found || fallback;

        this.activePlayerId = String(selected.id);
        this.saveActivePlayerId(this.activePlayerId);
        this.restoreUiStateForPlayer(this.activePlayerId);
        this.playerSelectTarget.value = this.activePlayerId;
    }

    async loadItems() {
        if (!this.activePlayerId) {
            this.setState(this.t('noSelectedPlayer'));
            return;
        }

        this.renderItems();
        this.setState(this.t('loadingItems'));

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
            this.setState(`${this.t('loadItemsError')} (${response.status}).`);
            return;
        }

        const payload = await response.json();
        this.items = Array.isArray(payload) ? payload : [];
        this.renderItems();
        await this.loadStats();
        this.updateStateCounter();
    }

    async loadStats() {
        if (!this.activePlayerId) {
            this.stats = null;
            this.renderStats();
            return;
        }

        const url = `${this.playersBaseUrlValue}/${this.activePlayerId}/stats`;
        const response = await fetch(this.appendLocaleToUrl(url), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.stats = null;
            this.renderStats();
            return;
        }

        const payload = await response.json();
        this.stats = payload && typeof payload === 'object' ? payload : null;
        this.renderStats();
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
        await this.loadStats();
        this.updateStateCounter();
        this.persistCurrentPlayerState();
    }

    async exportKnowledge() {
        if (!this.activePlayerId) {
            this.setState(this.t('noSelectedPlayer'));
            return;
        }

        const url = `${this.playersBaseUrlValue}/${this.activePlayerId}/knowledge/export`;
        const response = await fetch(this.appendLocaleToUrl(url), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setState(`${this.t('exportFailed')} (${response.status}).`);
            return;
        }

        const payload = await response.json();
        const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
        const objectUrl = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = `player_${this.activePlayerId}_knowledge.json`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);
        this.setState(this.t('exportDone'));
    }

    async importKnowledge() {
        if (!this.activePlayerId) {
            this.setState(this.t('noSelectedPlayer'));
            return;
        }

        const file = this.importFileInputTarget.files && this.importFileInputTarget.files[0];
        if (!file) {
            this.clearImportUnknownPanel();
            this.setState(this.t('importNoFile'));
            return;
        }

        let parsed;
        try {
            const text = await file.text();
            parsed = JSON.parse(text);
        } catch {
            this.clearImportUnknownPanel();
            this.setState(this.t('importInvalidFile'));
            return;
        }

        const replace = !this.importMergeCheckboxTarget.checked;
        const previewResponse = await fetch(this.appendLocaleToUrl(`${this.playersBaseUrlValue}/${this.activePlayerId}/knowledge/preview-import`), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                version: Number.isInteger(parsed.version) ? parsed.version : 1,
                replace,
                learnedItems: Array.isArray(parsed.learnedItems) ? parsed.learnedItems : [],
            }),
        });
        if (!previewResponse.ok) {
            this.clearImportUnknownPanel();
            this.setState(`${this.t('importPreviewError')} (${previewResponse.status}).`);
            return;
        }

        const preview = await previewResponse.json();
        const unknownItems = Array.isArray(preview.unknownItems) ? preview.unknownItems : [];
        if (unknownItems.length > 0) {
            this.renderImportUnknownPanel(unknownItems);
            const details = unknownItems
                .slice(0, 8)
                .map((entry) => {
                    const type = typeof entry.type === 'string' ? entry.type : '?';
                    const sourceId = Number.isInteger(entry.sourceId) ? entry.sourceId : Number(entry.sourceId || 0);

                    return `${type}:${sourceId}`;
                })
                .join(', ');
            const suffix = unknownItems.length > 8 ? ', ...' : '';
            this.setState(
                `${this.t('importPreviewUnknown', { '%count%': unknownItems.length })} ${this.t('importPreviewUnknownDetailsPrefix')}: ${details}${suffix}`,
            );
            return;
        }
        this.clearImportUnknownPanel();

        const previewMessage = this.t('importConfirmPreview', {
            '%add%' : Number(preview.wouldAdd ?? 0),
            '%remove%': Number(preview.wouldRemove ?? 0),
        });
        if (!window.confirm(previewMessage)) {
            return;
        }

        this.importButtonTarget.disabled = true;
        this.setState(this.t('importingProgress'));

        const response = await fetch(this.appendLocaleToUrl(`${this.playersBaseUrlValue}/${this.activePlayerId}/knowledge/import`), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                version: Number.isInteger(parsed.version) ? parsed.version : 1,
                replace,
                learnedItems: Array.isArray(parsed.learnedItems) ? parsed.learnedItems : [],
            }),
        });
        this.importButtonTarget.disabled = false;

        if (!response.ok) {
            this.setState(`${this.t('importFailed')} (${response.status}).`);
            return;
        }

        this.importFileInputTarget.value = '';
        this.clearImportUnknownPanel();
        await this.loadItems();
        this.setState(this.t('importDone'));
    }

    renderImportUnknownPanel(unknownItems) {
        if (!this.hasImportUnknownPanelTarget) {
            return;
        }

        const rows = unknownItems
            .map((entry) => {
                const type = typeof entry.type === 'string' ? entry.type : '?';
                const sourceId = Number.isInteger(entry.sourceId) ? entry.sourceId : Number(entry.sourceId || 0);

                return `<li><code>${this.escape(type)}:${this.escape(sourceId)}</code></li>`;
            })
            .join('');

        this.importUnknownPanelTarget.innerHTML = `
            <p class="transfer-unknown-title">${this.escape(this.t('importPreviewUnknownDetailsPrefix'))}</p>
            <ul>${rows}</ul>
        `;
    }

    clearImportUnknownPanel() {
        if (!this.hasImportUnknownPanelTarget) {
            return;
        }
        this.importUnknownPanelTarget.innerHTML = '';
    }

    renderItems() {
        const visibleItems = this.getVisibleItems();
        if (visibleItems.length === 0) {
            this.miscListTarget.innerHTML = `<p>${this.escape(this.t('noMiscFound'))}</p>`;
            if (this.hasBookListTarget) {
                this.bookListTarget.innerHTML = `<p>${this.escape(this.t('noBookFound'))}</p>`;
            }
            return;
        }
        this.miscListTarget.innerHTML = this.renderMiscBlock(visibleItems);
        this.bindToggleButtons(this.miscListTarget);
        this.bindAccordionBehavior(this.miscListTarget, 'misc');
        if (this.hasBookListTarget) {
            this.bookListTarget.innerHTML = this.renderBookBlock(visibleItems);
            this.bindToggleButtons(this.bookListTarget);
            this.bindAccordionBehavior(this.bookListTarget, 'book');
        }
    }

    renderStats() {
        if (!this.stats || typeof this.stats !== 'object') {
            this.statsPanelTarget.innerHTML = '';
            return;
        }

        const overall = this.stats.overall || { learned: 0, total: 0, percent: 0 };
        const byType = this.stats.byType || {};
        const misc = byType.misc || { learned: 0, total: 0, percent: 0 };
        const book = byType.book || { learned: 0, total: 0, percent: 0 };
        const miscByRank = Array.isArray(this.stats.miscByRank) ? this.stats.miscByRank : [];
        const bookByList = Array.isArray(this.stats.bookByList) ? this.stats.bookByList : [];

        const cards = [
            this.renderStatsCard(this.t('statsOverall'), overall),
            this.renderStatsCard(this.t('statsMisc'), misc),
            this.renderStatsCard(this.t('statsBook'), book),
        ].join('');

        const rankRows = miscByRank.map((row) => this.renderStatsRow(`${this.t('statsRankPrefix')} ${row.rank}`, row)).join('');
        const listRows = bookByList.map((row) => this.renderStatsRow(`${this.t('statsListPrefix')} ${row.listNumber}`, row)).join('');

        this.statsPanelTarget.innerHTML = `
            <div class="stats-cards">${cards}</div>
            <div class="stats-split">
                <section class="stats-group">
                    <h3>${this.escape(this.t('statsByRank'))}</h3>
                    ${rankRows || '<p class="catalog-note">-</p>'}
                </section>
                <section class="stats-group">
                    <h3>${this.escape(this.t('statsByList'))}</h3>
                    ${listRows || '<p class="catalog-note">-</p>'}
                </section>
            </div>
        `;
    }

    renderStatsCard(title, stat) {
        const learned = Number.isInteger(stat.learned) ? stat.learned : 0;
        const total = Number.isInteger(stat.total) ? stat.total : 0;
        const percent = Number.isInteger(stat.percent) ? stat.percent : 0;

        return `
            <article class="stats-card">
                <p class="stats-card-title">${this.escape(title)}</p>
                <p class="stats-card-main">${this.escape(percent)}${this.escape(this.t('statsPercentSuffix'))}</p>
                <p class="stats-card-sub">${this.escape(this.t('statsLearnedOnTotal', { '%learned%': learned, '%total%': total }))}</p>
                <div class="stats-bar"><span style="width:${Math.max(0, Math.min(100, percent))}%"></span></div>
            </article>
        `;
    }

    renderStatsRow(label, stat) {
        const learned = Number.isInteger(stat.learned) ? stat.learned : 0;
        const total = Number.isInteger(stat.total) ? stat.total : 0;
        const percent = Number.isInteger(stat.percent) ? stat.percent : 0;

        return `
            <div class="stats-row">
                <p class="stats-row-head">
                    <strong>${this.escape(label)}</strong>
                    <span>${this.escape(this.t('statsLearnedOnTotal', { '%learned%': learned, '%total%': total }))}</span>
                </p>
                <div class="stats-bar"><span style="width:${Math.max(0, Math.min(100, percent))}%"></span></div>
            </div>
        `;
    }

    renderMiscBlock(items) {
        const miscItems = items.filter((item) => item.type === 'MISC');
        if (miscItems.length === 0) {
            return `<p>${this.escape(this.t('noMiscFound'))}</p>`;
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
                <summary>${this.escape(this.t('rankPrefix'))} ${this.escape(rank)}</summary>
                <ul class="item-grid">${rankMap.get(rank).map((item) => this.renderItemCard(item)).join('')}</ul>
            </details>
        `).join('');
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

        const groupsHtml = groups
            .sort((a, b) => a[0] - b[0])
            .map(([listNumber, items]) => `
                <details class="catalog-subgroup" data-accordion-kind="book" data-accordion-id="${listNumber}" ${listNumber === this.openBookGroup ? 'open' : ''}>
                    <summary>${this.escape(this.t('listPrefix'))} ${this.escape(listNumber)}</summary>
                    <ul class="item-grid">${items.map((item) => this.renderItemCard(item)).join('')}</ul>
                </details>
            `)
            .join('');

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
        const dailyOpsLine = item.type === 'BOOK' && item.dropDailyOps && !this.infoContainsDailyOps(item.infoHtml)
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
        const searchSuffix = this.searchQuery !== '' ? `, ${this.t('searchFilterPrefix')}: "${this.searchQuery}"` : '';
        const translatedFilters = this.activeSourceFilters.map((key) => this.t(`source_${key}`));
        const sourceSuffix = this.activeSourceFilters.length > 0
            ? `, ${this.t('sourceFiltersPrefix')}: ${translatedFilters.join(', ')}`
            : '';

        this.setState(this.t('visibleItems', { '%count%': visibleCount }) + searchSuffix + sourceSuffix + '.');
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
                this.persistCurrentPlayerState();
            });
        });
    }

    restoreUiStateForPlayer(playerId) {
        const key = String(playerId || '').trim();
        if (!key) {
            return;
        }
        const state = this.playerUiState[key];
        const hasState = state && typeof state === 'object';

        this.searchQuery = hasState && typeof state.searchQuery === 'string'
            ? state.searchQuery
            : '';
        this.searchInputTarget.value = this.searchQuery;

        const sourceFilters = hasState && Array.isArray(state.sourceFilters)
            ? state.sourceFilters.filter((value) => typeof value === 'string')
            : [];
        this.activeSourceFilters = [...sourceFilters];
        this.sourceFilterTargets.forEach((checkbox) => {
            checkbox.checked = sourceFilters.includes(checkbox.value);
        });

        this.openMiscGroup = hasState && Number.isInteger(state.openMiscGroup)
            ? state.openMiscGroup
            : null;
        this.openBookGroup = hasState && Number.isInteger(state.openBookGroup)
            ? state.openBookGroup
            : null;
    }

    persistCurrentPlayerState() {
        const key = String(this.activePlayerId || '').trim();
        if (!key) {
            return;
        }

        this.playerUiState[key] = {
            searchQuery: this.searchQuery,
            sourceFilters: [...this.activeSourceFilters],
            openMiscGroup: Number.isInteger(this.openMiscGroup) ? this.openMiscGroup : null,
            openBookGroup: Number.isInteger(this.openBookGroup) ? this.openBookGroup : null,
            updatedAt: Date.now(),
        };
        this.persistAllState();
    }

    readPersistedState() {
        try {
            const raw = window.localStorage.getItem(this.storageBucketKey());
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

    persistAllState() {
        try {
            window.localStorage.setItem(this.storageBucketKey(), JSON.stringify(this.playerUiState));
        } catch {
            // Ignore storage errors (private mode/full storage).
        }
    }

    storageBucketKey() {
        const key = String(this.storageKeyValue || '').trim();
        if (key !== '') {
            return key;
        }

        return 'f76:item-catalog:ui';
    }

    getSavedActivePlayerId() {
        const root = this.playerUiState;
        if (!root || typeof root !== 'object') {
            return;
        }

        const meta = root.__meta;
        if (!meta || typeof meta !== 'object') {
            return '';
        }

        const value = meta.activePlayerId;

        return typeof value === 'string' ? value : '';
    }

    saveActivePlayerId(playerId) {
        const key = String(playerId || '').trim();
        if (key === '') {
            return;
        }

        if (!this.playerUiState.__meta || typeof this.playerUiState.__meta !== 'object') {
            this.playerUiState.__meta = {};
        }
        this.playerUiState.__meta.activePlayerId = key;
        this.persistAllState();
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

    t(key, params = {}) {
        const raw = this.translations[key] ?? key;

        return Object.entries(params).reduce(
            (carry, [name, value]) => carry.replaceAll(name, String(value)),
            String(raw),
        );
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

    readUiTranslations() {
        try {
            const raw = this.uiTranslationsValue ?? '{}';
            const parsed = JSON.parse(raw);

            return typeof parsed === 'object' && parsed !== null ? parsed : {};
        } catch {
            return {};
        }
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
