import { Controller } from '@hotwired/stimulus';

/*
 * Live recomputation of the daily balances, the cumulated balance and the
 * estimated leaving time while the user edits the clocking times.
 *
 * The rules live on the server (WorkTimeCalculator): this controller only
 * posts the current inputs to the preview endpoint and paints the answer.
 */
export default class extends Controller {
    static values = { previewUrl: String };
    static targets = ['time', 'balance', 'cumulated', 'leaving', 'adjusted', 'cumulatedBefore', 'clockedOut', 'status'];

    connect() {
        this.abortController = null;
    }

    disconnect() {
        this.abortController?.abort();
    }

    /** Called on every change of a time input. */
    recompute() {
        clearTimeout(this.debounce);
        this.debounce = setTimeout(() => this.#fetchPreview(), 150);
    }

    async #fetchPreview() {
        this.abortController?.abort();
        this.abortController = new AbortController();
        this.#setStatus('Computing…');

        try {
            const response = await fetch(this.previewUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ days: this.#collectDays() }),
                signal: this.abortController.signal,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.#paint(await response.json());
            this.#setStatus('Unsaved changes – press Save to keep them.');
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.#setStatus('Could not compute the balances.');
            }
        }
    }

    #collectDays() {
        const days = Array.from({ length: 5 }, () => ({}));
        for (const input of this.timeTargets) {
            const index = Number(input.dataset.index);
            days[index][input.dataset.field] = input.value;
        }
        return days;
    }

    #paint(summary) {
        for (const day of summary.days) {
            this.#paintBadge(this.balanceTargets.find((el) => Number(el.dataset.index) === day.index), day.balance);
            this.#paintBadge(this.cumulatedTargets.find((el) => Number(el.dataset.index) === day.index), day.cumulated);
        }

        const today = summary.today;
        if (this.hasLeavingTarget) {
            this.leavingTarget.textContent = today?.leavingTime ?? '--:--';
        }
        if (this.hasAdjustedTarget) {
            this.adjustedTarget.textContent = today?.adjustedLeavingTime ?? '--:--';
        }
        if (this.hasCumulatedBeforeTarget && today) {
            this.#paintBadge(this.cumulatedBeforeTarget, today.cumulatedBefore);
        }
        if (this.hasClockedOutTarget) {
            this.clockedOutTarget.hidden = !today?.clockedOut;
        }
    }

    #paintBadge(element, value) {
        if (!element) {
            return;
        }
        element.textContent = value ?? '—';
        element.classList.remove('badge-positive', 'badge-negative', 'badge-neutral');
        if (value === null || value === undefined || value === '0:00') {
            element.classList.add('badge-neutral');
        } else {
            element.classList.add(value.startsWith('-') ? 'badge-negative' : 'badge-positive');
        }
    }

    #setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
