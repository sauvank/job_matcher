import { Controller } from '@hotwired/stimulus';

function getStored(key, fallback = null) {
    try {
        const val = window.localStorage.getItem(key);
        if (val !== null) return val;
    } catch {
        // Local storage unavailable or restricted
    }
    try {
        return window.sessionStorage.getItem(key);
    } catch {
        return fallback;
    }
}

function setStored(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // ignore
    }
    try {
        window.sessionStorage.setItem(key, value);
    } catch {
        // ignore
    }
}

const STORAGE_KEYS = {
    UNSELECTED_SOURCES: 'job-matcher-offer-unselected-sources',
    LEGACY_SELECTED_SOURCES: 'job-matcher-offer-selected-sources',
    UNSELECTED_STATUSES: 'job-matcher-offer-unselected-statuses',
    LEGACY_SELECTED_STATUSES: 'job-matcher-offer-selected-statuses',
    EXCLUDE_NOT_INTERESTED: 'job-matcher-offer-exclude-not-interested',
    EXCLUDE_ESN: 'job-matcher-offer-exclude-esn',
    MIN_SCORE: 'job-matcher-offer-min-score',
};

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

        // 1. Sources restoration (newly added sources remain checked by default)
        const rawUnselectedSources = getStored(STORAGE_KEYS.UNSELECTED_SOURCES);
        if (rawUnselectedSources !== null) {
            try {
                const unselected = JSON.parse(rawUnselectedSources);
                if (Array.isArray(unselected)) {
                    this.sourceCheckboxTargets.forEach((cb) => {
                        cb.checked = !unselected.includes(cb.value);
                    });
                }
            } catch {
                // Keep default checked
            }
        } else {
            const rawLegacySources = getStored(STORAGE_KEYS.LEGACY_SELECTED_SOURCES);
            if (rawLegacySources !== null) {
                try {
                    const selected = JSON.parse(rawLegacySources);
                    if (Array.isArray(selected)) {
                        this.sourceCheckboxTargets.forEach((cb) => {
                            cb.checked = selected.includes(cb.value);
                        });
                    }
                } catch {
                    // Keep default checked
                }
            }
        }

        // 2. Exclude Not Interested
        const savedExcludeNotInterested = getStored(STORAGE_KEYS.EXCLUDE_NOT_INTERESTED) === '1';
        if (this.hasExcludeNotInterestedTarget) {
            this.excludeNotInterestedTarget.checked = savedExcludeNotInterested;
        }

        // 3. Exclude ESN
        const savedExcludeEsn = getStored(STORAGE_KEYS.EXCLUDE_ESN) === '1';
        if (this.hasExcludeEsnTarget) {
            this.excludeEsnTarget.checked = savedExcludeEsn;
        }

        // 4. Min score
        const savedMinScore = parseInt(getStored(STORAGE_KEYS.MIN_SCORE) || '0', 10) || 0;
        if (this.hasMinScoreTarget) {
            this.minScoreTarget.value = String(savedMinScore);
        }

        // 5. Statuses restoration
        const rawUnselectedStatuses = getStored(STORAGE_KEYS.UNSELECTED_STATUSES);
        if (rawUnselectedStatuses !== null) {
            try {
                const unselected = JSON.parse(rawUnselectedStatuses);
                if (Array.isArray(unselected)) {
                    this.statusCheckboxTargets.forEach((cb) => {
                        cb.checked = !unselected.includes(cb.value);
                    });
                }
            } catch {
                // Keep default checked
            }
        } else {
            const rawLegacyStatuses = getStored(STORAGE_KEYS.LEGACY_SELECTED_STATUSES);
            if (rawLegacyStatuses !== null) {
                try {
                    const selected = JSON.parse(rawLegacyStatuses);
                    if (Array.isArray(selected)) {
                        this.statusCheckboxTargets.forEach((cb) => {
                            cb.checked = selected.includes(cb.value);
                        });
                    }
                } catch {
                    // Keep default checked
                }
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
        const unselectedSources = this.sourceCheckboxTargets.filter((cb) => !cb.checked).map((cb) => cb.value);
        const selectedStatuses = this.getSelectedStatuses();
        const unselectedStatuses = this.statusCheckboxTargets.filter((cb) => !cb.checked).map((cb) => cb.value);
        const excludeNotInterested = this.hasExcludeNotInterestedTarget ? this.excludeNotInterestedTarget.checked : false;
        const excludeEsn = this.hasExcludeEsnTarget ? this.excludeEsnTarget.checked : false;
        const minScore = this.hasMinScoreTarget ? (parseInt(this.minScoreTarget.value, 10) || 0) : 0;

        setStored(STORAGE_KEYS.UNSELECTED_SOURCES, JSON.stringify(unselectedSources));
        setStored(STORAGE_KEYS.LEGACY_SELECTED_SOURCES, JSON.stringify(selectedSources));
        setStored(STORAGE_KEYS.UNSELECTED_STATUSES, JSON.stringify(unselectedStatuses));
        setStored(STORAGE_KEYS.LEGACY_SELECTED_STATUSES, JSON.stringify(selectedStatuses));
        setStored(STORAGE_KEYS.EXCLUDE_NOT_INTERESTED, excludeNotInterested ? '1' : '0');
        setStored(STORAGE_KEYS.EXCLUDE_ESN, excludeEsn ? '1' : '0');
        setStored(STORAGE_KEYS.MIN_SCORE, String(minScore));

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
            const isAnalyzing = card.dataset.offerFilterAnalyzing === '1';

            const matchProvider = allSourcesChecked || selectedSources.includes(cardProvider);
            const matchEsn = !excludeEsn || cardEsn === '0';
            const matchStatus = allStatusesChecked || selectedStatuses.includes(cardStatus);
            const matchExcludeNotInterested = !excludeNotInterested || cardStatus !== 'NOT_INTERESTED';
            const matchMinScore = minScore === 0 || isAnalyzing || (cardScore !== null && !isNaN(cardScore) && cardScore >= minScore);

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
