import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'tab',
        'esnTab',
        'dropdown',
        'statusCheckbox',
        'excludeNotInterested',
        'excludeEsn',
        'activeBadge',
        'card',
        'empty',
        'count'
    ];

    connect() {
        this.boundCloseOnOutsideClick = (e) => {
            if (this.hasDropdownTarget && this.dropdownTarget.open && !this.dropdownTarget.contains(e.target)) {
                this.dropdownTarget.open = false;
            }
        };
        document.addEventListener('click', this.boundCloseOnOutsideClick);

        this.boundOnOfferStatusUpdated = () => {
            this.updateFilters();
        };
        window.addEventListener('offer-status-updated', this.boundOnOfferStatusUpdated);

        const savedProvider = window.sessionStorage.getItem('job-matcher-offer-provider-filter') || '';
        const providerExists = savedProvider === '' || this.tabTargets.some((tab) => (tab.dataset.offerFilterValue || tab.dataset.offerFilterProvider) === savedProvider);

        const savedEsn = window.sessionStorage.getItem('job-matcher-offer-esn-filter') || '';
        const esnExists = savedEsn === '' || this.esnTabTargets.some((tab) => tab.dataset.offerFilterEsn === savedEsn);

        const savedExcludeNotInterested = window.sessionStorage.getItem('job-matcher-offer-exclude-not-interested') === '1';
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = savedExcludeNotInterested;
        }

        const savedExcludeEsn = window.sessionStorage.getItem('job-matcher-offer-exclude-esn') === '1';
        if (this.hasExcludeEsnTarget) {
            this.excludeEsnTarget.checked = savedExcludeEsn;
        }

        const rawStatuses = window.sessionStorage.getItem('job-matcher-offer-selected-statuses');
        if (rawStatuses !== null) {
            try {
                const selected = JSON.parse(rawStatuses);
                if (Array.isArray(selected)) {
                    this.statusCheckboxTargets.forEach((cb) => {
                        cb.checked = selected.includes(cb.value);
                    });
                }
            } catch {
                // Keep default checked
            }
        }

        this.applyFilter(
            providerExists ? savedProvider : '',
            esnExists ? savedEsn : '',
            this.getSelectedStatuses(),
            savedExcludeNotInterested,
            savedExcludeEsn
        );
    }

    disconnect() {
        if (this.boundCloseOnOutsideClick) {
            document.removeEventListener('click', this.boundCloseOnOutsideClick);
        }
        if (this.boundOnOfferStatusUpdated) {
            window.removeEventListener('offer-status-updated', this.boundOnOfferStatusUpdated);
        }
    }

    select(event) {
        const filter = event.currentTarget.dataset.offerFilterValue ?? event.currentTarget.dataset.offerFilterProvider ?? '';
        window.sessionStorage.setItem('job-matcher-offer-provider-filter', filter);
        this.updateFilters();
    }

    selectEsn(event) {
        const esnFilter = event.currentTarget.dataset.offerFilterEsn ?? '';
        window.sessionStorage.setItem('job-matcher-offer-esn-filter', esnFilter);
        this.updateFilters();
    }

    updateFilters() {
        const provider = this.getCurrentProviderFilter();
        const esn = this.getCurrentEsnFilter();
        const selectedStatuses = this.getSelectedStatuses();
        const excludeNotInterested = this.hasExcludeNotInterestedTarget ? this.excludeNotInterestedTarget.checked : false;
        const excludeEsn = this.hasExcludeEsnTarget ? this.excludeEsnTarget.checked : false;

        window.sessionStorage.setItem('job-matcher-offer-exclude-not-interested', excludeNotInterested ? '1' : '0');
        window.sessionStorage.setItem('job-matcher-offer-exclude-esn', excludeEsn ? '1' : '0');
        window.sessionStorage.setItem('job-matcher-offer-selected-statuses', JSON.stringify(selectedStatuses));

        this.applyFilter(provider, esn, selectedStatuses, excludeNotInterested, excludeEsn);
    }

    resetDropdown() {
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = false;
        }
        if (this.hasExcludeEsnTarget) {
            this.excludeEsnTarget.checked = false;
        }
        this.statusCheckboxTargets.forEach((cb) => {
            cb.checked = true;
        });
        this.updateFilters();
    }

    checkAllStatuses() {
        this.statusCheckboxTargets.forEach((cb) => {
            cb.checked = true;
        });
        this.updateFilters();
    }

    uncheckAllStatuses() {
        this.statusCheckboxTargets.forEach((cb) => {
            cb.checked = false;
        });
        this.updateFilters();
    }

    getSelectedStatuses() {
        return this.statusCheckboxTargets.filter((cb) => cb.checked).map((cb) => cb.value);
    }

    getCurrentProviderFilter() {
        const activeTab = this.tabTargets.find((tab) => tab.classList.contains('active'));
        return activeTab ? (activeTab.dataset.offerFilterValue ?? activeTab.dataset.offerFilterProvider ?? '') : '';
    }

    getCurrentEsnFilter() {
        const activeEsnTab = this.esnTabTargets.find((tab) => tab.classList.contains('active'));
        return activeEsnTab ? (activeEsnTab.dataset.offerFilterEsn ?? '') : '';
    }

    applyFilter(providerFilter, esnFilter, selectedStatuses, excludeNotInterested = false, excludeEsn = false) {
        let visibleCount = 0;
        const allStatusesChecked = selectedStatuses.length === this.statusCheckboxTargets.length;

        this.cardTargets.forEach((card) => {
            const cardProvider = card.dataset.offerFilterProvider ?? card.dataset.offerFilterValue ?? '';
            const cardEsn = card.dataset.offerFilterEsn ?? '0';
            const cardStatus = card.dataset.offerFilterStatus ?? 'UNPROCESSED';

            const matchProvider = providerFilter === '' || cardProvider === providerFilter;
            const matchEsn = esnFilter === ''
                ? (!excludeEsn || cardEsn === '0')
                : (esnFilter === 'esn' ? cardEsn === '1' : cardEsn === '0');

            const matchStatus = allStatusesChecked || selectedStatuses.includes(cardStatus);
            const matchExcludeNotInterested = !excludeNotInterested || cardStatus !== 'NOT_INTERESTED';

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

        // Compute active filters badge count
        let activeFilterCount = 0;
        if (excludeNotInterested) activeFilterCount++;
        if (excludeEsn) activeFilterCount++;
        if (!allStatusesChecked) activeFilterCount++;

        if (this.hasActiveBadgeTarget) {
            this.activeBadgeTarget.textContent = String(activeFilterCount);
            this.activeBadgeTarget.hidden = activeFilterCount === 0;
        }

        if (this.hasCountTarget) {
            this.countTarget.textContent = String(visibleCount);
        }
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visibleCount !== 0;
        }
    }
}
