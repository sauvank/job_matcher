import { Controller } from '@hotwired/stimulus';

function getStored(key, fallback = '') {
    try {
        const val = window.localStorage.getItem(key);
        if (val !== null) return val;
    } catch {
        // ignore
    }
    try {
        return window.sessionStorage.getItem(key) || fallback;
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

export default class extends Controller {
    static targets = ['tab', 'row', 'empty'];

    connect() {
        const savedFilter = getStored('job-matcher-source-filter', '');
        const filterExists = savedFilter === '' || this.tabTargets.some((tab) => tab.dataset.sourceFilterValue === savedFilter);

        this.applyFilter(filterExists ? savedFilter : '');
    }

    select(event) {
        const filter = event.currentTarget.dataset.sourceFilterValue || '';
        setStored('job-matcher-source-filter', filter);
        this.applyFilter(filter);
    }

    applyFilter(filter) {
        let visibleCount = 0;

        this.rowTargets.forEach((row) => {
            const visible = filter === '' || row.dataset.sourceFilterValue === filter;
            row.hidden = !visible;
            visibleCount += visible ? 1 : 0;
        });

        this.tabTargets.forEach((tab) => {
            const active = (tab.dataset.sourceFilterValue || '') === filter;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visibleCount !== 0;
        }
    }
}
