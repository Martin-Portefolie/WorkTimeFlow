import { Controller } from '@hotwired/stimulus';
import { showStatus, clearStatusTimer } from '../lib/form_status.js';

export default class extends Controller {
    static targets = ['status', 'projectsDialog'];

    openProjects() {
        this.projectsDialogTarget.showModal();
    }

    closeProjects() {
        this.projectsDialogTarget.close();
    }

    dismissProjects(event) {
        if (event.target !== this.projectsDialogTarget) return;
        const bounds = this.projectsDialogTarget.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            this.closeProjects();
        }
    }

    connect() {
        showStatus(this.statusTarget, this.statusTarget.textContent.trim());
    }

    disconnect() {
        clearStatusTimer(this.statusTarget);
    }

    async save(event) {
        event.preventDefault();
        if (this.saving) return;
        const form = event.currentTarget;
        this.saving = true;
        const buttons = [...this.element.querySelectorAll('button')];
        buttons.forEach(button => { button.disabled = true; });
        showStatus(this.statusTarget, this.element.dataset.saving, 'saving');
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
            const panel = document.querySelector('[data-controller="client-form"]');
            if (!panel) throw new Error(this.element.dataset.error);
            this.element.replaceWith(panel);
        } catch (error) {
            showStatus(this.statusTarget, error.message || this.element.dataset.error, 'error');
        } finally {
            this.saving = false;
            buttons.forEach(button => { button.disabled = false; });
        }
    }
}
