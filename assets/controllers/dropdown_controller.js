import { Controller } from "@hotwired/stimulus";

/**
 * DropdownController handles the behavior of custom dropdowns
 */
export default class extends Controller {
    static targets = ["select", "customDropdown", "menu", "search", "item", "selected", "tableBody"];

    connect() {
        this.createCustomDropdown();
    }

    createCustomDropdown() {
        const select = this.selectTarget;
        const options = Array.from(select.options);

        // Create Custom Dropdown
        const customDropdown = document.createElement("div");
        customDropdown.classList.add("relative", "w-full");
        this.element.appendChild(customDropdown);

        // Selected Element
        const selected = document.createElement("div");
        selected.classList.add("border", "p-2", "rounded-md", "bg-white", "cursor-pointer", "shadow-sm");
        selected.textContent = options.length > 0 ? options[0].textContent : "Select a Todo";
        customDropdown.appendChild(selected);

        // Menu Element
        const menu = document.createElement("div");
        menu.classList.add("absolute", "w-full", "bg-white", "shadow-lg", "border", "mt-1", "rounded-md", "hidden", "max-h-48", "overflow-y-auto");
        customDropdown.appendChild(menu);

        // Search Input
        const search = document.createElement("input");
        search.type = "text";
        search.placeholder = "Search...";
        search.classList.add("w-full", "p-2", "border-b", "focus:outline-none");
        menu.appendChild(search);

        // Items Wrapper
        const menuInnerWrapper = document.createElement("div");
        menu.appendChild(menuInnerWrapper);

        // Add First 5 Items Initially
        this.populateDropdown(menuInnerWrapper, options.slice(0, 5));

        // Add Event Listeners
        selected.addEventListener("click", () => menu.classList.toggle("hidden"));
        search.addEventListener("input", (e) => this.filterItems(menuInnerWrapper, e.target.value, options));
        menuInnerWrapper.addEventListener("click", (e) => {
            if (e.target.classList.contains("dropdown-menu-item")) {
                this.setSelected(e.target, selected, select, menu);
            }
        });

        // Hide the original select
        select.style.display = "none";
    }

    populateDropdown(wrapper, options) {
        wrapper.innerHTML = ""; // Clear existing options
        options.forEach((option) => {
            const item = document.createElement("div");
            item.classList.add("p-2", "cursor-pointer", "hover:bg-gray-200", "dropdown-menu-item");
            item.dataset.value = option.value;
            item.textContent = option.textContent;
            wrapper.appendChild(item);
        });
    }

    filterItems(wrapper, searchValue, options) {
        const filtered = options.filter((option) =>
            option.textContent.toLowerCase().includes(searchValue.toLowerCase())
        );
        this.populateDropdown(wrapper, filtered.slice(0, 5));
    }

    setSelected(item, selected, select, menu) {
        selected.textContent = item.textContent;
        select.value = item.dataset.value;
        menu.classList.add("hidden"); // Close menu
    }
}
