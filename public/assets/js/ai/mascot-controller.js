// FORSA AI Assistant — mascot state machine (PRD section 43) and scroll
// wiring (section 28). Owns the floating mascot button's data-state/
// data-scroll attributes; ai-assistant.js wires this to the sidebar.
window.ForsaAi = window.ForsaAi || {};

window.ForsaAi.MascotController = (function () {
    // Durations mirror the CSS animation lengths in ai-assistant.css so a
    // state change never gets interrupted mid-animation.
    const REACT_MS = 500;
    const PULL_MS = 400;
    const JUMP_MS = 500;
    const SCROLL_TRANSITION_MS = 450;

    function create({ mascotEl, greetingEl, onOpenSidebar }) {
        const greetingIntervalMs = window.ForsaAiConfig?.mascotGreetingIntervalMs;
        const greeting = window.ForsaAi.MascotGreeting.create(greetingEl, greetingIntervalMs);
        let sidebarOpen = false;
        let scrollState = 'idle'; // idle | hiding | hidden | climbing

        function setState(state) {
            mascotEl.dataset.state = state;
        }

        function setScroll(state) {
            scrollState = state;
            mascotEl.dataset.scroll = state;
        }

        function reduceMotion() {
            return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        }

        // ---- Idle / greeting ----
        setState('idle');
        setScroll('idle');
        greeting.start();

        // ---- Scroll show/hide (section 28) ----
        const scroll = window.ForsaAi.MascotScroll.create(
            () => { // hide
                if (sidebarOpen || scrollState === 'hiding' || scrollState === 'hidden') return;
                greeting.stop();
                setScroll('hiding');
                setTimeout(() => { if (scrollState === 'hiding') setScroll('hidden'); }, reduceMotion() ? 0 : SCROLL_TRANSITION_MS);
            },
            () => { // show
                if (sidebarOpen || scrollState === 'idle') return;
                setScroll('climbing');
                setTimeout(() => {
                    if (scrollState === 'climbing') {
                        setScroll('idle');
                        greeting.start();
                    }
                }, reduceMotion() ? 0 : SCROLL_TRANSITION_MS + 150);
            }
        );
        scroll.start();

        // ---- Click -> sidebar (section 29/43) ----
        function handleActivate() {
            if (sidebarOpen) return;
            sidebarOpen = true;
            greeting.stop();

            const jumpStart = reduceMotion() ? 0 : REACT_MS + PULL_MS;
            const jumpEnd = jumpStart + (reduceMotion() ? 0 : JUMP_MS);

            setState('reacting');
            onOpenSidebar(); // starts the sidebar slide-in immediately, in parallel
            setTimeout(() => setState('pulling_sidebar'), reduceMotion() ? 0 : REACT_MS);
            setTimeout(() => setState('jumping_to_sidebar'), jumpStart);
            setTimeout(() => mascotEl.classList.add('docked'), jumpEnd);
        }

        mascotEl.addEventListener('click', handleActivate);
        mascotEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleActivate(); }
        });

        function notifySidebarClosed() {
            sidebarOpen = false;
            mascotEl.classList.remove('docked');
            setState('idle');
            if (scrollState === 'idle') greeting.start();
        }

        return { notifySidebarClosed };
    }

    return { create };
})();
