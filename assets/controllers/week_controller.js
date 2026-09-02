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
    static targets = ['time', 'balance', 'cumulated', 'leaving', 'adjusted', 'cumulatedBefore', 'clockedOut', 'status', 'hintEmpty', 'hintFilled'];

    connect() {
        this.abortController = null;
        this.statusDefault = this.hasStatusTarget ? this.statusTarget.textContent : '';
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
        this.#setStatus(this.element.dataset.weekStatusComputing || '…');

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
            this.#setStatus(this.element.dataset.weekStatusUnsaved || this.statusDefault);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.#setStatus(this.element.dataset.weekStatusError || this.statusDefault);
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
            this.#paintFlap(this.leavingTarget, today?.leavingTime ?? null, '');
        }
        if (this.hasAdjustedTarget) {
            this.#paintFlap(this.adjustedTarget, today?.adjustedLeavingTime ?? null, 'flap-amber');
        }
        if (this.hasCumulatedBeforeTarget && today) {
            this.#paintBadge(this.cumulatedBeforeTarget, today.cumulatedBefore);
        }
        if (this.hasClockedOutTarget) {
            this.clockedOutTarget.hidden = !today?.clockedOut;
        }
        const hasEstimate = Boolean(today?.leavingTime);
        if (this.hasHintEmptyTarget) {
            this.hintEmptyTarget.hidden = hasEstimate;
        }
        if (this.hasHintFilledTarget) {
            this.hintFilledTarget.hidden = !hasEstimate;
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

    /** Re-render the split-flap display (same markup as templates/_partials/flap.html.twig). */
    #paintFlap(container, value, variant) {
        const flap = container.querySelector('.flap');
        const current = flap?.getAttribute('aria-label');
        const text = value ?? '--:--';
        if (current === text) {
            return;
        }
        const size = flap?.style.getPropertyValue('--flap-size') || '';
        const classes = ['flap', variant, text === '--:--' ? 'flap-muted' : '', 'is-flipping'].filter(Boolean).join(' ');
        const digits = Array.from(text, (ch, i) =>
            ch === ':'
                ? '<span class="flap-colon" aria-hidden="true">:</span>'
                : `<span class="flap-digit" style="--i: ${i}" aria-hidden="true">${ch}</span>`,
        ).join('');
        container.innerHTML = `<span class="${classes}" role="img" aria-label="${text}"${size ? ` style="--flap-size: ${size}"` : ''}>${digits}</span>`;
    }

    #setStatus(text) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = text;
        }
    }
}
