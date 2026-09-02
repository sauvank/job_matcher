import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'toggle'];

    connect() {
        this.element.classList.add('nav-ready');
    }

    toggle() {
        const isOpen = this.element.classList.toggle('nav-open');

        this.toggleTarget.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
}
