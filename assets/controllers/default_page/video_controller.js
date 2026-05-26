import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["video", "overlay"];

    connect() {
        if (this.hasVideoTarget) {
            this._onEnded = () => this.showOverlay();
            this.videoTarget.addEventListener("ended", this._onEnded);
        }
    }

    disconnect() {
        if (this._onEnded && this.hasVideoTarget) {
            this.videoTarget.removeEventListener("ended", this._onEnded);
        }
    }

    play(event) {
        event?.preventDefault?.();
        if (!this.hasVideoTarget || !this.hasOverlayTarget) return;

        // Show video, hide overlay
        this.videoTarget.classList.remove("hidden");
        this.hideOverlay();

        const p = this.videoTarget.play();
        if (p && typeof p.catch === "function") {
            p.catch(() => this.showOverlay());
        }
    }

    showOverlay() {
        if (this.hasOverlayTarget) this.overlayTarget.style.display = "";
        if (this.hasVideoTarget) {
            this.videoTarget.classList.add("hidden");
            this.videoTarget.pause();
            this.videoTarget.currentTime = 0; // reset to start frame
        }
    }

    hideOverlay() {
        if (this.hasOverlayTarget) this.overlayTarget.style.display = "none";
    }
}
