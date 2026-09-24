import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sun', 'moon'];

    connect() {
        this.theme = localStorage.getItem('theme');

        if (this.theme !== 'light' && this.theme !== 'dark') {
            this.theme = 'dark';
            localStorage.setItem('theme', this.theme);
        }

        this.apply();
    }

    toggle() {
        this.theme = this.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', this.theme);
        this.apply();
    }

    apply() {
        const isDark = this.theme === 'dark';

        document.documentElement.classList.toggle('dark', isDark);

        this.sunTarget.classList.toggle('hidden', !isDark);
        this.moonTarget.classList.toggle('hidden', isDark);
    }
}
