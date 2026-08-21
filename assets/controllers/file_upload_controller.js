import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'filename', 'zone', 'submit'];

    connect() {
        this.update();
    }

    update() {
        const file = this.inputTarget.files?.[0];

        this.filenameTarget.textContent = file?.name ?? 'Aucun fichier sélectionné';
        this.filenameTarget.classList.toggle('has-file', Boolean(file));
        this.submitTarget.disabled = !file;
    }

    dragOver(event) {
        event.preventDefault();
        this.zoneTarget.classList.add('is-dragging');
    }

    dragLeave(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('is-dragging');
    }

    drop(event) {
        event.preventDefault();
        this.zoneTarget.classList.remove('is-dragging');

        const file = event.dataTransfer?.files?.[0];
        if (!file) {
            return;
        }

        const transfer = new DataTransfer();
        transfer.items.add(file);
        this.inputTarget.files = transfer.files;
        this.inputTarget.dispatchEvent(new Event('change', { bubbles: true }));
    }
}
