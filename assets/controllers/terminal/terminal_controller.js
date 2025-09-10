// assets/controllers/terminal/terminal_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'output'];
    static values = { runUrl: String };

    // Tile clicks
    focusTile() {
        this.inputTarget.focus();
        // put caret at end
        const v = this.inputTarget.value;
        this.inputTarget.value = '';
        this.inputTarget.value = v;
    }

    blurTile() {
        if (document.activeElement === this.inputTarget) {
            this.inputTarget.blur();
        }
    }

    async submit() {
        const raw = this.inputTarget.value.trim();
        if (!raw) return;

        // client-side clear
        if (['clear', 'cls'].includes(raw.toLowerCase())) {
            this.clearOutput();
            this.inputTarget.value = '';
            this.inputTarget.focus();
            return;
        }

        const pending = document.createElement('div');
        pending.textContent = `> ${raw}`;
        this.outputTarget.prepend(pending);

        try {
            const form = new FormData();
            form.append('input', raw);

            const res = await fetch(this.runUrlValue, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: form
            });

            const html = await res.text();
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html;
            pending.replaceWith(wrapper.firstElementChild || wrapper);
        } catch (e) {
            pending.textContent = `Error: ${e.message}`;
        }

        this.inputTarget.value = '';
        this.inputTarget.focus();
    }

    hotkeys(event) {
        if (this._isEditable(event.target)) return;

        // Ctrl/⌘ + L clears
        if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'l') {
            event.preventDefault();
            this.clearOutput();
            this.inputTarget.focus();
            return;
        }

        // accept H to focus (in addition to your Shift+T)
        if (!event.ctrlKey && !event.metaKey && !event.altKey) {
            const key = event.key;
            if (key === 'T' && event.shiftKey) {
                event.preventDefault();
                this.focusTile();
                return;
            }
            if (key.toLowerCase() === 'h') {
                event.preventDefault();
                this.focusTile();
                return;
            }
            if (key === 'Escape' && document.activeElement === this.inputTarget) {
                event.preventDefault();
                this.blurTile();
                return;
            }
        }
    }

    onEsc(e) {
        e.preventDefault();
        this.blurTile();
    }

    focused() {
        this.element.classList.add('ring-1', 'ring-emerald-500/40');
    }
    blurred() {
        this.element.classList.remove('ring-1', 'ring-emerald-500/40');
    }

    clearOutput() {
        this.outputTarget.innerHTML = '';
        const hint = document.createElement('div');
        hint.className = 'text-xs text-zinc-500';
        hint.textContent = 'Cleared. Type "help" for commands.';
        this.outputTarget.prepend(hint);
    }

    _isEditable(el) {
        if (!el) return false;
        const tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        if (el.isContentEditable) return true;
        return false;
    }
}
