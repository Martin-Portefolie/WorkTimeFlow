import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["logs"];

    connect() {
        console.log("Timelog Todo Collapse Controller Connected!");
    }

    toggle(event) {
        event.preventDefault();
        console.log("Toggle function triggered!");

        const button = event.currentTarget;
        const logList = this.logsTarget;

        if (logList) {
            logList.classList.toggle("hidden");
            button.textContent = logList.classList.contains("hidden") ? "Show Logs" : "Hide Logs";
        } else {
            console.error("Log list not found!");
        }
    }
}
