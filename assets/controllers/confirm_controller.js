import { Controller } from '@hotwired/stimulus';

/*
 * Ask for confirmation before submitting a form.
 *   <form data-controller="confirm" data-confirm-message-value="Sure?" data-action="submit->confirm#ask">
 */
export default class extends Controller {
    static values = { message: { type: String, default: 'Are you sure?' } };

    ask(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
