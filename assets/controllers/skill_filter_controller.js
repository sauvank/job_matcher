import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['level', 'row', 'count', 'empty'];

    connect() {
        this.filter();
    }

    filter() {
        if (!this.hasLevelTarget) {
            return;
        }

        const selectedLevel = this.levelTarget.value;
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
