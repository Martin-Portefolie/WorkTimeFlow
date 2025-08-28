
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["menu", "button"];

    connect() {
        this._outsideClick = this._outsideClick || ((e) => {
            if (!this.element.contains(e.target)) this.close();
        });
        document.addEventListener("click", this._outsideClick);
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") this.close();
        });
    }

    disconnect() {
        document.removeEventListener("click", this._outsideClick);
    }

    toggle(e) {
        e.preventDefault();
        this.menuTarget.classList.toggle("hidden");
        this.buttonTarget.setAttribute(
            "aria-expanded",
            this.menuTarget.classList.contains("hidden") ? "false" : "true"
        );
    }

    close() {
        if (!this.menuTarget.classList.contains("hidden")) {
            this.menuTarget.classList.add("hidden");
            this.buttonTarget.setAttribute("aria-expanded", "false");
        }
    }
}
