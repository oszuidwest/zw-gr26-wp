/**
 * ZW-GR26 — ZuidWest Verkiezingen 2026
 * Programma-dropdown + resultaten-drawer met donut, tabel en coalitiebouwer
 */
(() => {
    /* === STEMLOCATIES ACCORDION === */
    document.querySelectorAll('.zw-gr26-stem__row').forEach((row) => {
        row.addEventListener('click', () => {
            const parent = row.closest('.zw-gr26-stem__list');
            parent
                .querySelectorAll('.zw-gr26-stem__row.open')
                .forEach((other) => {
                    if (other !== row) {
                        other.classList.remove('open');
                    }
                });
            row.classList.toggle('open');
        });
    });

    /* === PROGRAMMA DROPDOWN === */
    const selects = document.querySelectorAll(
        '[data-zw-gr26-programma-select]',
    );
    selects.forEach((select) => {
        const container = select.closest('.zw-gr26-programma');

        select.addEventListener('change', (e) => {
            const scope = container || document;
            scope.querySelectorAll('.zw-gr26-programma__list').forEach((el) => {
                el.classList.remove('active');
            });
            if (e.target.value) {
                const el = document.getElementById(e.target.value);
                if (el) {
                    el.classList.add('active');
                }
            }
        });
    });

    /* === RESULTATEN DRAWER === */
    if (typeof zwGr26Resultaten === 'undefined') {
        return;
    }

    const backdrop = document.getElementById('zwvModal');
    const modal = backdrop ? backdrop.querySelector('.zw-gr26-modal') : null;
    const modalTitle = document.getElementById('zwvModalTitle');
    const modalSub = document.getElementById('zwvModalSubtitle');
    const modalClose = document.getElementById('zwvModalClose');
    const donutEl = document.getElementById('zwvDonut');
    const donutTotal = document.getElementById('zwvDonutTotal');
    const donutCoalLabel = document.getElementById('zwvDonutCoalLabel');
    const donutLabel = document.getElementById('zwvDonutLabel');
    const opkomstEl = document.getElementById('zwvOpkomst');
    const tbody = document.getElementById('zwvTbody');
    const coalToggle = document.getElementById('zwvCoalToggle');
    const coalStatusText = document.getElementById('zwvCoalStatusText');
    const coalReset = document.getElementById('zwvCoalReset');
    const tableLabel = document.getElementById('zwvTableLabel');

    if (!backdrop || !modalTitle) {
        return;
    }

    const DEFAULT_COLOR = '#90a4ae';
    const cx = 50;
    const cy = 50;
    const r = 40;
    const circumference = 2 * Math.PI * r;

    let svgResults = null;
    let svgCoal = null;
    let rows = [];
    let coalMode = false;
    let hadMajority = false;
    let currentTotalZetels = 0;
    let currentMajority = 0;
    let triggerElement = null;

    /* --- SVG helpers --- */
    function createSvg(className) {
        const svg = document.createElementNS(
            'http://www.w3.org/2000/svg',
            'svg',
        );
        svg.setAttribute('viewBox', '0 0 100 100');
        svg.classList.add(className);
        return svg;
    }

    function addCircle(svg, kleur, pct, offset) {
        const circle = document.createElementNS(
            'http://www.w3.org/2000/svg',
            'circle',
        );
        circle.setAttribute('cx', cx);
        circle.setAttribute('cy', cy);
        circle.setAttribute('r', r);
        circle.setAttribute('fill', 'none');
        circle.setAttribute('stroke', kleur);
        circle.setAttribute('stroke-width', '14');
        circle.setAttribute(
            'stroke-dasharray',
            `${pct * circumference} ${circumference}`,
        );
        circle.setAttribute('stroke-dashoffset', `${-offset}`);
        svg.appendChild(circle);
    }

    /* --- Coalition helpers --- */
    function getSelectedRows() {
        return rows.filter((row) => row.classList.contains('is-selected'));
    }

    function getSelectedSeats(selected) {
        return selected.reduce(
            (sum, row) => sum + Number(row.dataset.zetels),
            0,
        );
    }

    function drawCoalDonut(selected) {
        svgCoal.replaceChildren();
        let coalOffset = 0;

        selected.forEach((row) => {
            const z = Number(row.dataset.zetels);
            const pct = z / currentTotalZetels;
            addCircle(svgCoal, row.dataset.kleur, pct, coalOffset);
            coalOffset += pct * circumference;
        });

        const sum = getSelectedSeats(selected);
        const remaining = currentTotalZetels - sum;
        if (remaining > 0) {
            addCircle(
                svgCoal,
                '#e8e8e8',
                remaining / currentTotalZetels,
                coalOffset,
            );
        }

        donutTotal.textContent = sum;
        donutCoalLabel.textContent = `van ${currentTotalZetels}`;
    }

    function updateCoalition() {
        const selected = getSelectedRows();
        const sum = getSelectedSeats(selected);

        drawCoalDonut(selected);

        const isMajority = sum >= currentMajority;

        if (isMajority && !hadMajority) {
            donutEl.classList.add('majority-celebrate');
            donutEl.addEventListener(
                'animationend',
                () => donutEl.classList.remove('majority-celebrate'),
                { once: true },
            );
            launchConfetti();
        }
        hadMajority = isMajority;

        donutEl.classList.toggle('has-majority', isMajority);
        modal.classList.toggle('has-majority', isMajority);

        coalStatusText.textContent =
            sum === 0
                ? 'Klik op partijen om een coalitie te vormen'
                : `${sum} van ${currentTotalZetels} zetels`;
    }

    /* --- Confetti --- */
    function launchConfetti() {
        const canvas = document.createElement('canvas');
        canvas.className = 'zw-gr26-confetti-canvas';
        document.body.appendChild(canvas);
        const ctx = canvas.getContext('2d');

        const W = window.innerWidth;
        const H = window.innerHeight;
        canvas.width = W;
        canvas.height = H;

        const colors = [
            '#CC2229',
            '#1B3F94',
            '#2e7d32',
            '#FF6600',
            '#FFD700',
            '#00A651',
            '#8B0000',
            '#00A3E0',
        ];
        const count = 150;
        const pieces = [];

        for (let i = 0; i < count; i++) {
            pieces.push({
                x: Math.random() * W,
                y: Math.random() * -H,
                w: 4 + Math.random() * 6,
                h: 8 + Math.random() * 8,
                color: colors[Math.floor(Math.random() * colors.length)],
                vx: (Math.random() - 0.5) * 4,
                vy: 2 + Math.random() * 4,
                rot: Math.random() * Math.PI * 2,
                rotV: (Math.random() - 0.5) * 0.2,
            });
        }

        let frame;
        const fadeStart = 2500;
        const start = performance.now();

        function draw(now) {
            const elapsed = now - start;
            ctx.clearRect(0, 0, W, H);

            const fadeFactor =
                elapsed > fadeStart ? 1 - (elapsed - fadeStart) / 1000 : 1;
            if (fadeFactor <= 0) {
                cancelAnimationFrame(frame);
                canvas.remove();
                return;
            }

            for (const p of pieces) {
                p.x += p.vx;
                p.vy += 0.06;
                p.y += p.vy;
                p.rot += p.rotV;

                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot);
                ctx.globalAlpha = fadeFactor;
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            }

            frame = requestAnimationFrame(draw);
        }

        frame = requestAnimationFrame(draw);
    }

    /* --- Modal content builders --- */
    function resetState(is2026) {
        coalMode = false;
        hadMajority = false;
        modal.classList.remove('is-coal-mode', 'has-majority');
        modal.classList.toggle('is-wacht', !is2026);
        coalToggle.textContent = 'Bouw coalitie';
        coalToggle.style.display = is2026 ? '' : 'none';
        donutLabel.textContent = is2026
            ? 'Zetelverdeling'
            : 'Huidige zetelverdeling';
        tableLabel.textContent = is2026 ? 'Resultaten' : 'Huidige raad';
        coalStatusText.textContent =
            'Klik op partijen om een coalitie te vormen';
    }

    function renderHeader(data, key, tileName) {
        modalTitle.textContent = data
            ? data.naam
            : tileName
              ? tileName.textContent
              : key;
        modalSub.textContent = data?.has_2026
            ? 'Uitslag gemeenteraadsverkiezingen 2026'
            : 'Huidige samenstelling gemeenteraad';
    }

    function renderOpkomst(data, is2026) {
        opkomstEl.textContent = '';

        if (is2026 && data.opkomst_2026 != null) {
            opkomstEl.textContent = `Opkomst: ${data.opkomst_2026}%`;
            if (data.opkomst_2022 != null) {
                const ref = document.createElement('span');
                ref.className = 'zw-gr26-modal__opkomst-ref';
                ref.textContent = ` (2022: ${data.opkomst_2022}%)`;
                opkomstEl.appendChild(ref);
            }
        } else if (data && data.opkomst_2022 != null) {
            const ref = document.createElement('span');
            ref.className = 'zw-gr26-modal__opkomst-ref';
            ref.textContent = `Opkomst 2022: ${data.opkomst_2022}%`;
            opkomstEl.appendChild(ref);
        }
    }

    function renderDonut(partijen, is2026) {
        donutEl.classList.remove('has-majority');
        if (svgResults) svgResults.remove();
        if (svgCoal) svgCoal.remove();

        svgResults = createSvg('zw-gr26-modal__donut-svg-results');
        svgCoal = createSvg('zw-gr26-modal__donut-svg-coal');

        let offset = 0;
        partijen.forEach((p) => {
            const seats = is2026 ? p.zetels : p.zetels_2022 || 0;
            const pct = seats / currentTotalZetels;
            addCircle(svgResults, p.kleur || DEFAULT_COLOR, pct, offset);
            offset += pct * circumference;
        });

        const center = donutEl.querySelector('.zw-gr26-modal__donut-center');
        donutEl.insertBefore(svgCoal, center);
        donutEl.insertBefore(svgResults, center);
        donutTotal.textContent = currentTotalZetels;
    }

    function createDiffCell(p, is2026) {
        const td = document.createElement('td');
        if (!is2026) return td;

        if (p.zetels_2022 === null) {
            const span = document.createElement('span');
            span.className = 'zw-gr26-tbl__diff zw-gr26-tbl__diff--nieuw';
            span.textContent = 'NW';
            td.appendChild(span);
        } else {
            const d = p.zetels - p.zetels_2022;
            if (d !== 0) {
                const span = document.createElement('span');
                span.className =
                    d > 0
                        ? 'zw-gr26-tbl__diff zw-gr26-tbl__diff--plus'
                        : 'zw-gr26-tbl__diff zw-gr26-tbl__diff--min';
                span.textContent = d > 0 ? `+${d}` : d;
                td.appendChild(span);
            }
        }
        return td;
    }

    function renderTable(partijen, is2026) {
        tbody.replaceChildren();
        rows = [];

        partijen.forEach((p) => {
            const seats = is2026 ? p.zetels : p.zetels_2022 || 0;
            const color = p.kleur || DEFAULT_COLOR;
            const tr = document.createElement('tr');
            tr.dataset.zetels = seats;
            tr.dataset.kleur = color;

            // Dot + name cell (matches first half of header colspan="2").
            const tdParty = document.createElement('td');
            const dot = document.createElement('span');
            dot.className = 'zw-gr26-tbl__dot';
            dot.style.backgroundColor = color;
            tdParty.appendChild(dot);
            const nameSpan = document.createElement('span');
            nameSpan.className = 'zw-gr26-tbl__name';
            nameSpan.textContent = p.naam;
            tdParty.appendChild(nameSpan);

            // Empty cell (second half of header colspan="2").
            const tdSpacer = document.createElement('td');

            const tdSeats = document.createElement('td');
            tdSeats.className = 'zw-gr26-tbl__seats';
            tdSeats.textContent = seats;

            const tdDiff = createDiffCell(p, is2026);

            tr.addEventListener('click', () => {
                if (!coalMode) return;
                tr.classList.toggle('is-selected');
                updateCoalition();
            });

            tr.appendChild(tdParty);
            tr.appendChild(tdSpacer);
            tr.appendChild(tdSeats);
            tr.appendChild(tdDiff);
            tbody.appendChild(tr);
            rows.push(tr);
        });
    }

    /* --- Event handlers --- */
    coalToggle.addEventListener('click', () => {
        coalMode = !coalMode;
        modal.classList.toggle('is-coal-mode', coalMode);
        coalToggle.textContent = coalMode
            ? 'Terug naar resultaten'
            : 'Bouw coalitie';
        donutLabel.textContent = coalMode ? 'Coalitie' : 'Zetelverdeling';

        if (coalMode) {
            drawCoalDonut(getSelectedRows());
        } else {
            donutTotal.textContent = currentTotalZetels;
            for (const row of rows) row.classList.remove('is-selected');
            donutEl.classList.remove('has-majority');
            modal.classList.remove('has-majority');
            hadMajority = false;
        }
    });

    coalReset.addEventListener('click', () => {
        for (const row of rows) row.classList.remove('is-selected');
        updateCoalition();
    });

    /* --- Open drawer on tile click/keypress --- */
    function openTile(tile) {
        const key = tile.dataset.gemeente;
        const data = zwGr26Resultaten[key] || null;
        const is2026 = data ? data.has_2026 : false;
        const partijen = data ? data.partijen : [];
        const tileName = tile.querySelector('.zw-gr26-tile__name');

        currentTotalZetels = data ? data.totaal_zetels : 0;
        currentMajority = Math.floor(currentTotalZetels / 2) + 1;

        resetState(is2026);
        renderHeader(data, key, tileName);
        renderOpkomst(data, is2026);
        renderDonut(partijen, is2026);
        renderTable(partijen, is2026);

        triggerElement = tile;
        backdrop.classList.add('is-open');
        modalClose.focus();
    }

    document
        .querySelectorAll('.zw-gr26-tile[data-gemeente]')
        .forEach((tile) => {
            tile.addEventListener('click', () => openTile(tile));
            tile.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openTile(tile);
                }
            });
        });

    /* --- Focus trap --- */
    modal.addEventListener('keydown', (e) => {
        if (e.key !== 'Tab') return;

        const focusable = [
            ...modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
            ),
        ].filter((el) => el.offsetWidth > 0);

        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    });

    /* --- Close drawer --- */
    function closeModal() {
        backdrop.classList.remove('is-open');
        if (triggerElement) {
            triggerElement.focus();
            triggerElement = null;
        }
    }

    modalClose.addEventListener('click', closeModal);

    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            closeModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && backdrop.classList.contains('is-open')) {
            closeModal();
        }
    });
})();
