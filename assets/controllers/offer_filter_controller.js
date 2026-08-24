import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'card', 'empty', 'count'];

    connect() {
        const savedFilter = window.sessionStorage.getItem('job-matcher-offer-provider-filter') || '';
        const filterExists = savedFilter === '' || this.tabTargets.some((tab) => tab.dataset.offerFilterValue === savedFilter);

        this.applyFilter(filterExists ? savedFilter : '');
    }

    select(event) {
        const filter = event.currentTarget.dataset.offerFilterValue || '';
        window.sessionStorage.setItem('job-matcher-offer-provider-filter', filter);
        this.applyFilter(filter);
    }

    applyFilter(filter) {
        let visibleCount = 0;

        this.cardTargets.forEach((card) => {
            const visible = filter === '' || card.dataset.offerFilterValue === filter;
            card.hidden = !visible;
            visibleCount += visible ? 1 : 0;
        });

        this.tabTargets.forEach((tab) => {
            const active = (tab.dataset.offerFilterValue || '') === filter;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        if (this.hasCountTarget) {
            this.countTarget.textContent = String(visibleCount);
        }
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visibleCount !== 0;
        }
    }
}
