// assets/controllers/terminal/terminal_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'output'];
    static values = { runUrl: String };

    /* ---------------------------
     * UI helpers (focus/blur)
     * ------------------------- */

    // Focus input (also moves caret to end)
    focusTile() {
        this.inputTarget.focus();
        const v = this.inputTarget.value;
        this.inputTarget.value = '';
        this.inputTarget.value = v;
    }

    // Blur input if it is focused
    blurTile() {
        if (document.activeElement === this.inputTarget) {
            this.inputTarget.blur();
        }
    }

    /* ---------------------------
     * Submit handler
     * - Accepts multiple commands separated by ';'
     * - Runs sequentially (awaits each)
     * - Handles client-side clear (clear/cls)
     * ------------------------- */
    async submit() {
        const raw = (this.inputTarget.value || '').trim();
        if (!raw) return;

        // Split by ';', trim each, drop empties
        // Note: If you later need quoted strings with semicolons, we can upgrade to a tokenizer.
        const commands = raw
            .split(';')
            .map(s => s.trim())
            .filter(Boolean);

        for (const cmd of commands) {
            // Handle client-side clear immediately (no server call)
            if (['clear', 'cls'].includes(cmd.toLowerCase())) {
                this.clearOutput();
                continue;
            }

            // Echo the typed command immediately for responsiveness
            const pending = document.createElement('div');
            pending.textContent = `> ${cmd}`;
            this.outputTarget.prepend(pending);

            try {
                const form = new FormData();
                form.append('input', cmd);

                const res = await fetch(this.runUrlValue, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: form
                });

                const html = await res.text();
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html;

                // Replace the echo with the server-rendered line (includes success/invalid coloring)
                pending.replaceWith(wrapper.firstElementChild || wrapper);
            } catch (e) {
                pending.textContent = `Error: ${e.message}`;
            }
        }

        // Reset input and keep focus ready for next commands
        this.inputTarget.value = '';
        this.inputTarget.focus();
    }

    /* ---------------------------
     * Global hotkeys (window)
     * ------------------------- */
    hotkeys(event) {
        if (this._isEditable(event.target)) return;

        // Ctrl/⌘ + L : clear output
        if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'l') {
            event.preventDefault();
            this.clearOutput();
            this.inputTarget.focus();
            return;
        }

        // Accept H to focus (and Shift+T per your spec). Esc to unfocus.
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

    // Input-scoped Esc (reliable across browsers)
    onEsc(e) {
        e.preventDefault();
        this.blurTile();
    }

    // Optional visual focus ring on the terminal container
    focused() {
        this.element.classList.add('ring-1', 'ring-emerald-500/40');
    }

    blurred() {
        this.element.classList.remove('ring-1', 'ring-emerald-500/40');
    }

    /* ---------------------------
     * Utilities
     * ------------------------- */
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
