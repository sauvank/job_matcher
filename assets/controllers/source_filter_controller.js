import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'row', 'empty'];

    connect() {
        const savedFilter = window.sessionStorage.getItem('job-matcher-source-filter') || '';
        const filterExists = savedFilter === '' || this.tabTargets.some((tab) => tab.dataset.sourceFilterValue === savedFilter);

        this.applyFilter(filterExists ? savedFilter : '');
    }

    select(event) {
        const filter = event.currentTarget.dataset.sourceFilterValue || '';
        window.sessionStorage.setItem('job-matcher-source-filter', filter);
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
