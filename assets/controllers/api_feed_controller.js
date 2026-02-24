import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['state', 'list'];
    static values = { url: String };

    async connect() {
        if (!this.hasUrlValue) {
            this.stateTarget.textContent = 'Endpoint API manquant.';
            return;
        }

        try {
            const response = await fetch(this.urlValue, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                this.stateTarget.textContent = `Erreur API (${response.status})`;
                return;
            }

            const payload = await response.json();
            this.stateTarget.textContent = `${payload.title} - ${payload.updatedAt}`;
            this.listTarget.innerHTML = payload.cards
                .map((card) => `<li><strong>${card.label}</strong>: ${card.value}</li>`)
                .join('');
        } catch (error) {
            this.stateTarget.textContent = 'Impossible de charger les donnees.';
            console.error(error);
        }
    }
}
