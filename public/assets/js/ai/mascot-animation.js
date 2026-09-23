// FORSA AI Assistant — greeting text rotation (PRD section 27).
// Idle body animation (breathing/blink/sway/look-around/bounce) is pure CSS
// driven by the [data-state="idle"] attribute (see ai-assistant.css); this
// module only owns the greeting bubble's timing and wording, since that is
// the one piece of "idle animation" that is actual content, not motion.
window.ForsaAi = window.ForsaAi || {};

window.ForsaAi.MascotGreeting = (function () {
    const FIRST_GREETING = 'Halo, ada yang bisa saya bantu...?';
    const ROTATING_GREETINGS = [
        'Mau lihat gap FTK hari ini?',
        'Ada data yang ingin dibandingkan?',
        'Saya bisa bantu membaca data FORSA.',
        'Mau cari organisasi dengan gap terbesar?',
        'Coba tanyakan data FTK atau realisasi.',
    ];

    function create(bubbleEl) {
        let timer = null;
        let lastText = null;

        function pickNext() {
            if (ROTATING_GREETINGS.length === 1) return ROTATING_GREETINGS[0];
            let next;
            do {
                next = ROTATING_GREETINGS[Math.floor(Math.random() * ROTATING_GREETINGS.length)];
            } while (next === lastText);
            return next;
        }

        function show(text) {
            lastText = text;
            bubbleEl.textContent = text;
            bubbleEl.classList.add('visible');
        }

        function hide() {
            bubbleEl.classList.remove('visible');
        }

        // T=0 fixed greeting, then a different random one every 10s
        // (section 27) — stopped while the sidebar is open or the mascot is
        // hidden by scroll, restarted when back to idle.
        function start() {
            stop();
            show(FIRST_GREETING);
            timer = setInterval(() => show(pickNext()), 10000);
        }

        function stop() {
            if (timer) clearInterval(timer);
            timer = null;
            hide();
        }

        return { start, stop, hide };
    }

    return { create };
})();
