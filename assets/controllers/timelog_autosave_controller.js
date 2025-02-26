import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        this.inputs = this.element.querySelectorAll(".autosave-input");
        this.inputs.forEach((input) => {
            input.addEventListener("change", this.autoSave.bind(this));
        });
    }

    async autoSave(event) {
        const input = event.target;
        const todoId = input.dataset.todoId;
        const date = input.dataset.date;
        const [hours, minutes] = input.value.split(":").map((val) => parseInt(val, 10));

        const payload = {
            todoId,
            date,
            hours: isNaN(hours) ? 0 : hours,
            minutes: isNaN(minutes) ? 0 : minutes,
        };

        try {
            const response = await fetch("/profile/save-time", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                throw new Error("Failed to save data.");
            }

            const result = await response.json();
            console.log("Save successful:", result);

            // ✅ Update the UI with the new values
            this.updateUI(input, result);

        } catch (error) {
            console.error("Auto-save error:", error);
        }
    }

    updateUI(input, result) {
        const todoId = result.todoId;
        const date = result.date;
        const hours = result.hours;
        const minutes = result.minutes;

        // ✅ Update the input field with formatted time
        input.value = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;

        // ✅ Find the total field for the row
        const row = input.closest("tr");
        let rowTotal = 0;

        row.querySelectorAll(".autosave-input").forEach((inp) => {
            const [h, m] = inp.value.split(":").map((val) => parseInt(val, 10));
            rowTotal += h * 60 + m;
        });

        const totalCell = row.querySelector(".row-total");
        if (totalCell) {
            totalCell.textContent = `${Math.floor(rowTotal / 60)}h ${rowTotal % 60}m`;
        }

        // ✅ Update the column totals
        // const dayTotalCell = document.querySelector(`td[data-date="${date}"]`);
        // if (dayTotalCell) {
        //     let dayTotal = 0;
        //     document.querySelectorAll(`input[data-date="${date}"]`).forEach((inp) => {
        //         const [h, m] = inp.value.split(":").map((val) => parseInt(val, 10));
        //         dayTotal += h * 60 + m;
        //     });
        //
        //     dayTotalCell.textContent = `${Math.floor(dayTotal / 60)}h ${dayTotal % 60}m`;
        // }
        const dayTotalCell = document.querySelector(`td[data-day-total="${date}"]`);
        if (dayTotalCell) {
            let dayTotal = 0;
            document.querySelectorAll(`input[data-date="${date}"]`).forEach((inp) => {
                const [h, m] = inp.value.split(":").map((val) => parseInt(val, 10));
                dayTotal += h * 60 + m;
            });

            // ✅ Update the cell text content
            dayTotalCell.textContent = `${Math.floor(dayTotal / 60)}h ${dayTotal % 60}m`;
        }

        let weeklyTotal = 0;
        document.querySelectorAll("td[data-day-total]").forEach((cell) => {
            console.log("Checking cell:", cell.textContent); // ✅ Debugging
            const time = cell.textContent.trim().split(" ");

            if (time.length === 2) {
                const h = parseInt(time[0]) || 0;
                const m = parseInt(time[1]) || 0;
                weeklyTotal += h * 60 + m;
            } else {
                console.warn("Invalid time format:", cell.textContent); // ✅ Debugging
            }
        });

        const weeklyTotalCell = document.getElementById("weekly-total");
        if (weeklyTotalCell) {
            weeklyTotalCell.textContent = `${Math.floor(weeklyTotal / 60)}h ${weeklyTotal % 60}m`;
        }

    }
}
