import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'esnTab', 'card', 'empty', 'count'];

    connect() {
        const savedProvider = window.sessionStorage.getItem('job-matcher-offer-provider-filter') || '';
        const providerExists = savedProvider === '' || this.tabTargets.some((tab) => (tab.dataset.offerFilterValue || tab.dataset.offerFilterProvider) === savedProvider);

        const savedEsn = window.sessionStorage.getItem('job-matcher-offer-esn-filter') || '';
        const esnExists = savedEsn === '' || this.esnTabTargets.some((tab) => tab.dataset.offerFilterEsn === savedEsn);

        this.applyFilter(providerExists ? savedProvider : '', esnExists ? savedEsn : '');
    }

    select(event) {
        const filter = event.currentTarget.dataset.offerFilterValue ?? event.currentTarget.dataset.offerFilterProvider ?? '';
        window.sessionStorage.setItem('job-matcher-offer-provider-filter', filter);
        const currentEsn = this.getCurrentEsnFilter();
        this.applyFilter(filter, currentEsn);
    }

    selectEsn(event) {
        const esnFilter = event.currentTarget.dataset.offerFilterEsn ?? '';
        window.sessionStorage.setItem('job-matcher-offer-esn-filter', esnFilter);
        const currentProvider = this.getCurrentProviderFilter();
        this.applyFilter(currentProvider, esnFilter);
    }

    getCurrentProviderFilter() {
        const activeTab = this.tabTargets.find((tab) => tab.classList.contains('active'));
        return activeTab ? (activeTab.dataset.offerFilterValue ?? activeTab.dataset.offerFilterProvider ?? '') : '';
    }

    getCurrentEsnFilter() {
        const activeEsnTab = this.esnTabTargets.find((tab) => tab.classList.contains('active'));
        return activeEsnTab ? (activeEsnTab.dataset.offerFilterEsn ?? '') : '';
    }

    applyFilter(providerFilter, esnFilter) {
        let visibleCount = 0;

        this.cardTargets.forEach((card) => {
            const cardProvider = card.dataset.offerFilterProvider ?? card.dataset.offerFilterValue ?? '';
            const cardEsn = card.dataset.offerFilterEsn ?? '0';

            const matchProvider = providerFilter === '' || cardProvider === providerFilter;
            const matchEsn = esnFilter === ''
                || (esnFilter === 'esn' && cardEsn === '1')
                || (esnFilter === 'non_esn' && cardEsn === '0');

            const visible = matchProvider && matchEsn;
            card.hidden = !visible;
            visibleCount += visible ? 1 : 0;
        });

        this.tabTargets.forEach((tab) => {
            const val = tab.dataset.offerFilterProvider ?? tab.dataset.offerFilterValue ?? '';
            const active = val === providerFilter;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        this.esnTabTargets.forEach((tab) => {
            const val = tab.dataset.offerFilterEsn ?? '';
            const active = val === esnFilter;
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
