import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.updateVar = this.updateVar.bind(this);
        this.ro = new ResizeObserver(this.updateVar);
        this.ro.observe(this.element);
        this.updateVar(); // initial
        window.addEventListener('load', this.updateVar, { once: true });
    }

    disconnect() {
        this.ro?.disconnect();
    }

    updateVar() {
        const h = this.element.offsetHeight || 0;
        document.documentElement.style.setProperty('--nav-h', `${h}px`);
    }
}
