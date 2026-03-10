/**
 * ZW-GR26 — ZuidWest Verkiezingen 2026
 * Programma-dropdown + resultaten-drawer met donut, tabel en coalitiebouwer
 */
(() => {
    /* === STEMLOCATIES ACCORDION === */
    document.querySelectorAll('.zwv-stem__row').forEach((row) => {
        row.addEventListener('click', () => {
            const parent = row.closest('.zwv-stem__list');
            parent.querySelectorAll('.zwv-stem__row.open').forEach((other) => {
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
        const container = select.closest('.zwv-programma');

        select.addEventListener('change', (e) => {
            const scope = container || document;
            scope.querySelectorAll('.zwv-programma__list').forEach((el) => {
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
    const modal = backdrop ? backdrop.querySelector('.zwv-modal') : null;
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

    /* --- Coalition logic --- */
    function drawCoalDonut() {
        svgCoal.innerHTML = '';
        const selected = rows.filter((row) =>
            row.classList.contains('is-selected'),
        );
        let sum = 0;
        let coalOffset = 0;

        selected.forEach((row) => {
            const z = Number(row.dataset.zetels);
            sum += z;
            const pct = z / currentTotalZetels;
            addCircle(svgCoal, row.dataset.kleur, pct, coalOffset);
            coalOffset += pct * circumference;
        });

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
        const selected = rows.filter((row) =>
            row.classList.contains('is-selected'),
        );
        let sum = 0;
        selected.forEach((row) => {
            sum += Number(row.dataset.zetels);
        });

        drawCoalDonut();

        const isMajority = sum >= currentMajority;

        if (isMajority && !hadMajority) {
            donutEl.classList.add('majority-celebrate');
            donutEl.addEventListener(
                'animationend',
                () => {
                    donutEl.classList.remove('majority-celebrate');
                },
                { once: true },
            );
            launchConfetti();
        }
        hadMajority = isMajority;

        donutEl.classList.toggle('has-majority', isMajority);
        modal.classList.toggle('has-majority', isMajority);

        if (sum === 0) {
            coalStatusText.textContent =
                'Klik op partijen om een coalitie te vormen';
        } else {
            coalStatusText.textContent = `${sum} van ${currentTotalZetels} zetels`;
        }
    }

    /* --- Confetti --- */
    function launchConfetti() {
        const canvas = document.createElement('canvas');
        canvas.className = 'zwv-confetti-canvas';
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

    /* --- Event handlers --- */
    coalToggle.addEventListener('click', () => {
        coalMode = !coalMode;
        modal.classList.toggle('is-coal-mode', coalMode);
        coalToggle.textContent = coalMode
            ? 'Terug naar resultaten'
            : 'Bouw coalitie';
        donutLabel.textContent = coalMode ? 'Coalitie' : 'Zetelverdeling';

        if (coalMode) {
            drawCoalDonut();
        } else {
            donutTotal.textContent = currentTotalZetels;
            rows.forEach((row) => {
                row.classList.remove('is-selected');
            });
            donutEl.classList.remove('has-majority');
            modal.classList.remove('has-majority');
            hadMajority = false;
        }
    });

    coalReset.addEventListener('click', () => {
        rows.forEach((row) => {
            row.classList.remove('is-selected');
        });
        updateCoalition();
    });

    /* --- Open drawer on tile click --- */
    document.querySelectorAll('.zwv-tile[data-gemeente]').forEach((tile) => {
        tile.addEventListener('click', () => {
            const key = tile.dataset.gemeente;
            const data = zwGr26Resultaten[key] || null;
            const is2026 = data ? data.has_2026 : false;
            const partijen = data ? data.partijen : [];
            const tileName = tile.querySelector('.zwv-tile__name');

            currentTotalZetels = data ? data.totaal_zetels : 0;
            currentMajority = Math.floor(currentTotalZetels / 2) + 1;

            // Reset coal mode.
            coalMode = false;
            modal.classList.remove('is-coal-mode', 'has-majority');
            modal.classList.toggle('is-wacht', !is2026);
            coalToggle.textContent = 'Bouw coalitie';
            donutLabel.textContent = is2026
                ? 'Zetelverdeling'
                : 'Huidige zetelverdeling';
            tableLabel.textContent = is2026 ? 'Resultaten' : 'Huidige raad';
            hadMajority = false;

            // Show/hide coalition toggle.
            coalToggle.style.display = is2026 ? '' : 'none';

            // Header.
            modalTitle.textContent = data
                ? data.naam
                : tileName
                  ? tileName.textContent
                  : key;
            modalSub.textContent = is2026
                ? 'Gemeenteraadsverkiezingen 2026'
                : 'Huidige samenstelling gemeenteraad';

            // Opkomst.
            if (is2026 && data.opkomst_2026 != null) {
                let txt = `Opkomst: ${data.opkomst_2026}%`;
                if (data.opkomst_2022 != null) {
                    txt += ` <span class="zwv-modal__opkomst-ref">(2022: ${data.opkomst_2022}%)</span>`;
                }
                opkomstEl.innerHTML = txt;
            } else if (data && data.opkomst_2022 != null) {
                opkomstEl.innerHTML = `<span class="zwv-modal__opkomst-ref">Opkomst 2022: ${data.opkomst_2022}%</span>`;
            } else {
                opkomstEl.textContent = '';
            }

            // Build results donut.
            donutEl.classList.remove('has-majority');
            if (svgResults) svgResults.remove();
            if (svgCoal) svgCoal.remove();

            svgResults = createSvg('zwv-modal__donut-svg-results');
            svgCoal = createSvg('zwv-modal__donut-svg-coal');

            let offset = 0;
            partijen.forEach((p) => {
                const seats = is2026 ? p.zetels : p.zetels_2022 || 0;
                const pct = seats / currentTotalZetels;
                addCircle(svgResults, p.kleur || '#90a4ae', pct, offset);
                offset += pct * circumference;
            });

            const center = donutEl.querySelector('.zwv-modal__donut-center');
            donutEl.insertBefore(svgCoal, center);
            donutEl.insertBefore(svgResults, center);
            donutTotal.textContent = currentTotalZetels;

            // Build table.
            tbody.innerHTML = '';
            rows = [];
            partijen.forEach((p) => {
                const seats = is2026 ? p.zetels : p.zetels_2022 || 0;
                const tr = document.createElement('tr');
                tr.dataset.zetels = seats;
                tr.dataset.kleur = p.kleur || '#90a4ae';

                const td1 = document.createElement('td');
                td1.innerHTML = `<span class="zwv-tbl__dot" style="background:${p.kleur || '#90a4ae'}"></span><span class="zwv-tbl__name">${p.naam}</span>`;

                const td2 = document.createElement('td');

                const td3 = document.createElement('td');
                td3.className = 'zwv-tbl__seats';
                td3.textContent = seats;

                const td4 = document.createElement('td');
                if (is2026) {
                    if (p.zetels_2022 === null) {
                        td4.innerHTML =
                            '<span class="zwv-tbl__diff zwv-tbl__diff--nieuw">nieuw</span>';
                    } else {
                        const d = p.zetels - p.zetels_2022;
                        if (d !== 0) {
                            const cls =
                                d > 0
                                    ? 'zwv-tbl__diff zwv-tbl__diff--plus'
                                    : 'zwv-tbl__diff zwv-tbl__diff--min';
                            td4.innerHTML = `<span class="${cls}">${d > 0 ? `+${d}` : d}</span>`;
                        }
                    }
                }

                tr.addEventListener('click', () => {
                    if (!coalMode) return;
                    tr.classList.toggle('is-selected');
                    updateCoalition();
                });

                tr.appendChild(td1);
                tr.appendChild(td2);
                tr.appendChild(td3);
                tr.appendChild(td4);
                tbody.appendChild(tr);
                rows.push(tr);
            });

            // Reset status.
            coalStatusText.textContent =
                'Klik op partijen om een coalitie te vormen';

            backdrop.classList.add('is-open');
        });
    });

    /* --- Close drawer --- */
    function closeModal() {
        backdrop.classList.remove('is-open');
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
