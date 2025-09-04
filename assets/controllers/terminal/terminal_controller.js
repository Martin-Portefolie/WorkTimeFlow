import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'output'];

    run(event) {
        const cmd = event.currentTarget.dataset.terminalTerminalCommand || '';
        if (!cmd) return;
        this.inputTarget.value = cmd;
        this.inputTarget.focus();
    }

    submit() {
        const raw = this.inputTarget.value.trim();
        if (!raw) return;

        const line = document.createElement('div');
        line.textContent = `> ${raw}`;
        this.outputTarget.prepend(line);

        this.inputTarget.value = '';
        this.inputTarget.focus();
    }

    // Window-level hotkeys
    hotkeys(event) {
        if (this._isEditable(event.target)) return;
        if (event.ctrlKey || event.metaKey || event.altKey) return;

        // Shift+T focuses the terminal
        if (event.key === 'T' && event.shiftKey) {
            event.preventDefault();
            this.inputTarget.focus();
            // move caret to end without changing value
            const v = this.inputTarget.value;
            this.inputTarget.value = '';
            this.inputTarget.value = v;
            return;
        }

        // Fallback: blur on Esc if focused (some browsers pass it through)
        if (event.key === 'Escape' && document.activeElement === this.inputTarget) {
            event.preventDefault();
            this.inputTarget.blur();
        }
    }

    // Input-scoped Esc (reliable across browsers)
    onEsc(event) {
        event.preventDefault();
        this.inputTarget.blur();
    }

    // Optional visual focus outline on the terminal container
    focused() {
        this.element.classList.add('ring-1', 'ring-emerald-500/40');
    }
    blurred() {
        this.element.classList.remove('ring-1', 'ring-emerald-500/40');
    }

    _isEditable(el) {
        if (!el) return false;
        const tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        if (el.isContentEditable) return true;
        return false;
    }
}
