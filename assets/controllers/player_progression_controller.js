import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['playerSelect', 'state', 'statsPanel'];
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
        await this.loadPlayers();
    }

    bindEvents() {
        this.playerSelectTarget.addEventListener('change', async () => {
            this.activePlayerId = this.playerSelectTarget.value;
            this.saveActivePlayerId(this.activePlayerId);
            await this.loadStats();
        });
    }

    async loadPlayers() {
        this.setState(this.t('loadingPlayers'));
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
        await this.loadStats();
    }

    async loadStats() {
        if (!this.activePlayerId) {
            this.setState(this.t('noSelectedPlayer'));
            this.stats = null;
            this.renderStats();
            return;
        }

        this.setState('');
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

