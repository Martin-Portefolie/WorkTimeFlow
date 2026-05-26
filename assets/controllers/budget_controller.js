import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["hours", "rate", "budget"];

    connect() {
        console.log("Budget Controller Connected!");
        this.updateBudget(); // Ensure correct budget on page load
    }

    updateBudget() {
        let hours = parseFloat(this.hoursTarget.value) || 0;

        // Get selected rate's value from the dataset
        let rateOption = this.rateTarget.selectedOptions[0]; // Get the selected <option>
        let rate = parseFloat(rateOption.dataset.value) || 0; // Extract data-value

        // Perform the budget calculation
        let estimatedBudget = hours * rate;

        // Update the estimated budget input field
        this.budgetTarget.value = estimatedBudget.toFixed(2); // Ensure 2 decimal places

        console.log(`Updated Budget: ${estimatedBudget} €, Hours: ${hours}, Rate: ${rate}`);
    }
}
