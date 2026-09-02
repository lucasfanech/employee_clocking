import { Controller } from '@hotwired/stimulus';

/*
 * Easter egg: a small minesweeper. Best time is kept in localStorage.
 */
export default class extends Controller {
    static values = {
        size: { type: Number, default: 10 },
        mines: { type: Number, default: 20 },
        image: String,
    };
    static targets = ['grid', 'timer', 'best', 'message'];

    connect() {
        this.bestTime = this.#readBestTime();
        this.#renderBest();
        this.restart();
    }

    disconnect() {
        this.#stopTimer();
    }

    restart() {
        this.#stopTimer();
        this.time = 0;
        this.started = false;
        this.ended = false;
        this.timerTarget.textContent = '0';
        this.messageTarget.hidden = true;
        this.#buildGrid();
    }

    reveal(event) {
        if (this.ended) {
            return;
        }
        if (!this.started) {
            this.started = true;
            this.interval = setInterval(() => {
                this.time += 1;
                this.timerTarget.textContent = String(this.time);
            }, 1000);
        }
        const { row, col } = event.currentTarget.dataset;
        this.#revealCell(Number(row), Number(col));
    }

    #buildGrid() {
        const size = this.sizeValue;
        this.gridTarget.innerHTML = '';
        this.cells = [];

        for (let r = 0; r < size; r++) {
            const row = [];
            for (let c = 0; c < size; c++) {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'mine-cell';
                el.dataset.row = String(r);
                el.dataset.col = String(c);
                el.dataset.action = 'minesweeper#reveal';
                el.setAttribute('aria-label', `Cell ${r + 1}, ${c + 1}`);
                this.gridTarget.appendChild(el);
                row.push({ el, mine: false, revealed: false, adjacent: 0 });
            }
            this.cells.push(row);
        }

        let placed = 0;
        while (placed < this.minesValue) {
            const r = Math.floor(Math.random() * size);
            const c = Math.floor(Math.random() * size);
            if (!this.cells[r][c].mine) {
                this.cells[r][c].mine = true;
                placed += 1;
            }
        }

        this.#forEachCell((r, c, cell) => {
            if (cell.mine) {
                return;
            }
            let count = 0;
            this.#forEachNeighbour(r, c, (n) => (count += n.mine ? 1 : 0));
            cell.adjacent = count;
        });
    }

    #revealCell(r, c) {
        const cell = this.cells[r][c];
        if (cell.revealed) {
            return;
        }
        cell.revealed = true;
        cell.el.classList.add('is-revealed');

        if (cell.mine) {
            cell.el.classList.add('is-mine');
            cell.el.innerHTML = this.hasImageValue
                ? `<img src="${this.imageValue}" alt="Mine">`
                : '💥';
            this.#end(false);
            return;
        }

        cell.el.textContent = cell.adjacent > 0 ? String(cell.adjacent) : '';
        if (cell.adjacent === 0) {
            this.#forEachNeighbour(r, c, (_, nr, nc) => this.#revealCell(nr, nc));
        }
        this.#checkWin();
    }

    #checkWin() {
        let revealed = 0;
        this.#forEachCell((_, __, cell) => (revealed += cell.revealed ? 1 : 0));
        if (revealed === this.sizeValue * this.sizeValue - this.minesValue) {
            this.#end(true);
        }
    }

    #end(won) {
        this.ended = true;
        this.#stopTimer();
        this.messageTarget.hidden = false;
        if (won) {
            this.messageTarget.textContent = `You won in ${this.time}s! 🎉`;
            if (this.bestTime === null || this.time < this.bestTime) {
                this.bestTime = this.time;
                try {
                    localStorage.setItem('minesweeper.bestTime', String(this.time));
                } catch {
                    /* storage unavailable */
                }
                this.#renderBest();
            }
        } else {
            this.messageTarget.textContent = 'Boom! You lost.';
        }
    }

    #stopTimer() {
        clearInterval(this.interval);
    }

    #readBestTime() {
        try {
            const value = localStorage.getItem('minesweeper.bestTime');
            return value === null ? null : Number(value);
        } catch {
            return null;
        }
    }

    #renderBest() {
        this.bestTarget.textContent = this.bestTime === null ? 'N/A' : `${this.bestTime}s`;
    }

    #forEachCell(fn) {
        this.cells.forEach((row, r) => row.forEach((cell, c) => fn(r, c, cell)));
    }

    #forEachNeighbour(r, c, fn) {
        for (let dr = -1; dr <= 1; dr++) {
            for (let dc = -1; dc <= 1; dc++) {
                const nr = r + dr;
                const nc = c + dc;
                if ((dr || dc) && nr >= 0 && nr < this.sizeValue && nc >= 0 && nc < this.sizeValue) {
                    fn(this.cells[nr][nc], nr, nc);
                }
            }
        }
    }
}
