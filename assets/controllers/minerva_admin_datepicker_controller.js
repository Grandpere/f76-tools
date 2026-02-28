import { Controller } from '@hotwired/stimulus';
import flatpickr from 'flatpickr';
import { French } from 'flatpickr/dist/l10n/fr.js';
import { German } from 'flatpickr/dist/l10n/de.js';

export default class extends Controller {
    static values = {
        locale: String,
    };

    connect() {
        this.instances = [];
        const locale = this.resolveLocale();

        this.element.querySelectorAll("input[type='date']").forEach((input) => {
            this.instances.push(flatpickr(input, {
                locale,
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'Y-m-d',
                allowInput: true,
                disableMobile: true,
            }));
        });

        this.element.querySelectorAll("input[type='datetime-local']").forEach((input) => {
            this.instances.push(flatpickr(input, {
                locale,
                dateFormat: 'Y-m-d\\TH:i',
                altInput: true,
                altFormat: 'Y-m-d H:i',
                enableTime: true,
                time_24hr: true,
                allowInput: true,
                disableMobile: true,
            }));
        });
    }

    disconnect() {
        if (!Array.isArray(this.instances)) {
            return;
        }
        this.instances.forEach((instance) => instance.destroy());
        this.instances = [];
    }

    resolveLocale() {
        const key = String(this.localeValue || '').trim().toLowerCase();
        if (key === 'fr') {
            return French;
        }
        if (key === 'de') {
            return German;
        }

        return 'default';
    }
}
