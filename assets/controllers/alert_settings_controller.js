import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['checkbox', 'thresholdGroup', 'slider', 'valueBadge', 'explanation', 'presetBtn'];

    connect() {
        this.updateToggleState();
        this.updateSlider();
    }

    toggle() {
        this.updateToggleState();
    }

    updateToggleState() {
        if (!this.hasCheckboxTarget || !this.hasThresholdGroupTarget) return;
        const isEnabled = this.checkboxTarget.checked;
        this.thresholdGroupTarget.classList.toggle('is-disabled', !isEnabled);
    }

    onSliderInput(event) {
        this.updateSlider(parseInt(event.target.value, 10));
    }

    setPreset(event) {
        event.preventDefault();
        const value = parseInt(event.currentTarget.dataset.presetValue, 10);
        if (this.hasSliderTarget) {
            this.sliderTarget.value = value;
            this.updateSlider(value);
        }
    }

    updateSlider(val) {
        const value = val !== undefined ? val : (this.hasSliderTarget ? parseInt(this.sliderTarget.value, 10) : 70);

        if (this.hasValueBadgeTarget) {
            this.valueBadgeTarget.textContent = `≥ ${value}%`;
            this.valueBadgeTarget.className = 'settings-score-badge ' + (value >= 75 ? 'badge-good' : (value >= 50 ? 'badge-medium' : 'badge-low'));
        }

        if (this.hasExplanationTarget) {
            let text = '';
            if (value >= 85) {
                text = 'Très ciblé : seules les opportunités d’exception seront sélectionnées.';
            } else if (value >= 75) {
                text = 'Excellente sélection : offres hautement compatibles avec votre profil.';
            } else if (value >= 60) {
                text = 'Équilibré (Recommandé) : bon compromis entre pertinence et volume d’offres.';
            } else {
                text = 'Large : vous recevrez davantage d’offres avec des compétences partielles.';
            }
            this.explanationTarget.textContent = text;
        }

        if (this.hasPresetBtnTargets) {
            this.presetBtnTargets.forEach(btn => {
                const btnVal = parseInt(btn.dataset.presetValue, 10);
                btn.classList.toggle('is-active', btnVal === value);
            });
        }
    }
}
