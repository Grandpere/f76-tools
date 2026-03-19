import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'createNameInput', 'createButton', 'state', 'statsPanel', 'backupState', 'exportButton', 'importFileInput', 'importMergeCheckbox', 'importButton', 'importUnknownPanel'];
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
        this.activePlayerId = null;
        this.stats = null;
        this.translations = this.readUiTranslations();
        this.playerUiState = this.readPersistedState();
        this.bindEvents();
        this.setBackupState('');
        await this.loadPlayers();
    }

    bindEvents() {
        this.playerSelectTarget.addEventListener('change', async () => {
            this.activePlayerId = this.playerSelectTarget.value;
            this.saveActivePlayerId(this.activePlayerId);
            await this.loadStats();
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

        this.exportButtonTarget.addEventListener('click', async () => {
            await this.exportKnowledge();
        });

        this.importButtonTarget.addEventListener('click', async () => {
            await this.importKnowledge();
        });
    }

    async loadPlayers() {
        this.setState(this.t('loadingPlayers'));
        this.renderLoadingPanels();

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
        await this.loadStats();
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
                await this.loadStats();
            }
        }
        this.setState(this.t('playerCreated', { '%name%': name }));
    }

    async loadStats() {
        if (!this.activePlayerId) {
            this.setState(this.t('noSelectedPlayer'));
            this.stats = null;
            this.renderStats();
            return;
        }

        this.setState('');
        this.renderLoadingPanels();
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

    async exportKnowledge() {
        if (!this.activePlayerId) {
            this.setBackupState(this.t('noSelectedPlayer'));
            return;
        }

        const url = `${this.playersBaseUrlValue}/${this.activePlayerId}/knowledge/export`;
        const response = await fetch(this.appendLocaleToUrl(url), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            this.setBackupState(`${this.t('exportFailed')} (${response.status}).`);
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
        this.setBackupState(this.t('exportDone'));
    }

    async importKnowledge() {
        if (!this.activePlayerId) {
            this.setBackupState(this.t('noSelectedPlayer'));
            return;
        }

        const file = this.importFileInputTarget.files && this.importFileInputTarget.files[0];
        if (!file) {
            this.clearImportUnknownPanel();
            this.setBackupState(this.t('importNoFile'));
            return;
        }

        let parsed;
        try {
            const text = await file.text();
            parsed = JSON.parse(text);
        } catch {
            this.clearImportUnknownPanel();
            this.setBackupState(this.t('importInvalidFile'));
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
            this.setBackupState(`${this.t('importPreviewError')} (${previewResponse.status}).`);
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
            this.setBackupState(
                `${this.t('importPreviewUnknown', { '%count%': unknownItems.length })} ${this.t('importPreviewUnknownDetailsPrefix')}: ${details}${suffix}`,
            );
            return;
        }
        this.clearImportUnknownPanel();

        const previewMessage = this.t('importConfirmPreview', {
            '%add%': Number(preview.wouldAdd ?? 0),
            '%remove%': Number(preview.wouldRemove ?? 0),
        });
        if (!window.confirm(previewMessage)) {
            return;
        }

        this.importButtonTarget.disabled = true;
        this.setBackupState(this.t('importingProgress'));

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
            this.setBackupState(`${this.t('importFailed')} (${response.status}).`);
            return;
        }

        this.importFileInputTarget.value = '';
        this.clearImportUnknownPanel();
        await this.loadStats();
        this.setBackupState(this.t('importDone'));
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
        this.importUnknownPanelTarget.replaceChildren();
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

    renderStats() {
        if (!this.stats || typeof this.stats !== 'object') {
            this.statsPanelTarget.replaceChildren();
            return;
        }

        const overall = this.stats.overall || { learned: 0, total: 0, percent: 0 };
        const byType = this.stats.byType || {};
        const byBookKind = this.stats.byBookKind || {};
        const misc = byType.misc || { learned: 0, total: 0, percent: 0 };
        const minervaBooks = this.stats.minervaBooks || { learned: 0, total: 0, percent: 0 };
        const book = byType.book || { learned: 0, total: 0, percent: 0 };
        const bookPlans = byBookKind.plan || { learned: 0, total: 0, percent: 0 };
        const bookRecipes = byBookKind.recipe || { learned: 0, total: 0, percent: 0 };
        const miscByRank = Array.isArray(this.stats.miscByRank) ? this.stats.miscByRank : [];
        const bookByList = Array.isArray(this.stats.bookByList) ? this.stats.bookByList : [];
        const bookByCategory = Array.isArray(this.stats.bookByCategory) ? this.stats.bookByCategory : [];

        const cards = [
            this.renderStatsCard(this.t('statsOverall'), overall),
            this.renderStatsCard(this.t('statsMisc'), misc),
            this.renderStatsCard(this.t('bookCatalogLearned'), book),
        ].join('');

        const rankRows = miscByRank.map((row) => this.renderStatsRow(`${this.t('statsRankPrefix')} ${row.rank}`, row)).join('');
        const listRows = bookByList.map((row) => this.renderStatsRow(`${this.t('statsListPrefix')} ${row.listNumber}`, row)).join('');
        const categoryRows = bookByCategory.map((row) => this.renderStatsRow(this.t(`bookCategory_${row.category}`), row)).join('');

        this.statsPanelTarget.innerHTML = `
            <div class="stats-cards">${cards}</div>
            <div class="stats-cards progression-book-summary">
                ${this.renderStatsCard(this.t('bookCatalogPlans'), bookPlans)}
                ${this.renderStatsCard(this.t('statsBook'), minervaBooks)}
                ${this.renderStatsCard(this.t('bookCatalogRecipes'), bookRecipes)}
            </div>
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
            <div class="stats-split">
                <section class="stats-group">
                    <h3>${this.escape(this.t('statsByBookCategory'))}</h3>
                    ${categoryRows || '<p class="catalog-note">-</p>'}
                </section>
            </div>
        `;
        this.bookPanelTarget.replaceChildren();
    }

    renderLoadingPanels() {
        const placeholder = `<p class="catalog-state">${this.escape(this.t('loadingItems'))}</p>`;
        this.statsPanelTarget.innerHTML = placeholder;
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

    setState(message) {
        this.stateTarget.textContent = message;
    }

    setBackupState(message) {
        if (!this.hasBackupStateTarget) {
            return;
        }
        this.backupStateTarget.textContent = message;
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
}
