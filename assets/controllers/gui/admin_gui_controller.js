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
        const selected = ['border-emerald-500', 'bg-emerald-500/10', 'text-emerald-500', 'dark:border-emerald-500/40', 'dark:text-emerald-400'];
        const unselected = ['border-transparent', 'text-zinc-600', 'hover:border-zinc-300', 'hover:bg-white', 'hover:text-zinc-900', 'dark:text-zinc-400', 'dark:hover:border-zinc-700', 'dark:hover:bg-zinc-900', 'dark:hover:text-zinc-100'];
        this.element.querySelectorAll('[data-key]').forEach((element) => {
            const isActive = element.dataset.key === active;
            selected.forEach(name => element.classList.toggle(name, isActive));
            unselected.forEach(name => element.classList.toggle(name, !isActive));
            if (isActive) element.setAttribute('aria-current', 'page');
            else element.removeAttribute('aria-current');
        });
    }

    runCommand(event) {
        const command = event.currentTarget.dataset.command;

        if (!command) {
            return;
        }

        document.dispatchEvent(
            new CustomEvent('wtf:terminal:run', {
                detail: { command },
            }),
        );
    }

    loadClient(event) {
        return this.loadPanel(event);
    }

    async loadPanel(event) {
        event.preventDefault();
        const trigger = event.currentTarget;
        const url = trigger.dataset.url || trigger.getAttribute('href');
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error(trigger.dataset.error);
            const html = await response.text();
            const active = trigger.dataset.key || 'clients';
            this.update({ detail: { active, view: active, html } });
        } catch (error) {
            window.alert(trigger.dataset.error);
        }
    }

}
