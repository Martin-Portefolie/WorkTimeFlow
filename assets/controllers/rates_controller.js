import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["collection", "addButton"];

    connect() {
        this.updateRemoveEvent();
    }

    add(event) {
        event.preventDefault();

        // Get the prototype template
        let prototype = this.collectionTarget.dataset.prototype;
        let index = this.collectionTarget.children.length;
        let newForm = prototype.replace(/__name__/g, index);

        // Create a new element for the rate field
        let newElement = document.createElement("div");
        newElement.classList.add("rate-item", "flex", "items-center", "space-x-2", "mt-2");
        newElement.innerHTML = newForm + '<button type="button" data-action="click->rates#remove" class="remove-rate bg-red-500 text-white px-2 py-1 rounded-md">X</button>';

        // Append the new element
        this.collectionTarget.appendChild(newElement);
    }

    remove(event) {
        event.preventDefault();
        event.target.closest(".rate-item").remove();
    }

    updateRemoveEvent() {
        this.element.querySelectorAll(".remove-rate").forEach((button) => {
            button.addEventListener("click", (event) => this.remove(event));
        });
    }
}
