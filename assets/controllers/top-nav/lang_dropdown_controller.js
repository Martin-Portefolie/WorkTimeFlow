import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["menu", "button"];

    connect() {
        this.outsideClick = this.outsideClick.bind(this);
        this.escapeClose = this.escapeClose.bind(this);

        document.addEventListener("click", this.outsideClick);
        document.addEventListener("keydown", this.escapeClose);
    }

    disconnect() {
        document.removeEventListener("click", this.outsideClick);
        document.removeEventListener("keydown", this.escapeClose);
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        this.menuTarget.classList.toggle("hidden");
        this.buttonTarget.setAttribute("aria-expanded", this.isOpen ? "true" : "false");
    }

    close() {
        if (!this.isOpen) {
            return;
        }

        this.menuTarget.classList.add("hidden");
        this.buttonTarget.setAttribute("aria-expanded", "false");
    }

    outsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    escapeClose(event) {
        if (event.key === "Escape") {
            this.close();
        }
    }

    get isOpen() {
        return !this.menuTarget.classList.contains("hidden");
    }
}
