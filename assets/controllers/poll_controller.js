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
            const frame = this.element.closest('turbo-frame') || (this.element.tagName === 'TURBO-FRAME' ? this.element : null);
            if (frame && typeof frame.reload === 'function') {
                const scrollX = window.scrollX;
                const scrollY = window.scrollY;
                const restoreScroll = (event) => {
                    if (event.target !== frame) return;

                    window.scrollTo(scrollX, scrollY);
                    frame.removeEventListener('turbo:frame-load', restoreScroll);
                };
                frame.addEventListener('turbo:frame-load', restoreScroll);

                if (this.urlValue && frame.src && frame.src !== this.urlValue) {
                    frame.src = this.urlValue;
                } else if (this.urlValue && !frame.src) {
                    frame.src = this.urlValue;
                } else {
                    frame.reload();
                }
            } else {
                const scrollX = window.scrollX;
                const scrollY = window.scrollY;

                if (window.Turbo) {
                    const restoreScroll = () => {
                        window.scrollTo(scrollX, scrollY);
                        document.removeEventListener('turbo:load', restoreScroll);
                    };
                    document.addEventListener('turbo:load', restoreScroll, { once: true });
                    window.Turbo.visit(this.urlValue || window.location.href, { action: 'replace' });
                } else {
                    window.location.reload();
                }
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
