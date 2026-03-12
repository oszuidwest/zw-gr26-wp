/**
 * ZuidWest Verkiezingen 2026 front-end interactie.
 *
 * Centrale modal helper, video modal, programma-dropdown,
 * resultaten-drawer met donut, tabel, en coalitiebouwer.
 *
 * @package ZWGR26
 */
(() => {
    /* === MODAL HELPER === */

    /** @type {string} History state key used by all modals. */
    const HISTORY_KEY = 'zwgr26Modal';

    /** @type {?Object} Currently active modal instance. */
    let activeModal = null;

    /** @type {Object<string, Function>} Restore callbacks for forward navigation, keyed by modal type. */
    const modalRestorers = {};

    /**
     * Creates a keyboard focus trap for a modal panel.
     *
     * @access private
     *
     * @param {HTMLElement} panel The panel element to trap focus within.
     * @return {Function} Callback to rebuild the cached focusable-element list.
     */
    function createFocusTrap(panel) {
        let cache = [];
        let focusinHandler = null;

        function refresh() {
            cache = [
                ...panel.querySelectorAll(
                    'button, [href], input, select, textarea, video, [tabindex]:not([tabindex="-1"])',
                ),
            ].filter((el) => el.offsetWidth > 0);
        }

        panel.addEventListener('keydown', (e) => {
            if (e.key !== 'Tab' || cache.length === 0) return;
            const first = cache[0];
            const last = cache[cache.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        function activate() {
            refresh();
            if (focusinHandler) return;
            focusinHandler = (e) => {
                if (!panel.contains(e.target) && cache.length > 0) {
                    e.preventDefault();
                    cache[0].focus();
                }
            };
            document.addEventListener('focusin', focusinHandler);
        }

        function deactivate() {
            if (focusinHandler) {
                document.removeEventListener('focusin', focusinHandler);
                focusinHandler = null;
            }
        }

        return { refresh, activate, deactivate };
    }

    /** @type {boolean} Whether the active modal pushed a history entry. */
    let historyPushed = false;

    /** @type {boolean} Whether a history.back() close is in flight, awaiting popstate. */
    let closePending = false;

    /**
     * Opens a modal, closing any previously active one first.
     *
     * @access private
     *
     * @param {Object}       modal       Modal descriptor with open() and close() methods.
     * @param {Object}       historyData Data stored in the history state for restore.
     * @param {?HTMLElement}  trigger     Element to refocus when the modal closes.
     */
    function openModal(modal, historyData, trigger) {
        if (activeModal) activeModal.close();
        activeModal = modal;
        activeModal.trigger = trigger || null;
        closePending = false;
        modal.open();
        document.body.classList.add('zw-gr26-modal-open');
        if (historyData) {
            history.pushState({ [HISTORY_KEY]: historyData }, '');
        }
        historyPushed = !!historyData;
    }

    /**
     * Restores a modal on forward navigation (no history push).
     *
     * The history entry already exists, so we skip the push but still
     * mark historyPushed so the back button closes the modal.
     *
     * @access private
     *
     * @param {Object} modal Modal descriptor.
     */
    function restoreModal(modal) {
        if (activeModal) activeModal.close();
        activeModal = modal;
        activeModal.trigger = null;
        closePending = false;
        modal.open();
        document.body.classList.add('zw-gr26-modal-open');
        historyPushed = true;
    }

    /**
     * Closes the active modal (DOM only, no history navigation).
     *
     * @access private
     */
    function closeActiveModal() {
        if (!activeModal) return;
        activeModal.close();
        document.body.classList.remove('zw-gr26-modal-open');
        if (activeModal.trigger) {
            activeModal.trigger.focus();
            activeModal.trigger = null;
        }
        activeModal = null;
        historyPushed = false;
        closePending = false;
    }

    /**
     * Closes the active modal and navigates back in history.
     *
     * history.back() is async — popstate fires after the history traversal
     * completes. The guard prevents a second call (e.g. rapid Escape presses)
     * from pushing another history.back() before popstate has cleaned up.
     *
     * @access private
     */
    function closeActiveModalWithHistory() {
        if (!activeModal || closePending) return;
        if (historyPushed) {
            closePending = true;
            history.back();
        } else {
            closeActiveModal();
        }
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && activeModal) {
            closeActiveModalWithHistory();
        }
    });

    window.addEventListener('popstate', (e) => {
        const data = e.state?.[HISTORY_KEY];
        if (data?.type && modalRestorers[data.type]) {
            modalRestorers[data.type](data);
        } else {
            closeActiveModal();
        }
    });

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

    /* === PODCAST COVER SHUFFLE === */
    if (typeof zwGr26PodcastInstances !== 'undefined') {
        function preloadCovers(covers) {
            const loads = [];
            for (const c of covers) {
                for (const url of [c.src, c.src2x]) {
                    loads.push(
                        new Promise((resolve) => {
                            const img = new Image();
                            img.onload = img.onerror = resolve;
                            img.src = url;
                        }),
                    );
                }
            }
            return Promise.all(loads);
        }

        document
            .querySelectorAll('.zw-gr26-podcast__card[data-podcast-id]')
            .forEach((card) => {
                const id = card.dataset.podcastId;
                const covers = zwGr26PodcastInstances[id];
                if (!covers || covers.length === 0) {
                    return;
                }

                const imgs = card.querySelectorAll(
                    '.zw-gr26-podcast__polaroid img',
                );
                if (covers.length < imgs.length) {
                    return;
                }

                function pickCovers(n) {
                    const pool = [...covers];
                    const picked = [];
                    for (let i = 0; i < n; i++) {
                        const j = Math.floor(Math.random() * pool.length);
                        picked.push(pool.splice(j, 1)[0]);
                    }
                    return picked;
                }

                function shuffleCovers() {
                    const chosen = pickCovers(imgs.length);
                    preloadCovers(chosen).then(() => {
                        imgs.forEach((img, i) => {
                            img.src = chosen[i].src;
                            img.srcset = chosen[i].srcset;
                        });
                    });
                }

                preloadCovers(covers).then(() => {
                    shuffleCovers();
                    const intervalId = setInterval(() => {
                        if (!card.isConnected) {
                            clearInterval(intervalId);
                            return;
                        }
                        shuffleCovers();
                    }, 3000);
                });
            });
    }

    /* === VIDEO MODAL === */
    const videoBackdrop = document.getElementById('zwgr26VideoModal');
    if (videoBackdrop && typeof videojs !== 'undefined') {
        const videoPanel = videoBackdrop.querySelector('.zw-gr26-video-modal');
        const videoClose = videoBackdrop.querySelector('.zw-gr26-modal__close');
        const videoFocusTrap = createFocusTrap(videoPanel);

        /** @type {?Object} Video.js player instance, lazily initialized. */
        let player = null;

        /**
         * Initializes the Video.js player (if needed) and loads a source URL.
         *
         * @access private
         *
         * @param {string} src HLS stream URL to load.
         */
        function loadVideo(src) {
            if (!player) {
                player = videojs('zwgr26VideoPlayer', {
                    autoplay: true,
                    language: 'nl',
                });
            }
            player.src({ src: src, type: 'application/x-mpegURL' });
        }

        const videoModal = {
            open() {
                videoBackdrop.classList.add('is-open');
                videoFocusTrap.activate();
                videoClose.focus();
            },
            close() {
                videoFocusTrap.deactivate();
                videoBackdrop.classList.remove('is-open');
                if (player) {
                    player.reset();
                }
            },
        };

        videoBackdrop.addEventListener('click', (e) => {
            if (e.target === videoBackdrop) closeActiveModalWithHistory();
        });

        videoClose.addEventListener('click', closeActiveModalWithHistory);

        modalRestorers.video = (data) => {
            loadVideo(data.src);
            restoreModal(videoModal);
        };

        document
            .querySelectorAll(
                '.zw-gr26-vcard__link[data-stream], .zw-gr26-ecard__link[data-stream]',
            )
            .forEach((link) => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const src = link.dataset.stream;
                    loadVideo(src);
                    openModal(videoModal, { type: 'video', src: src }, link);
                });
            });
    }

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

    const resultsFocusTrap = createFocusTrap(modal);

    /** @type {string} Default fallback color for parties without a specified color. */
    const DEFAULT_COLOR = '#90a4ae';

    /** @type {number} Center X coordinate for the SVG donut chart. */
    const cx = 50;

    /** @type {number} Center Y coordinate for the SVG donut chart. */
    const cy = 50;

    /** @type {number} Radius of the SVG donut chart. */
    const r = 40;

    /** @type {number} Circumference of the donut circle. */
    const circumference = 2 * Math.PI * r;

    /** @type {?SVGSVGElement} SVG element for the results donut. */
    let svgResults = null;

    /** @type {?SVGSVGElement} SVG element for the coalition donut overlay. */
    let svgCoal = null;

    /** @type {HTMLTableRowElement[]} All party table rows in the current modal. */
    let rows = [];

    /** @type {boolean} Whether the coalition builder mode is active. */
    let coalMode = false;

    /** @type {boolean} Whether a majority was already reached in the current session. */
    let hadMajority = false;

    /** @type {number} Total number of seats for the current municipality. */
    let currentTotalZetels = 0;

    /** @type {number} Number of seats needed for a majority. */
    let currentMajority = 0;

    /* --- SVG helpers --- */

    /**
     * Creates an SVG element with a fixed viewBox and a CSS class.
     *
     * @access private
     *
     * @param {string} className CSS class to add to the SVG element.
     * @return {SVGSVGElement} The created SVG element.
     */
    function createSvg(className) {
        const svg = document.createElementNS(
            'http://www.w3.org/2000/svg',
            'svg',
        );
        svg.setAttribute('viewBox', '0 0 100 100');
        svg.classList.add(className);
        return svg;
    }

    /**
     * Appends a circle segment to an SVG donut chart.
     *
     * @access private
     *
     * @param {SVGSVGElement} svg    Target SVG element.
     * @param {string}        kleur  Stroke color for the segment.
     * @param {number}        pct    Fraction of the full circle (0–1).
     * @param {number}        offset Dash offset to position the segment.
     */
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

    /**
     * Returns all table rows that are currently selected for the coalition.
     *
     * @access private
     *
     * @return {HTMLTableRowElement[]} Selected rows.
     */
    function getSelectedRows() {
        return rows.filter((row) => row.classList.contains('is-selected'));
    }

    /**
     * Calculates the total number of seats for the given rows.
     *
     * @access private
     *
     * @param {HTMLTableRowElement[]} selected Rows to sum seats for.
     * @return {number} Total seats.
     */
    function getSelectedSeats(selected) {
        return selected.reduce(
            (sum, row) => sum + Number(row.dataset.zetels),
            0,
        );
    }

    /**
     * Draws the coalition donut chart from the selected party rows.
     *
     * Replaces the existing coalition SVG content and updates the seat counter.
     *
     * @access private
     *
     * @param {HTMLTableRowElement[]} selected Rows to visualize.
     */
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

    /**
     * Updates the coalition display after a selection change.
     *
     * Redraws the coalition donut, checks for a majority, and triggers confetti
     * when a majority is reached for the first time.
     *
     * @access private
     */
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

    /**
     * Launches a full-screen confetti animation.
     *
     * Creates a temporary canvas, spawns colored rectangles that fall with
     * gravity, and removes the canvas after a fade-out.
     *
     * @access private
     */
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

        /**
         * Renders a single animation frame of falling confetti.
         *
         * @access private
         *
         * @param {number} now Current timestamp from requestAnimationFrame.
         */
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

    /**
     * Resets the modal to its default state for a municipality.
     *
     * @access private
     *
     * @param {boolean} is2026 Whether 2026 results are available.
     */
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

    /**
     * Renders the modal header with the municipality name and subtitle.
     *
     * @access private
     *
     * @param {?Object}      data     Municipality data object, or null.
     * @param {string}       key      Municipality slug used as fallback title.
     * @param {?HTMLElement}  tileName Element containing the tile display name.
     */
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

    /**
     * Renders voter turnout information in the modal.
     *
     * Displays the 2026 turnout percentage when available, with an optional
     * 2022 reference. Falls back to showing only the 2022 turnout.
     *
     * @access private
     *
     * @param {?Object} data   Municipality data object, or null.
     * @param {boolean} is2026 Whether 2026 results are available.
     */
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

    /**
     * Renders the seat distribution donut chart in the modal.
     *
     * @access private
     *
     * @param {Object[]} partijen Array of party objects with zetels, kleur, etc.
     * @param {boolean}  is2026   Whether to show 2026 or 2022 seat counts.
     */
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

    /**
     * Creates a table cell showing the seat difference between 2026 and 2022.
     *
     * @access private
     *
     * @param {Object}  p      Party data object with zetels and zetels_2022.
     * @param {boolean} is2026 Whether 2026 results are available.
     * @return {HTMLTableCellElement} The constructed diff cell.
     */
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

    /**
     * Renders the results table with a row for each party.
     *
     * Each row displays the party color dot, name, seat count, and optional
     * seat difference. Rows are clickable in coalition mode.
     *
     * @access private
     *
     * @param {Object[]} partijen Array of party objects.
     * @param {boolean}  is2026   Whether to show 2026 or 2022 seat counts.
     */
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

        resultsFocusTrap.refresh();
    });

    coalReset.addEventListener('click', () => {
        for (const row of rows) row.classList.remove('is-selected');
        updateCoalition();
    });

    /* --- Results modal --- */

    const resultsModal = {
        open() {
            backdrop.classList.add('is-open');
            resultsFocusTrap.activate();
            modalClose.focus();
        },
        close() {
            resultsFocusTrap.deactivate();
            backdrop.classList.remove('is-open');
        },
    };

    /**
     * Renders the results modal content for a municipality tile.
     *
     * @access private
     *
     * @param {HTMLElement} tile The municipality tile element.
     */
    function renderTile(tile) {
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
    }

    /**
     * Opens the results modal for a municipality tile.
     *
     * @access private
     *
     * @param {HTMLElement} tile The clicked municipality tile element.
     */
    function openTile(tile) {
        renderTile(tile);
        openModal(
            resultsModal,
            { type: 'resultaten', gemeente: tile.dataset.gemeente },
            tile,
        );
    }

    /**
     * Finds a municipality tile by its data-gemeente value.
     *
     * Uses dataset comparison instead of string interpolation in a selector
     * to avoid issues with special characters in history state values.
     *
     * @access private
     *
     * @param {string} gemeente The municipality slug to find.
     * @return {?HTMLElement} The matching tile element, or null.
     */
    function findTileByGemeente(gemeente) {
        const tiles = document.querySelectorAll('.zw-gr26-tile[data-gemeente]');
        for (const tile of tiles) {
            if (tile.dataset.gemeente === gemeente) {
                return tile;
            }
        }
        return null;
    }

    modalRestorers.resultaten = (data) => {
        const tile = findTileByGemeente(data.gemeente);
        if (tile) {
            renderTile(tile);
            restoreModal(resultsModal);
        }
    };

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

    modalClose.addEventListener('click', closeActiveModalWithHistory);

    backdrop.addEventListener('click', (e) => {
        if (e.target === backdrop) {
            closeActiveModalWithHistory();
        }
    });
})();
