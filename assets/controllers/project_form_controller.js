import { Controller } from '@hotwired/stimulus';
import { showStatus, clearStatusTimer } from '../lib/form_status.js';

export default class extends Controller {
    static targets = ['status', 'dialog', 'calculation'];

    connect() {
        this.statusTargets.forEach(status => showStatus(status, status.textContent.trim()));
        const category = this.element.dataset.openCategory;
        if (category) this.dialogTargets.find(dialog => dialog.dataset.category === category)?.showModal();
    }

    disconnect() {
        this.statusTargets.forEach(clearStatusTimer);
    }

    open(event) {
        this.dialogTargets.find(dialog => dialog.dataset.category === event.currentTarget.dataset.category)?.showModal();
    }

    close(event) {
        event.currentTarget.closest('dialog').close();
    }

    dismiss(event) {
        const dialog = event.currentTarget;
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    }

    status(message, state) {
        this.statusTargets.forEach(status => showStatus(status, message, state));
    }

    updateEstimate(event) {
        const hours = Number(event.currentTarget.value);
        this.element.querySelector('[name="estimatedMinutes"]').value = String(hours * 60);
        this.element.querySelector('[data-testid="project-estimated-time"]').textContent = `${hours}h 0m`;
        this.updateBudgetFromEstimate();
    }

    updateBudgetFromEstimate() {
        const rate = this.element.querySelector('[name="rate"]');
        const minutes = this.element.querySelector('[name="estimatedMinutes"]').value;
        if (rate.value && minutes !== '') this.calculateBudget();
    }

    calculateBudget() {
        const rate = this.element.querySelector('[name="rate"]');
        const minutes = this.element.querySelector('[name="estimatedMinutes"]').value.trim();
        const amount = rate.selectedOptions[0]?.dataset.value;
        if (!amount || !/^\d+$/.test(minutes)) {
            this.calculationTarget.textContent = this.element.dataset.calculateError;
            return;
        }
        const budget = (Number(amount) * Number(minutes) / 60).toFixed(2);
        this.element.querySelector('[name="estimatedBudget"]').value = budget;
        this.calculationTarget.textContent = this.calculationTarget.dataset.calculated.replace('%amount%', budget);
    }

    async save(event) {
        event.preventDefault();
        if (this.saving) return;
        const form = event.currentTarget;
        const invalid = [...form.elements].find(field => field.willValidate && !field.validity.valid);
        if (invalid) {
            const dialog = invalid.closest('dialog');
            this.dialogTargets.filter(open => open.open && open !== dialog).forEach(open => open.close());
            if (dialog && !dialog.open) dialog.showModal();
            invalid.reportValidity();
            return;
        }
        this.saving = true;
        const buttons = [...this.element.querySelectorAll('button')];
        buttons.forEach(button => { button.disabled = true; });
        this.status(this.element.dataset.saving, 'saving');
        try {
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: { Accept: 'text/html, application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            if (response.redirected) throw new Error(this.element.dataset.expired);
            if (!response.ok) {
                const result = await response.json();
                throw new Error(result.error || this.element.dataset.error);
            }
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const panel = document.querySelector('[data-controller="project-form"]');
            if (!panel) throw new Error(this.element.dataset.error);
            const openDialog = this.dialogTargets.find(dialog => dialog.open);
            if (openDialog) {
                panel.dataset.openCategory = openDialog.dataset.category;
                openDialog.close();
            }
            this.element.replaceWith(panel);
        } catch (error) {
            this.status(error.message || this.element.dataset.error, 'error');
        } finally {
            this.saving = false;
            buttons.forEach(button => { button.disabled = false; });
        }
    }
}
