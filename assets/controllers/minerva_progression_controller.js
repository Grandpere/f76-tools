import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['cell'];
    static values = {
        playersBaseUrl: String,
        storageKey: String,
        locale: String,
        fallbackPlayerId: String,
    };

    async connect() {
        const playerId = this.resolvePlayerId();
        if (!playerId) {
            return;
        }

        const url = this.buildStatsUrl(playerId);
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return;
        }

        const payload = await response.json();
        const bookByList = Array.isArray(payload?.bookByList) ? payload.bookByList : [];
        const byList = new Map();
        bookByList.forEach((row) => {
            const listNumber = Number(row?.listNumber ?? 0);
            if (!Number.isInteger(listNumber) || listNumber <= 0) {
                return;
            }
            const learned = Number.isInteger(row.learned) ? row.learned : 0;
            const total = Number.isInteger(row.total) ? row.total : 0;
            const percent = Number.isInteger(row.percent) ? row.percent : 0;
            byList.set(listNumber, { learned, total, percent });
        });

        this.renderProgress(byList);
    }

    resolvePlayerId() {
        const key = String(this.storageKeyValue || '').trim();
        if (key) {
            try {
                const raw = window.localStorage.getItem(key);
                if (raw) {
                    const parsed = JSON.parse(raw);
                    const active = parsed?.__meta?.activePlayerId;
                    if (typeof active === 'string' && active.trim() !== '') {
                        return active.trim();
                    }
                }
            } catch {
                // Ignore parse/storage errors.
            }
        }

        const fallback = String(this.fallbackPlayerIdValue || '').trim();

        return fallback !== '' ? fallback : '';
    }

    buildStatsUrl(playerId) {
        const base = String(this.playersBaseUrlValue || '').replace(/\/+$/, '');
        const url = new URL(`${base}/${playerId}/stats`, window.location.origin);
        const locale = String(this.localeValue || '').trim();
        if (locale !== '') {
            url.searchParams.set('locale', locale);
        }

        return `${url.pathname}${url.search}`;
    }

    renderProgress(byList) {
        this.cellTargets.forEach((cell) => {
            const listCycle = Number(cell.getAttribute('data-list-cycle') || 0);
            if (!Number.isInteger(listCycle) || listCycle <= 0) {
                return;
            }

            const metaNode = cell.querySelector('[data-minerva-progress-meta]');
            const fillNode = cell.querySelector('[data-minerva-progress-fill]');
            const stat = byList.get(listCycle) ?? byList.get(this.resolveLegacyListMapping(listCycle));

            if (!metaNode || !fillNode) {
                return;
            }

            if (!stat) {
                metaNode.textContent = '-';
                fillNode.style.width = '0%';
                return;
            }

            metaNode.textContent = `${stat.learned}/${stat.total}`;
            fillNode.style.width = `${Math.max(0, Math.min(100, stat.percent))}%`;

            const track = fillNode.parentElement;
            if (track) {
                track.setAttribute('aria-valuenow', String(stat.percent));
            }
        });
    }

    resolveLegacyListMapping(listCycle) {
        if (listCycle >= 1 && listCycle <= 4) {
            return listCycle;
        }

        const relative = ((listCycle - 1) % 4) + 1;

        return relative <= 0 ? 1 : relative;
    }
}
