import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'esnTab', 'statusTab', 'card', 'empty', 'count'];

    connect() {
        const savedProvider = window.sessionStorage.getItem('job-matcher-offer-provider-filter') || '';
        const providerExists = savedProvider === '' || this.tabTargets.some((tab) => (tab.dataset.offerFilterValue || tab.dataset.offerFilterProvider) === savedProvider);

        const savedEsn = window.sessionStorage.getItem('job-matcher-offer-esn-filter') || '';
        const esnExists = savedEsn === '' || this.esnTabTargets.some((tab) => tab.dataset.offerFilterEsn === savedEsn);

        const savedStatus = window.sessionStorage.getItem('job-matcher-offer-status-filter') || '';
        const statusExists = savedStatus === '' || this.statusTabTargets.some((tab) => tab.dataset.offerFilterStatus === savedStatus);

        this.applyFilter(
            providerExists ? savedProvider : '',
            esnExists ? savedEsn : '',
            statusExists ? savedStatus : ''
        );
    }

    select(event) {
        const filter = event.currentTarget.dataset.offerFilterValue ?? event.currentTarget.dataset.offerFilterProvider ?? '';
        window.sessionStorage.setItem('job-matcher-offer-provider-filter', filter);
        const currentEsn = this.getCurrentEsnFilter();
        const currentStatus = this.getCurrentStatusFilter();
        this.applyFilter(filter, currentEsn, currentStatus);
    }

    selectEsn(event) {
        const esnFilter = event.currentTarget.dataset.offerFilterEsn ?? '';
        window.sessionStorage.setItem('job-matcher-offer-esn-filter', esnFilter);
        const currentProvider = this.getCurrentProviderFilter();
        const currentStatus = this.getCurrentStatusFilter();
        this.applyFilter(currentProvider, esnFilter, currentStatus);
    }

    selectStatus(event) {
        const statusFilter = event.currentTarget.dataset.offerFilterStatus ?? '';
        window.sessionStorage.setItem('job-matcher-offer-status-filter', statusFilter);
        const currentProvider = this.getCurrentProviderFilter();
        const currentEsn = this.getCurrentEsnFilter();
        this.applyFilter(currentProvider, currentEsn, statusFilter);
    }

    getCurrentProviderFilter() {
        const activeTab = this.tabTargets.find((tab) => tab.classList.contains('active'));
        return activeTab ? (activeTab.dataset.offerFilterValue ?? activeTab.dataset.offerFilterProvider ?? '') : '';
    }

    getCurrentEsnFilter() {
        const activeEsnTab = this.esnTabTargets.find((tab) => tab.classList.contains('active'));
        return activeEsnTab ? (activeEsnTab.dataset.offerFilterEsn ?? '') : '';
    }

    getCurrentStatusFilter() {
        const activeStatusTab = this.statusTabTargets.find((tab) => tab.classList.contains('active'));
        return activeStatusTab ? (activeStatusTab.dataset.offerFilterStatus ?? '') : '';
    }

    applyFilter(providerFilter, esnFilter, statusFilter = '') {
        let visibleCount = 0;

        this.cardTargets.forEach((card) => {
            const cardProvider = card.dataset.offerFilterProvider ?? card.dataset.offerFilterValue ?? '';
            const cardEsn = card.dataset.offerFilterEsn ?? '0';
            const cardStatus = card.dataset.offerFilterStatus ?? 'UNPROCESSED';

            const matchProvider = providerFilter === '' || cardProvider === providerFilter;
            const matchEsn = esnFilter === ''
                || (esnFilter === 'esn' && cardEsn === '1')
                || (esnFilter === 'non_esn' && cardEsn === '0');
            const matchStatus = statusFilter === '' || cardStatus === statusFilter;

            const visible = matchProvider && matchEsn && matchStatus;
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

        this.statusTabTargets.forEach((tab) => {
            const val = tab.dataset.offerFilterStatus ?? '';
            const active = val === statusFilter;
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
