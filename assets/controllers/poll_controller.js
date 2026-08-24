import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        interval: { type: Number, default: 2000 },
        url: String
    };

    connect() {
        this.startPolling();
    }

    disconnect() {
        this.stopPolling();
    }

    startPolling() {
        this.timer = setInterval(() => {
            const frame = this.element.closest('turbo-frame') || this.element;
            if (typeof frame.reload === 'function') {
                frame.reload();
            } else if (window.Turbo) {
                window.Turbo.visit(this.urlValue || window.location.href, { action: 'replace' });
            } else {
                window.location.reload();
            }
        }, this.intervalValue);
    }

    stopPolling() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
}
