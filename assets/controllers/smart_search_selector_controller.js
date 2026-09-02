import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'submitBtn', 'toggleBtn'];

    connect() {
        this.updateCount();
    }

    updateCount() {
        const checked = this.checkboxTargets.filter((cb) => cb.checked);
        const count = checked.length;

        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = count === 0;
            if (count > 0) {
                this.submitBtnTarget.textContent = `🚀 Ajouter et rechercher (${count})`;
            } else {
                this.submitBtnTarget.textContent = '🚀 Ajouter et rechercher les intitulés sélectionnés';
            }
        }

        if (this.hasToggleBtnTarget) {
            const allChecked = this.checkboxTargets.length > 0 && checked.length === this.checkboxTargets.length;
            this.toggleBtnTarget.textContent = allChecked ? 'Tout désélectionner' : 'Tout sélectionner';
        }
    }

    toggleAll() {
        const allChecked = this.checkboxTargets.length > 0 && this.checkboxTargets.every((cb) => cb.checked);
        this.checkboxTargets.forEach((cb) => {
            cb.checked = !allChecked;
        });
        this.updateCount();
    }
}
