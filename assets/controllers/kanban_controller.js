import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['column', 'card', 'count', 'searchInput', 'filterTab', 'emptyColumn'];
    static values = {
        statusUrl: String,
        noteUrl: String,
    };

    connect() {
        this.updateAllCounts();
    }

    dragStart(event) {
        const card = event.target.closest('[data-kanban-target="card"]');
        if (!card) return;

        event.dataTransfer.setData('text/plain', card.dataset.matchId);
        event.dataTransfer.setData('application/json', JSON.stringify({
            matchId: card.dataset.matchId,
            token: card.dataset.token,
            status: card.dataset.status,
            statusUrl: card.dataset.statusUrl,
        }));
        event.dataTransfer.effectAllowed = 'move';
        card.classList.add('is-dragging');
    }

    dragEnd(event) {
        const card = event.target.closest('[data-kanban-target="card"]');
        if (card) {
            card.classList.remove('is-dragging');
        }
        this.clearDragOver();
    }

    dragOver(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const column = event.target.closest('[data-kanban-column]');
        if (column) {
            column.classList.add('is-dragover');
        }
    }

    dragLeave(event) {
        const column = event.target.closest('[data-kanban-column]');
        if (column && !column.contains(event.relatedTarget)) {
            column.classList.remove('is-dragover');
        }
    }

    async drop(event) {
        event.preventDefault();
        this.clearDragOver();

        const column = event.target.closest('[data-kanban-column]');
        if (!column) return;

        const targetStatus = column.dataset.kanbanColumn;
        const rawData = event.dataTransfer.getData('application/json');
        if (!rawData) return;

        try {
            const data = JSON.parse(rawData);
            const matchId = data.matchId;
            const currentStatus = data.status;
            const token = data.token;
            const statusUrl = data.statusUrl;

            if (currentStatus === targetStatus) return;

            const card = this.element.querySelector(`[data-match-id="${matchId}"]`);
            if (!card) return;

            const cardsContainer = column.querySelector('.kanban-cards');
            if (cardsContainer) {
                cardsContainer.prepend(card);
            }
            card.dataset.status = targetStatus;

            // Update select if exists inside card
            const statusSelect = card.querySelector('select[data-action*="changeStatus"]');
            if (statusSelect) {
                statusSelect.value = targetStatus;
            }

            this.updateAllCounts();

            // Submit AJAX update
            await this.submitStatusUpdate(statusUrl, token, targetStatus, card);
        } catch (e) {
            console.error('Failed to parse dropped card data', e);
        }
    }

    async changeStatus(event) {
        const select = event.target;
        const targetStatus = select.value;
        const card = select.closest('[data-kanban-target="card"]');
        if (!card) return;

        const currentStatus = card.dataset.status;
        if (currentStatus === targetStatus) return;

        const targetColumn = this.element.querySelector(`[data-kanban-column="${targetStatus}"]`);
        if (!targetColumn) return;

        const cardsContainer = targetColumn.querySelector('.kanban-cards');
        if (cardsContainer) {
            cardsContainer.prepend(card);
        }
        card.dataset.status = targetStatus;

        this.updateAllCounts();

        await this.submitStatusUpdate(card.dataset.statusUrl, card.dataset.token, targetStatus, card);
    }

    async submitStatusUpdate(statusUrl, token, newStatus, card) {
        const formData = new FormData();
        formData.append('_token', token);
        formData.append('status', newStatus);

        try {
            const response = await fetch(statusUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                console.error('Status update failed');
            }
        } catch (e) {
            console.error('Error updating status', e);
        }
    }

    async toggleNoteEditor(event) {
        event.preventDefault();
        const card = event.target.closest('[data-kanban-target="card"]');
        if (!card) return;

        const noteBox = card.querySelector('.kanban-card-note-box');
        const noteForm = card.querySelector('.kanban-card-note-form');
        if (noteForm && noteBox) {
            const isHidden = noteForm.hidden;
            noteForm.hidden = !isHidden;
            if (isHidden) {
                const textarea = noteForm.querySelector('textarea');
                if (textarea) textarea.focus();
            }
        }
    }

    async saveNote(event) {
        event.preventDefault();
        const form = event.target.closest('form');
        if (!form) return;

        const card = form.closest('[data-kanban-target="card"]');
        const noteUrl = form.action;
        const formData = new FormData(form);

        try {
            const response = await fetch(noteUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                const noteTextEl = card.querySelector('.kanban-card-note-text');
                const noteBox = card.querySelector('.kanban-card-note-box');
                const noteBtn = card.querySelector('.kanban-note-toggle-btn');
                
                if (noteTextEl) {
                    noteTextEl.textContent = data.note || '';
                }
                if (noteBox) {
                    noteBox.hidden = !data.note;
                }
                if (noteBtn) {
                    noteBtn.classList.toggle('has-note', Boolean(data.note));
                    noteBtn.setAttribute('title', data.note ? 'Modifier la note' : 'Ajouter une note');
                }
                form.hidden = true;
            }
        } catch (e) {
            console.error('Failed to save note', e);
        }
    }

    cancelNote(event) {
        event.preventDefault();
        const form = event.target.closest('form');
        if (form) {
            form.hidden = true;
        }
    }

    filter(event) {
        const query = (this.hasSearchInputTarget ? this.searchInputTarget.value : '').toLowerCase().trim();
        const activeTab = this.filterTabTargets.find(t => t.classList.contains('active'));
        const searchFilter = activeTab ? activeTab.dataset.searchLabel : '';

        this.cardTargets.forEach(card => {
            const cardSearchLabel = (card.dataset.searchLabel || '').toLowerCase();
            const cardText = (card.textContent || '').toLowerCase();

            const matchesSearch = !query || cardText.includes(query);
            const matchesTab = !searchFilter || cardSearchLabel === searchFilter.toLowerCase();

            if (matchesSearch && matchesTab) {
                card.hidden = false;
            } else {
                card.hidden = true;
            }
        });

        this.updateAllCounts();
    }

    selectTab(event) {
        event.preventDefault();
        const clickedTab = event.currentTarget;
        this.filterTabTargets.forEach(tab => tab.classList.remove('active'));
        clickedTab.classList.add('active');
        this.filter();
    }

    clearDragOver() {
        this.columnTargets.forEach(col => col.classList.remove('is-dragover'));
    }

    updateAllCounts() {
        this.columnTargets.forEach(column => {
            const visibleCards = column.querySelectorAll('[data-kanban-target="card"]:not([hidden])').length;
            const countEl = column.querySelector('[data-kanban-count]');
            const emptyEl = column.querySelector('.kanban-empty-column');

            if (countEl) {
                countEl.textContent = visibleCards;
            }
            if (emptyEl) {
                emptyEl.hidden = visibleCards > 0;
            }
        });
    }
}
