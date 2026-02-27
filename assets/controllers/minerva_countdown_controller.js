import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['days', 'hours', 'minutes', 'seconds'];
    static values = {
        targetDate: String,
    };

    connect() {
        this.targetTimestamp = Date.parse(this.targetDateValue);
        if (Number.isNaN(this.targetTimestamp)) {
            return;
        }

        this.render();
        this.timer = window.setInterval(() => this.render(), 1000);
    }

    disconnect() {
        if (this.timer) {
            window.clearInterval(this.timer);
            this.timer = null;
        }
    }

    render() {
        const now = Date.now();
        let diff = Math.floor((this.targetTimestamp - now) / 1000);
        if (diff < 0) {
            diff = 0;
        }

        const days = Math.floor(diff / 86400);
        const hours = Math.floor((diff % 86400) / 3600);
        const minutes = Math.floor((diff % 3600) / 60);
        const seconds = diff % 60;

        this.daysTarget.textContent = this.format(days);
        this.hoursTarget.textContent = this.format(hours);
        this.minutesTarget.textContent = this.format(minutes);
        this.secondsTarget.textContent = this.format(seconds);
    }

    format(value) {
        return String(value).padStart(2, '0');
    }
}

