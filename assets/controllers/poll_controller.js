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

    isUserInteracting() {
        const active = document.activeElement;
        if (active && ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(active.tagName)) {
            // Avoid reloading while user is typing in form fields or focused on interactive elements
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)) {
                return true;
            }
        }
        if (document.querySelector('details[open]')) {
            return true;
        }
        const selection = window.getSelection ? window.getSelection().toString() : '';
        if (selection && selection.length > 0) {
            return true;
        }

        return false;
    }

    startPolling() {
        this.timer = setInterval(() => {
            if (this.isUserInteracting()) {
                return;
            }

            const frame = this.element.closest('turbo-frame') || (this.element.tagName === 'TURBO-FRAME' ? this.element : null);
            if (frame && typeof frame.reload === 'function') {
                let scrollX = window.scrollX;
                let scrollY = window.scrollY;

                const currentHeight = frame.offsetHeight;
                if (currentHeight > 0) {
                    frame.style.minHeight = `${currentHeight}px`;
                }

                const onBeforeRender = (event) => {
                    if (event.target !== frame) return;
                    scrollX = window.scrollX;
                    scrollY = window.scrollY;
                    frame.removeEventListener('turbo:before-frame-render', onBeforeRender);
                };
                frame.addEventListener('turbo:before-frame-render', onBeforeRender);

                const restoreScroll = (event) => {
                    if (event.target !== frame) return;
                    frame.removeEventListener('turbo:frame-load', restoreScroll);

                    const restore = () => {
                        window.scrollTo({ left: scrollX, top: scrollY, behavior: 'instant' });
                    };

                    requestAnimationFrame(() => {
                        restore();
                        requestAnimationFrame(() => {
                            restore();
                            frame.style.minHeight = '';
                        });
                    });
                };
                frame.addEventListener('turbo:frame-load', restoreScroll);

                if (!frame.src && this.urlValue) {
                    frame.src = this.urlValue;
                } else {
                    frame.reload();
                }
            } else {
                const scrollX = window.scrollX;
                const scrollY = window.scrollY;

                if (window.Turbo) {
                    const restore = () => {
                        requestAnimationFrame(() => {
                            window.scrollTo({ left: scrollX, top: scrollY, behavior: 'instant' });
                        });
                    };
                    document.addEventListener('turbo:render', restore, { once: true });
                    document.addEventListener('turbo:load', restore, { once: true });
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

