import { Controller } from '@hotwired/stimulus';
import { showStatus, clearStatusTimer } from '../lib/form_status.js';

export default class extends Controller {
    static targets = ['input', 'status', 'file', 'preview', 'upload', 'brandName', 'brandLogo', 'region', 'currency', 'currencyOptions'];

    connect() {
        if (this.hasStatusTarget) showStatus(this.statusTarget, this.statusTarget.textContent.trim());
        this.onCompanyUpdated = (event) => {
            if (typeof event.detail?.name === 'string') {
                const name = event.detail.name || 'WorkTimeFlow';
                if (this.hasBrandNameTarget) this.brandNameTarget.textContent = name;
                if (this.hasBrandLogoTarget) this.brandLogoTarget.alt = name;
            }
            if (this.hasBrandLogoTarget && typeof event.detail?.url === 'string') {
                this.brandLogoTarget.src = event.detail.url;
            }
            if (this.hasInputTarget && typeof event.detail?.name === 'string') {
                this.inputTarget.value = event.detail.name;
                this.inputTarget.defaultValue = event.detail.name;
                showStatus(this.statusTarget, '');
            }
        };
        document.addEventListener('wtf:company:updated', this.onCompanyUpdated);
    }

    disconnect() {
        if (this.hasStatusTarget) clearStatusTimer(this.statusTarget);
        document.removeEventListener('wtf:company:updated', this.onCompanyUpdated);
    }

    changeCurrencyRegion() {
        const template = this.currencyOptionsTargets.find(option => option.dataset.region === this.regionTarget.value);
        if (!template) return;

        const previousCurrency = this.currencyTarget.value;
        this.currencyTarget.replaceChildren(template.content.cloneNode(true));
        if ([...this.currencyTarget.options].some(option => option.value === previousCurrency)) {
            this.currencyTarget.value = previousCurrency;
        }
        showStatus(this.statusTarget, '');
    }

    async saveLogo() {
        const file = this.fileTarget.files[0];
        if (!file) {
            this.fileTarget.required = true;
            this.fileTarget.reportValidity();
            return;
        }
        this.uploadTarget.disabled = true;
        showStatus(this.statusTarget, this.element.dataset.saving, 'saving');
        try {
            const body = new FormData();
            body.append('logo', file);
            body.append('_token', this.element.dataset.token);
            const response = await fetch(this.element.dataset.saveUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.error || this.element.dataset.error);
            }
            this.previewTarget.src = result.url;
            document.dispatchEvent(new CustomEvent('wtf:company:updated', { detail: { url: result.url } }));
            this.fileTarget.value = '';
            showStatus(this.statusTarget, this.element.dataset.saved);
        } catch (error) {
            showStatus(this.statusTarget, error.message || this.element.dataset.error, 'error');
        } finally {
            this.uploadTarget.disabled = false;
        }
    }

    async saveRate(event) {
        event.preventDefault();
        if (this.savingRate) return;
        const form = event.currentTarget;
        const action = event.submitter?.value || 'save';
        if (action === 'delete' && !window.confirm(this.element.dataset.confirm)) return;

        const body = new FormData(form);
        body.set('action', action);
        this.savingRate = true;
        const buttons = [...this.element.querySelectorAll('button')];
        buttons.forEach(button => { button.disabled = true; });
        showStatus(this.statusTarget, this.element.dataset.saving, 'saving');
        try {
            // A control named "action" shadows the form.action property.
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: { 'Accept': 'text/html, application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            if (!response.ok) {
                const result = await response.json();
                throw new Error(result.error || this.element.dataset.error);
            }
            if (response.redirected) throw new Error(this.element.dataset.error);
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const panel = document.body.firstElementChild;
            if (panel?.dataset.controller !== 'company-form') throw new Error(this.element.dataset.error);
            this.element.replaceWith(panel);
        } catch (error) {
            showStatus(this.statusTarget, error.message || this.element.dataset.error, 'error');
        } finally {
            this.savingRate = false;
            buttons.forEach(button => { button.disabled = false; });
        }
    }

    async saveName() {
        if (this.savingName) return;
        this.savingName = true;
        showStatus(this.statusTarget, this.element.dataset.saving, 'saving');
        try {
            const response = await fetch(this.element.dataset.saveUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: new URLSearchParams({ company_name: this.inputTarget.value }),
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || this.element.dataset.error);
            document.dispatchEvent(new CustomEvent('wtf:company:updated', { detail: { name: result.name } }));
            showStatus(this.statusTarget, this.element.dataset.saved);
        } catch (error) {
            showStatus(this.statusTarget, error.message || this.element.dataset.error, 'error');
        } finally {
            this.savingName = false;
        }
    }
}
