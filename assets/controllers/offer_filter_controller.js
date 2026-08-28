import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'esnTab', 'statusTab', 'excludeNotInterested', 'excludeEsn', 'card', 'empty', 'count'];

    connect() {
        const savedProvider = window.sessionStorage.getItem('job-matcher-offer-provider-filter') || '';
        const providerExists = savedProvider === '' || this.tabTargets.some((tab) => (tab.dataset.offerFilterValue || tab.dataset.offerFilterProvider) === savedProvider);

        const savedEsn = window.sessionStorage.getItem('job-matcher-offer-esn-filter') || '';
        const esnExists = savedEsn === '' || this.esnTabTargets.some((tab) => tab.dataset.offerFilterEsn === savedEsn);

        const savedStatus = window.sessionStorage.getItem('job-matcher-offer-status-filter') || '';
        const statusExists = savedStatus === '' || this.statusTabTargets.some((tab) => tab.dataset.offerFilterStatus === savedStatus);

        const savedExcludeNotInterested = window.sessionStorage.getItem('job-matcher-offer-exclude-not-interested') === '1';
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = savedExcludeNotInterested;
        }

        const savedExcludeEsn = window.sessionStorage.getItem('job-matcher-offer-exclude-esn') === '1';
        if (this.hasExcludeEsnTarget) {
            this.excludeEsnTarget.checked = savedExcludeEsn;
        }

        this.applyFilter(
            providerExists ? savedProvider : '',
            esnExists ? savedEsn : '',
            statusExists ? savedStatus : '',
            savedExcludeNotInterested,
            savedExcludeEsn
        );
    }

    select(event) {
        const filter = event.currentTarget.dataset.offerFilterValue ?? event.currentTarget.dataset.offerFilterProvider ?? '';
        window.sessionStorage.setItem('job-matcher-offer-provider-filter', filter);
        const currentEsn = this.getCurrentEsnFilter();
        const currentStatus = this.getCurrentStatusFilter();
        const excludeNotInterested = this.getExcludeNotInterested();
        const excludeEsn = this.getExcludeEsn();
        this.applyFilter(filter, currentEsn, currentStatus, excludeNotInterested, excludeEsn);
    }

    selectEsn(event) {
        const esnFilter = event.currentTarget.dataset.offerFilterEsn ?? '';
        window.sessionStorage.setItem('job-matcher-offer-esn-filter', esnFilter);
        const currentProvider = this.getCurrentProviderFilter();
        const currentStatus = this.getCurrentStatusFilter();
        const excludeNotInterested = this.getExcludeNotInterested();
        const excludeEsn = this.getExcludeEsn();
        this.applyFilter(currentProvider, esnFilter, currentStatus, excludeNotInterested, excludeEsn);
    }

    selectStatus(event) {
        const statusFilter = event.currentTarget.dataset.offerFilterStatus ?? '';
        window.sessionStorage.setItem('job-matcher-offer-status-filter', statusFilter);
        const currentProvider = this.getCurrentProviderFilter();
        const currentEsn = this.getCurrentEsnFilter();
        const excludeNotInterested = this.getExcludeNotInterested();
        const excludeEsn = this.getExcludeEsn();
        this.applyFilter(currentProvider, currentEsn, statusFilter, excludeNotInterested, excludeEsn);
    }

    toggleExcludeNotInterested(event) {
        const isChecked = event.currentTarget.checked;
        window.sessionStorage.setItem('job-matcher-offer-exclude-not-interested', isChecked ? '1' : '0');
        const currentProvider = this.getCurrentProviderFilter();
        const currentEsn = this.getCurrentEsnFilter();
        const currentStatus = this.getCurrentStatusFilter();
        const excludeEsn = this.getExcludeEsn();
        this.applyFilter(currentProvider, currentEsn, currentStatus, isChecked, excludeEsn);
    }

    toggleExcludeEsn(event) {
        const isChecked = event.currentTarget.checked;
        window.sessionStorage.setItem('job-matcher-offer-exclude-esn', isChecked ? '1' : '0');
        const currentProvider = this.getCurrentProviderFilter();
        const currentEsn = this.getCurrentEsnFilter();
        const currentStatus = this.getCurrentStatusFilter();
        const excludeNotInterested = this.getExcludeNotInterested();
        this.applyFilter(currentProvider, currentEsn, currentStatus, excludeNotInterested, isChecked);
    }

    getExcludeNotInterested() {
        return this.hasExcludeNotInterestedTarget ? this.excludeNotInterestedTarget.checked : false;
    }

    getExcludeEsn() {
        return this.hasExcludeEsnTarget ? this.excludeEsnTarget.checked : false;
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

    applyFilter(providerFilter, esnFilter, statusFilter = '', excludeNotInterested = false, excludeEsn = false) {
        let visibleCount = 0;

        this.cardTargets.forEach((card) => {
            const cardProvider = card.dataset.offerFilterProvider ?? card.dataset.offerFilterValue ?? '';
            const cardEsn = card.dataset.offerFilterEsn ?? '0';
            const cardStatus = card.dataset.offerFilterStatus ?? 'UNPROCESSED';

            const matchProvider = providerFilter === '' || cardProvider === providerFilter;
            const matchEsn = esnFilter === ''
                ? (!excludeEsn || cardEsn === '0')
                : (esnFilter === 'esn' ? cardEsn === '1' : cardEsn === '0');
            const matchStatus = statusFilter === ''
                || (statusFilter === 'ACTIVE' ? cardStatus !== 'NOT_INTERESTED' : cardStatus === statusFilter);
            const matchExcludeNotInterested = (!excludeNotInterested || statusFilter === 'NOT_INTERESTED')
                ? true
                : cardStatus !== 'NOT_INTERESTED';

            const visible = matchProvider && matchEsn && matchStatus && matchExcludeNotInterested;
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
