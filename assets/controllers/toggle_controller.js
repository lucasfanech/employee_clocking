import { Controller } from '@hotwired/stimulus';

/*
 * Show / hide an element (mobile menu, modal…).
 *   <div data-controller="toggle">
 *     <button data-action="toggle#toggle">…</button>
 *     <div data-toggle-target="panel" hidden>…</div>
 *   </div>
 */
export default class extends Controller {
    static targets = ['panel'];

    toggle() {
        this.panelTargets.forEach((panel) => (panel.hidden = !panel.hidden));
    }

    open() {
        this.panelTargets.forEach((panel) => (panel.hidden = false));
    }

    close() {
        this.panelTargets.forEach((panel) => (panel.hidden = true));
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
