import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["collection"];

    connect() {
        console.log("✅ Rates controller connected!");
    }

    add(event) {
        event.preventDefault();

        console.log("Adding new rate..."); // ✅ Debugging message

        let collection = this.collectionTarget;
        let prototype = collection.dataset.prototype;
        let index = collection.children.length;

        if (!prototype) {
            console.error("❌ Prototype data missing!");
            return;
        }

        // Replace __name__ with the new index
        let newForm = prototype.replace(/__name__/g, index);

        // Create new element for rate field
        let newElement = document.createElement("div");
        newElement.classList.add("rate-item", "flex", "items-center", "space-x-3", "bg-white", "p-3", "rounded-md", "shadow-md", "border", "border-gray-300");
        newElement.innerHTML = newForm + `
            <button type="button" class="remove-rate bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md transition">✕</button>
        `;

        // Append the new element
        collection.appendChild(newElement);

        console.log("✅ New rate added!");

        // Attach remove event
        newElement.querySelector(".remove-rate").addEventListener("click", () => {
            console.log("❌ Rate removed!");
            newElement.remove();
        });
    }

    remove(event) {
        event.preventDefault();
        event.target.closest(".rate-item").remove();
        console.log("❌ Rate removed!");
    }
}
