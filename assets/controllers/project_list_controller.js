import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['rows', 'newRow', 'empty'];

    add() {
        let row = this.rowsTarget.querySelector('[data-project-row="new"]');
        if (!row) {
            row = this.newRowTarget.content.firstElementChild.cloneNode(true);
            this.rowsTarget.prepend(row);
        }
        this.emptyTarget.hidden = true;
        row.querySelector('input[name="name"]').focus();
        row.scrollIntoView({ block: 'nearest' });
    }

    cancel(event) {
        event.currentTarget.closest('[data-project-row="new"]').remove();
        this.emptyTarget.hidden = this.rowsTarget.querySelector('[data-project-row]') !== null;
    }
}
