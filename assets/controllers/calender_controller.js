import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["calendar"];

    connect() {
        this.viewMode = 7; // Default to 7-day view
        this.startDate = new Date(); // Start from today
        this.todos = JSON.parse(this.element.dataset.calendarTodos || "[]"); // Get todos safely
        this.renderCalendar();
    }

    toggleView(event) {
        this.viewMode = event.target.dataset.view === "7" ? 7 : 30;
        this.renderCalendar();
    }

    navigate(event) {
        const direction = event.target.dataset.direction;
        if (direction === "prev") {
            this.startDate.setDate(this.startDate.getDate() - this.viewMode);
        } else if (direction === "next") {
            this.startDate.setDate(this.startDate.getDate() + this.viewMode);
        } else {
            this.startDate = new Date();
        }
        this.renderCalendar();
    }

    renderCalendar() {
        this.calendarTarget.innerHTML = ""; // Clear old calendar
        for (let i = 0; i < this.viewMode; i++) {
            let day = new Date(this.startDate);
            day.setDate(this.startDate.getDate() + i);

            let dayCell = document.createElement("div");
            dayCell.className = "p-4 border rounded-lg bg-gray-100";
            dayCell.innerHTML = `
                <div class="font-bold">${day.toLocaleDateString()}</div>
                <div id="tasks-${day.toISOString().slice(0, 10)}"></div>
            `;

            this.calendarTarget.appendChild(dayCell);
        }

        this.populateTodos();
    }

    populateTodos() {
        this.todos.forEach(todo => {
            let taskElement = document.createElement("div");
            taskElement.className = "mt-2 p-2 bg-blue-200 text-blue-900 rounded";
            taskElement.innerText = todo.name;

            let taskContainer = document.getElementById(`tasks-${todo.date}`);
            if (taskContainer) {
                taskContainer.appendChild(taskElement);
            }
        });
    }
}
