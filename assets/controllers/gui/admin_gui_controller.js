import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'title'];

    connect() {
        this.onUpdate = this.update.bind(this);
        document.addEventListener('wtf:gui:update', this.onUpdate);
    }

    disconnect() {
        document.removeEventListener('wtf:gui:update', this.onUpdate);
    }

    update(event) {

        const { active, view, html } = event.detail || {};

        if (!active || !view || !html || !this.hasContentTarget) {
            return;
        }

        this.contentTarget.innerHTML = html;
        this.titleTarget.textContent = active;
        this.updateSidebar(active);
    }

    updateSidebar(active) {
        document
            .querySelectorAll('[data-key]')
            .forEach((el) => {
                const isActive = el.dataset.key === active;

                el.classList.toggle(
                    'border-emerald-500/40',
                    isActive
                );

                el.classList.toggle(
                    'bg-emerald-500/10',
                    isActive
                );

                el.classList.toggle(
                    'text-emerald-400',
                    isActive
                );

                if (!isActive) {
                    el.classList.remove(
                        'border-emerald-500/40',
                        'bg-emerald-500/10',
                        'text-emerald-400'
                    );
                }
            });
    }
}
