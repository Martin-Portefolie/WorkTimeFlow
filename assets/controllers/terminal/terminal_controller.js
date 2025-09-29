import { Controller } from '@hotwired/stimulus';

const ALIASES = {
    'u.l':  'users:list',
    'u.s':  'users:show',
    'u.a':  'users:add',
    'u.u':  'users:update',
    'u.fp': 'users:forgot-password',
    'u.off':'users:deactivate',
    'u.on': 'users:activate',
    'u.del':'users:delete',
};
export default class extends Controller {
    static targets = ['input', 'output', 'suggestions', 'palette', 'paletteInput', 'paletteList'];
    static values = { runUrl: String, allowlist: Array };

    connect() {
        // Identify user (prefix selector matches your "$email" span)
        const userText = document.querySelector('span.text-emerald-400')?.textContent || '$guest';
        this.userId = userText.replace(/^\$/, ''); // strip '$'
        // Derive role scope from allowlist (admin vs profile)
        const scope = (this.allowlistValue || []).includes('users:list') ? 'admin' : 'profile';

        // Per-user + per-scope history
        this.history = new HistoryStore(`wtf:terminal:history:${this.userId}:${scope}`, 50);
        this.historyIndex = null; // null => not navigating
    }

    /* ========== UI helpers ========== */
    focusTile() {
        this.inputTarget.focus();
        const v = this.inputTarget.value;
        this.inputTarget.value = '';
        this.inputTarget.value = v;
    }
    blurTile() {
        if (document.activeElement === this.inputTarget) this.inputTarget.blur();
    }

    /* ========== Submit (supports "cmd1; cmd2" etc.) ========== */
    async submit() {
        const raw = (this.inputTarget.value || '').trim();
        if (!raw) return;

        // Save full line in history
        this.history.push(raw);
        this.historyIndex = null;

        // Split by ';' and run sequentially
        const commands = raw.split(';').map(s => s.trim()).filter(Boolean);
        for (const cmd of commands) {
            // client-only clear
            if (['clear','cls'].includes(cmd.toLowerCase())) {
                this.clearOutput();
                continue;
            }

            // ---- NEW: alias normalization BEFORE allowlist check ----
            const firstToken = (cmd.split(/\s+/)[0] || '');
            const canonical  = ALIASES[firstToken] || firstToken;

            // Role-aware client filter; server still enforces
            if (this.allowlistValue && !this.allowlistValue.includes(canonical)) {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = this.renderLine(cmd, `${firstToken} is not viable`, false);
                this.outputTarget.prepend(wrapper.firstElementChild || wrapper);
                continue;
            }

            // Echo then POST (you can send `cmd` as-is; server also normalizes)
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
                pending.replaceWith(wrapper.firstElementChild || wrapper);
            } catch (e) {
                pending.textContent = `Error: ${e.message}`;
            }
        }

        this.inputTarget.value = '';
        this.inputTarget.focus();
    }
    /* ========== Keyboard (window) ========== */
    hotkeys(event) {
        const key = event.key.toLowerCase();
        const isCmd = event.metaKey || event.ctrlKey;
        const isShift = event.shiftKey;
        const isAlt = event.altKey;
        const isFocused = document.activeElement === this.inputTarget;

        // --- Focus terminal: ⌘/Ctrl + . ---
        if (isCmd && !isShift && !isAlt && key === '.') {
            event.preventDefault();
            this.focusTile();
            return;
        }

        // --- Command palette: ⌘/Ctrl + \ ---
        if (isCmd && !isShift && !isAlt && key === '\\') {
            event.preventDefault();
            this.openPalette();
            return;
        }

        // --- Clear output: ⌘/Ctrl + Shift + L ---
        if (isCmd && isShift && !isAlt && key === 'l') {
            event.preventDefault();
            this.clearOutput();
            this.inputTarget.focus();
            return;
        }

        // --- When input is focused, support history + escape ---
        if (isFocused && !isCmd && !isAlt) {
            if (event.key === 'ArrowUp')   { event.preventDefault(); this.showHistoryPrev(); return; }
            if (event.key === 'ArrowDown') { event.preventDefault(); this.showHistoryNext(); return; }
            if (event.key === 'Escape')    { event.preventDefault(); this.blurTile();        return; }
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

    /* ========== History navigation (↑/↓) ========== */
    showHistoryPrev() {
        const items = this.history.last(10);
        if (items.length === 0) return;

        if (this.historyIndex === null) {
            this.historyIndex = items.length - 1; // start at latest
        } else if (this.historyIndex > 0) {
            this.historyIndex -= 1;
        }
        this.inputTarget.value = items[this.historyIndex];
        this.moveCaretToEnd();
    }

    showHistoryNext() {
        const items = this.history.last(10);
        if (items.length === 0) return;

        if (this.historyIndex === null) {
            // nothing selected; keep as is
            return;
        } else if (this.historyIndex < items.length - 1) {
            this.historyIndex += 1;
            this.inputTarget.value = items[this.historyIndex];
        } else {
            // past the end -> clear selection
            this.historyIndex = null;
            this.inputTarget.value = '';
        }
        this.moveCaretToEnd();
    }

    moveCaretToEnd() {
        const v = this.inputTarget.value;
        this.inputTarget.value = '';
        this.inputTarget.value = v;
    }

    /* ========== Command palette (Cmd/Ctrl + K) ========== */
    openPalette() {
        if (!this.hasPaletteTarget) return;
        this.paletteTarget.classList.remove('hidden');
        // Build dataset: mix last 10 history and allowlist (unique)
        const recent = this.history.last(10);
        const allowed = (this.allowlistValue || []).slice();
        const merged = [...new Set([...recent.map(s => s.split(/\s+/)[0]), ...allowed])]; // command names

        this.paletteItems = merged.map(name => ({ name })); // simple structure
        this.renderPaletteList(this.paletteItems);
        // Focus input
        if (this.hasPaletteInputTarget) {
            this.paletteInputTarget.value = '';
            this.paletteInputTarget.focus();
        }
    }

    closePalette() {
        if (this.hasPaletteTarget) this.paletteTarget.classList.add('hidden');
    }

    filterPalette() {
        const q = (this.paletteInputTarget.value || '').toLowerCase().trim();
        if (!q) {
            this.renderPaletteList(this.paletteItems);
            return;
        }
        const filtered = this.paletteItems.filter(it => it.name.toLowerCase().includes(q));
        this.renderPaletteList(filtered);
    }

    pickFirstPalette() {
        const firstBtn = this.paletteListTarget.querySelector('button[data-cmd]');
        if (!firstBtn) return;
        const cmd = firstBtn.getAttribute('data-cmd');
        this.insertCommand(cmd);
        this.closePalette();
    }

    renderPaletteList(items) {
        if (!this.hasPaletteListTarget) return;
        this.paletteListTarget.innerHTML = '';
        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-3 py-3 text-zinc-400';
            empty.textContent = 'No matches.';
            this.paletteListTarget.appendChild(empty);
            return;
        }
        for (const it of items) {
            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'w-full text-left px-3 py-2 hover:bg-zinc-900 text-zinc-200 border-b border-zinc-800';
            row.dataset.cmd = it.name;
            row.textContent = it.name;
            row.addEventListener('click', () => {
                this.insertCommand(it.name);
                this.closePalette();
            });
            this.paletteListTarget.appendChild(row);
        }
    }

    insertCommand(cmd) {
        const existing = (this.inputTarget.value || '').trim();
        this.inputTarget.value = existing ? `${existing}; ${cmd}` : cmd;
        this.focusTile();
    }

    /* ========== Utilities ========== */
    clearOutput() {
        this.outputTarget.innerHTML = '';
        const hint = document.createElement('div');
        hint.className = 'text-xs text-zinc-500';
        hint.textContent = 'Cleared. Type "help" for commands.';
        this.outputTarget.prepend(hint);
    }

    renderLine(input, output, success) {
        // Fallback renderer used by client-side "not viable" message
        const color = success ? 'text-green-400' : 'text-red-400';
        return `
      <div class="flex items-start gap-2">
        <span class="${color} font-mono">&gt;</span>
        <div class="flex-1 space-y-1">
          <div class="text-zinc-200 font-mono text-sm">${this.escape(input)}</div>
          <div class="text-zinc-400 text-xs">${this.escape(output)}</div>
        </div>
      </div>`;
    }

    escape(s) {
        return (s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
}

/* ===============================
 * HistoryStore (localStorage)
 * - Keeps at most `cap` entries
 * - Dedup consecutive duplicates
 * - last(n): newest -> oldest slice
 * ============================= */
class HistoryStore {
    constructor(key, cap = 50) {
        this.key = key;
        this.cap = cap;
        this.arr = this._load();
    }
    push(cmd) {
        cmd = (cmd || '').trim();
        if (!cmd) return;
        // Skip if identical to the last stored command
        if (this.arr.length && this.arr[this.arr.length - 1] === cmd) return;
        this.arr.push(cmd);
        if (this.arr.length > this.cap) this.arr = this.arr.slice(-this.cap);
        this._save();
    }
    last(n = 10) {
        const slice = this.arr.slice(-n);     // oldest->newest within the slice
        return slice.reverse();               // return newest->oldest
    }
    _load() {
        try {
            const raw = localStorage.getItem(this.key);
            const parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch { return []; }
    }
    _save() {
        try { localStorage.setItem(this.key, JSON.stringify(this.arr)); } catch {}
    }
}
