import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['searchInput', 'results', 'summary', 'pageInput'];

    connect() {
        this.searchDebounceId = null;
        this.abortController = null;
        this.bindEvents();
    }

    disconnect() {
        if (this.searchDebounceId) {
            window.clearTimeout(this.searchDebounceId);
        }

        if (this.abortController) {
            this.abortController.abort();
        }
    }

    bindEvents() {
        this.element.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submit();
        });

        this.element.querySelectorAll('input[type="checkbox"], input[type="radio"], select').forEach((field) => {
            field.addEventListener('change', () => {
                this.resetPage();
                this.submit();
            });
        });

        if (this.hasSearchInputTarget) {
            this.searchInputTarget.addEventListener('input', () => {
                if (this.searchDebounceId) {
                    window.clearTimeout(this.searchDebounceId);
                }

                this.searchDebounceId = window.setTimeout(() => {
                    this.resetPage();
                    this.submit();
                }, 250);
            });
        }

        if (this.hasResultsTarget) {
            this.resultsTarget.addEventListener('click', (event) => {
                const link = event.target.closest('.catalog-pagination-link[href]');
                if (!(link instanceof HTMLAnchorElement)) {
                    return;
                }

                event.preventDefault();

                const url = new URL(link.href, window.location.origin);
                const page = url.searchParams.get('page');
                this.pageInputTarget.value = page && /^\d+$/.test(page) ? page : '1';
                this.submit();
            });
        }
    }

    resetPage() {
        if (this.hasPageInputTarget) {
            this.pageInputTarget.value = '1';
        }
    }

    async submit() {
        if (this.abortController) {
            this.abortController.abort();
        }

        this.abortController = new AbortController();

        const formData = new FormData(this.element);
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            params.append(key, String(value));
        }

        const requestUrl = new URL(this.element.getAttribute('action') || window.location.pathname, window.location.origin);
        requestUrl.search = params.toString();

        const response = await fetch(requestUrl.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: this.abortController.signal,
        });

        if (!response.ok) {
            return;
        }

        const html = await response.text();
        const parser = new DOMParser();
        const document = parser.parseFromString(html, 'text/html');

        const incomingResults = document.querySelector('[data-catalog-filters-target="results"]');
        const incomingSummary = document.querySelector('[data-catalog-filters-target="summary"]');

        if (incomingResults && this.hasResultsTarget) {
            this.resultsTarget.innerHTML = incomingResults.innerHTML;
        }

        if (incomingSummary && this.hasSummaryTarget) {
            this.summaryTarget.innerHTML = incomingSummary.innerHTML;
        }

        if (this.hasPageInputTarget) {
            const nextPageInput = document.querySelector('[data-catalog-filters-target="pageInput"]');
            if (nextPageInput instanceof HTMLInputElement) {
                this.pageInputTarget.value = nextPageInput.value;
            }
        }
    }
}
