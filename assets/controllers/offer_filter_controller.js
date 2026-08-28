import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'esnTab', 'statusTab', 'excludeNotInterested', 'card', 'empty', 'count'];

    connect() {
        const savedProvider = window.sessionStorage.getItem('job-matcher-offer-provider-filter') || '';
        const providerExists = savedProvider === '' || this.tabTargets.some((tab) => (tab.dataset.offerFilterValue || tab.dataset.offerFilterProvider) === savedProvider);

        const savedEsn = window.sessionStorage.getItem('job-matcher-offer-esn-filter') || '';
        const esnExists = savedEsn === '' || this.esnTabTargets.some((tab) => tab.dataset.offerFilterEsn === savedEsn);

        const savedStatus = window.sessionStorage.getItem('job-matcher-offer-status-filter') || '';
        const statusExists = savedStatus === '' || this.statusTabTargets.some((tab) => tab.dataset.offerFilterStatus === savedStatus);

        const savedExclude = window.sessionStorage.getItem('job-matcher-offer-exclude-not-interested') === '1';
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = savedExclude;
        }

        this.applyFilter(
            providerExists ? savedProvider : '',
            esnExists ? savedEsn : '',
            statusExists ? savedStatus : '',
            savedExclude
        );
    }

    select(event) {
        const filter = event.currentTarget.dataset.offerFilterValue ?? event.currentTarget.dataset.offerFilterProvider ?? '';
        window.sessionStorage.setItem('job-matcher-offer-provider-filter', filter);
        const currentEsn = this.getCurrentEsnFilter();
        const currentStatus = this.getCurrentStatusFilter();
        const exclude = this.getExcludeNotInterested();
        this.applyFilter(filter, currentEsn, currentStatus, exclude);
    }

    selectEsn(event) {
        const esnFilter = event.currentTarget.dataset.offerFilterEsn ?? '';
        window.sessionStorage.setItem('job-matcher-offer-esn-filter', esnFilter);
        const currentProvider = this.getCurrentProviderFilter();
        const currentStatus = this.getCurrentStatusFilter();
        const exclude = this.getExcludeNotInterested();
        this.applyFilter(currentProvider, esnFilter, currentStatus, exclude);
    }

    selectStatus(event) {
        const statusFilter = event.currentTarget.dataset.offerFilterStatus ?? '';
        window.sessionStorage.setItem('job-matcher-offer-status-filter', statusFilter);
        const currentProvider = this.getCurrentProviderFilter();
        const currentEsn = this.getCurrentEsnFilter();
        const exclude = this.getExcludeNotInterested();
        this.applyFilter(currentProvider, currentEsn, statusFilter, exclude);
    }

    toggleExcludeNotInterested(event) {
        const isChecked = event.currentTarget.checked;
        window.sessionStorage.setItem('job-matcher-offer-exclude-not-interested', isChecked ? '1' : '0');
        const currentProvider = this.getCurrentProviderFilter();
        const currentEsn = this.getCurrentEsnFilter();
        const currentStatus = this.getCurrentStatusFilter();
        this.applyFilter(currentProvider, currentEsn, currentStatus, isChecked);
    }

    getExcludeNotInterested() {
        return this.hasExcludeNotInterestedTarget ? this.excludeNotInterestedTarget.checked : false;
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

    applyFilter(providerFilter, esnFilter, statusFilter = '', excludeNotInterested = false) {
        let visibleCount = 0;

        this.cardTargets.forEach((card) => {
            const cardProvider = card.dataset.offerFilterProvider ?? card.dataset.offerFilterValue ?? '';
            const cardEsn = card.dataset.offerFilterEsn ?? '0';
            const cardStatus = card.dataset.offerFilterStatus ?? 'UNPROCESSED';

            const matchProvider = providerFilter === '' || cardProvider === providerFilter;
            const matchEsn = esnFilter === ''
                || (esnFilter === 'esn' && cardEsn === '1')
                || (esnFilter === 'non_esn' && cardEsn === '0');
            const matchStatus = statusFilter === ''
                || (statusFilter === 'ACTIVE' ? cardStatus !== 'NOT_INTERESTED' : cardStatus === statusFilter);
            const matchExclude = (!excludeNotInterested || statusFilter === 'NOT_INTERESTED')
                ? true
                : cardStatus !== 'NOT_INTERESTED';

            const visible = matchProvider && matchEsn && matchStatus && matchExclude;
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
