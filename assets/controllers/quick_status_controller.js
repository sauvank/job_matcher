import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['favoriteBtn', 'discardBtn'];
    static values = {
        matchId: Number,
        token: String,
        url: String,
    };

    async toggleFavorite(event) {
        event.preventDefault();
        event.stopPropagation();
        const card = this.element.closest('[data-offer-filter-target="card"]');
        const currentStatus = card ? card.dataset.offerFilterStatus : '';
        const newStatus = currentStatus === 'INTERESTED' ? 'UNPROCESSED' : 'INTERESTED';
        await this.submitStatus(newStatus);
    }

    async toggleDiscard(event) {
        event.preventDefault();
        event.stopPropagation();
        const card = this.element.closest('[data-offer-filter-target="card"]');
        const currentStatus = card ? card.dataset.offerFilterStatus : '';
        const newStatus = currentStatus === 'NOT_INTERESTED' ? 'UNPROCESSED' : 'NOT_INTERESTED';
        await this.submitStatus(newStatus);
    }

    async submitStatus(status) {
        const card = this.element.closest('[data-offer-filter-target="card"]');
        const badge = card ? card.querySelector(`[data-status-badge-for="${this.matchIdValue}"]`) : null;

        const formData = new FormData();
        formData.append('_token', this.tokenValue);
        formData.append('status', status);

        // Optimistic UI updates
        this.updateButtons(status);
        if (card) {
            card.dataset.offerFilterStatus = status;
            if (status === 'NOT_INTERESTED') {
                card.classList.add('offer-card-not-interested');
            } else {
                card.classList.remove('offer-card-not-interested');
            }
        }

        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                if (badge) {
                    if (data.status === 'UNPROCESSED') {
                        badge.hidden = true;
                    } else {
                        badge.hidden = false;
                        badge.className = `badge ${data.badgeClass} offer-status-badge`;
                        badge.textContent = `${data.icon} ${data.label}`;
                    }
                }
                // Notify filter controller to recalculate visible counts and apply active filters
                window.dispatchEvent(new CustomEvent('offer-status-updated', {
                    detail: { matchId: this.matchIdValue, status: data.status },
                }));
            }
        } catch (e) {
            console.error('Failed to update status', e);
        }
    }

    updateButtons(status) {
        if (this.hasFavoriteBtnTarget) {
            if (status === 'INTERESTED') {
                this.favoriteBtnTarget.classList.add('is-active');
                this.favoriteBtnTarget.setAttribute('title', 'Retirer des favoris (marquer À traiter)');
            } else {
                this.favoriteBtnTarget.classList.remove('is-active');
                this.favoriteBtnTarget.setAttribute('title', 'Mettre en favoris (M’intéresse)');
            }
        }
        if (this.hasDiscardBtnTarget) {
            if (status === 'NOT_INTERESTED') {
                this.discardBtnTarget.classList.add('is-active');
                this.discardBtnTarget.setAttribute('title', 'Annuler l’écartement (marquer À traiter)');
            } else {
                this.discardBtnTarget.classList.remove('is-active');
                this.discardBtnTarget.setAttribute('title', 'Écarter l’offre (Ne m’intéresse pas)');
            }
        }
    }
}
