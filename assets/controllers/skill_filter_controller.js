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
    static targets = ['level', 'row', 'count', 'empty'];

    connect() {
        if (this.hasLevelTarget) {
            const savedLevel = getStored('job-matcher-skill-level-filter', '');
            if (savedLevel !== '') {
                this.levelTarget.value = savedLevel;
            }
        }
        this.filter();
    }

    filter() {
        if (!this.hasLevelTarget) {
            return;
        }

        const selectedLevel = this.levelTarget.value;
        setStored('job-matcher-skill-level-filter', selectedLevel);
        let visibleCount = 0;

        this.rowTargets.forEach((row) => {
            const visible = selectedLevel === '' || row.dataset.skillLevel === selectedLevel;
            row.hidden = !visible;
            visibleCount += visible ? 1 : 0;
        });

        this.countTarget.textContent = `${visibleCount} compétence${visibleCount > 1 ? 's' : ''}`;
        this.emptyTarget.hidden = visibleCount !== 0;
    }
}
