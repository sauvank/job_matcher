import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'dropdown',
        'sourceCheckbox',
        'statusCheckbox',
        'excludeNotInterested',
        'excludeEsn',
        'minScore',
        'minScoreLabel',
        'presetBtn',
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

        const rawSources = window.sessionStorage.getItem('job-matcher-offer-selected-sources');
        if (rawSources !== null) {
            try {
                const selected = JSON.parse(rawSources);
                if (Array.isArray(selected)) {
                    this.sourceCheckboxTargets.forEach((cb) => {
                        cb.checked = selected.includes(cb.value);
                    });
                }
            } catch {
                // Keep default checked
            }
        }

        const savedExcludeNotInterested = window.sessionStorage.getItem('job-matcher-offer-exclude-not-interested') === '1';
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = savedExcludeNotInterested;
        }

        const savedExcludeEsn = window.sessionStorage.getItem('job-matcher-offer-exclude-esn') === '1';
        if (this.hasExcludeEsnTarget) {
            this.excludeEsnTarget.checked = savedExcludeEsn;
        }

        const savedMinScore = parseInt(window.sessionStorage.getItem('job-matcher-offer-min-score') || '0', 10) || 0;
        if (this.hasMinScoreTarget) {
            this.minScoreTarget.value = String(savedMinScore);
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
            this.getSelectedSources(),
            this.getSelectedStatuses(),
            savedExcludeNotInterested,
            savedExcludeEsn,
            savedMinScore
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

    setMinScorePreset(event) {
        const score = parseInt(event.currentTarget.dataset.score, 10) || 0;
        if (this.hasMinScoreTarget) {
            this.minScoreTarget.value = String(score);
        }
        this.updateFilters();
    }

    updateFilters() {
        const selectedSources = this.getSelectedSources();
        const selectedStatuses = this.getSelectedStatuses();
        const excludeNotInterested = this.hasExcludeNotInterestedTarget ? this.excludeNotInterestedTarget.checked : false;
        const excludeEsn = this.hasExcludeEsnTarget ? this.excludeEsnTarget.checked : false;
        const minScore = this.hasMinScoreTarget ? (parseInt(this.minScoreTarget.value, 10) || 0) : 0;

        window.sessionStorage.setItem('job-matcher-offer-selected-sources', JSON.stringify(selectedSources));
        window.sessionStorage.setItem('job-matcher-offer-exclude-not-interested', excludeNotInterested ? '1' : '0');
        window.sessionStorage.setItem('job-matcher-offer-exclude-esn', excludeEsn ? '1' : '0');
        window.sessionStorage.setItem('job-matcher-offer-min-score', String(minScore));
        window.sessionStorage.setItem('job-matcher-offer-selected-statuses', JSON.stringify(selectedStatuses));

        this.applyFilter(selectedSources, selectedStatuses, excludeNotInterested, excludeEsn, minScore);
    }

    resetDropdown() {
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = false;
        }
        if (this.hasExcludeEsnTarget) {
            this.excludeEsnTarget.checked = false;
        }
        if (this.hasMinScoreTarget) {
            this.minScoreTarget.value = '0';
        }
        this.sourceCheckboxTargets.forEach((cb) => {
            cb.checked = true;
        });
        this.statusCheckboxTargets.forEach((cb) => {
            cb.checked = true;
        });
        this.updateFilters();
    }

    checkAllSources() {
        this.sourceCheckboxTargets.forEach((cb) => {
            cb.checked = true;
        });
        this.updateFilters();
    }

    uncheckAllSources() {
        this.sourceCheckboxTargets.forEach((cb) => {
            cb.checked = false;
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

    getSelectedSources() {
        return this.sourceCheckboxTargets.filter((cb) => cb.checked).map((cb) => cb.value);
    }

    getSelectedStatuses() {
        return this.statusCheckboxTargets.filter((cb) => cb.checked).map((cb) => cb.value);
    }

    applyFilter(selectedSources, selectedStatuses, excludeNotInterested = false, excludeEsn = false, minScore = 0) {
        let visibleCount = 0;
        const allSourcesChecked = selectedSources.length === this.sourceCheckboxTargets.length;
        const allStatusesChecked = selectedStatuses.length === this.statusCheckboxTargets.length;

        if (this.hasMinScoreLabelTarget) {
            this.minScoreLabelTarget.textContent = minScore > 0 ? `≥ ${minScore}%` : 'Toutes (0%)';
        }

        this.presetBtnTargets.forEach((btn) => {
            const btnScore = parseInt(btn.dataset.score, 10) || 0;
            btn.classList.toggle('is-active', btnScore === minScore);
        });

        this.cardTargets.forEach((card) => {
            const cardProvider = card.dataset.offerFilterProvider ?? card.dataset.offerFilterValue ?? '';
            const cardEsn = card.dataset.offerFilterEsn ?? '0';
            const cardStatus = card.dataset.offerFilterStatus ?? 'UNPROCESSED';
            const rawScore = card.dataset.offerFilterScore;
            const cardScore = (rawScore !== '' && rawScore !== undefined) ? parseInt(rawScore, 10) : null;

            const matchProvider = allSourcesChecked || selectedSources.includes(cardProvider);
            const matchEsn = !excludeEsn || cardEsn === '0';
            const matchStatus = allStatusesChecked || selectedStatuses.includes(cardStatus);
            const matchExcludeNotInterested = !excludeNotInterested || cardStatus !== 'NOT_INTERESTED';
            const matchMinScore = minScore === 0 || (cardScore !== null && !isNaN(cardScore) && cardScore >= minScore);

            const visible = matchProvider && matchEsn && matchStatus && matchExcludeNotInterested && matchMinScore;
            card.hidden = !visible;
            visibleCount += visible ? 1 : 0;
        });

        // Compute active filters badge count
        let activeFilterCount = 0;
        if (excludeNotInterested) activeFilterCount++;
        if (excludeEsn) activeFilterCount++;
        if (minScore > 0) activeFilterCount++;
        if (!allSourcesChecked) activeFilterCount++;
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
