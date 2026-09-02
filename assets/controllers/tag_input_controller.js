import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'tags', 'value'];

    connect() {
        const initialTags = this.parse(this.valueTarget.value);

        this.tags = [];
        this.element.classList.add('tag-editor-ready');
        this.add(initialTags);
    }

    focus(event) {
        if (event.target.closest('.tag-editor-remove')) {
            return;
        }

        this.inputTarget.focus();
    }

    keydown(event) {
        if (event.key === 'Enter' || event.key === ',' || event.key === ';') {
            event.preventDefault();
            this.commit();

            return;
        }

        if (event.key === 'Backspace' && this.inputTarget.value === '' && this.tags.length > 0) {
            this.tags.pop();
            this.render();
        }
    }

    paste(event) {
        const text = event.clipboardData?.getData('text') ?? '';

        if (!/[,;\n\r]/.test(text)) {
            return;
        }

        event.preventDefault();
        this.add(this.parse(text));
    }

    commit() {
        this.add(this.parse(this.inputTarget.value));
        this.inputTarget.value = '';
    }

    remove(event) {
        event.preventDefault();
        event.stopPropagation();
        const index = Number.parseInt(event.currentTarget.dataset.index, 10);

        if (!Number.isInteger(index)) {
            return;
        }

        this.tags.splice(index, 1);
        this.render();
        this.inputTarget.focus();
    }

    add(values) {
        const known = new Set(this.tags.map((tag) => tag.toLocaleLowerCase('fr')));

        values.forEach((value) => {
            const normalized = value.toLocaleLowerCase('fr');

            if (!known.has(normalized)) {
                this.tags.push(value);
                known.add(normalized);
            }
        });

        this.render();
    }

    parse(value) {
        return value
            .split(/[,;\n\r]+/)
            .map((tag) => tag.trim())
            .filter((tag) => tag !== '');
    }

    render() {
        this.tagsTarget.replaceChildren(...this.tags.map((tag, index) => this.tagElement(tag, index)));
        this.valueTarget.value = this.tags.join(', ');
    }

    tagElement(tag, index) {
        const element = document.createElement('span');
        const label = document.createElement('span');
        const removeButton = document.createElement('button');

        element.className = 'tag-editor-tag';
        label.textContent = tag;
        removeButton.type = 'button';
        removeButton.className = 'tag-editor-remove';
        removeButton.dataset.index = index.toString();
        removeButton.dataset.action = 'tag-input#remove';
        removeButton.setAttribute('aria-label', `Retirer ${tag}`);
        removeButton.textContent = '×';
        element.append(label, removeButton);

        return element;
    }
}
