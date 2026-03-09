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
            this.listTarget.innerHTML = '';

            const cards = Array.isArray(payload.cards) ? payload.cards : [];
            cards.forEach((card) => {
                const item = document.createElement('li');
                const label = document.createElement('strong');
                label.textContent = String(card?.label ?? '');
                item.appendChild(label);
                item.append(': ');
                item.append(String(card?.value ?? ''));
                this.listTarget.appendChild(item);
            });
        } catch (error) {
            this.stateTarget.textContent = 'Impossible de charger les donnees.';
            console.error(error);
        }
    }
}
