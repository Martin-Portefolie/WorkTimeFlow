import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["hours", "hoursDisplay", "hiddenInput"];

    connect() {
        console.log("✅ Time Slider Controller Connected!");
        this.updateDisplay(); // Ensure display updates on load
        this.hoursTarget.addEventListener("input", this.updateDisplay.bind(this));
    }

    updateDisplay() {
        const hours = parseInt(this.hoursTarget.value, 10) || 0; // Get selected hours
        const totalMinutes = hours * 60; // Convert hours to minutes

        // Update display
        this.hoursDisplayTarget.textContent = `${hours}h`;

        // Update hidden input field (stores total minutes)
        this.hiddenInputTarget.value = totalMinutes;

        // Trigger budget update (if budget controller is present)
        this.dispatch("budget:update", { detail: { hours } });
    }
}
