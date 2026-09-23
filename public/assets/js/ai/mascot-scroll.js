// FORSA AI Assistant — scroll direction detection with hysteresis
// (PRD section 28: hide threshold deltaY > 50, show threshold deltaY < -40).
window.ForsaAi = window.ForsaAi || {};

window.ForsaAi.MascotScroll = (function () {
    function create(onHide, onShow) {
        let lastY = window.scrollY;
        let accumulated = 0;
        let direction = null; // 'down' | 'up' | null
        let ticking = false;

        const HIDE_THRESHOLD = 50;
        const SHOW_THRESHOLD = -40;

        function handleScroll() {
            const y = window.scrollY;
            const delta = y - lastY;
            lastY = y;

            // Accumulate while moving the same direction; reset the
            // accumulator whenever direction flips so small back-and-forth
            // jitter near the top of the page never triggers an animation
            // (the hysteresis the PRD asks for).
            const thisDirection = delta > 0 ? 'down' : (delta < 0 ? 'up' : direction);
            if (thisDirection !== direction) {
                accumulated = 0;
                direction = thisDirection;
            }
            accumulated += delta;

            if (direction === 'down' && accumulated > HIDE_THRESHOLD) {
                accumulated = 0;
                onHide();
            } else if (direction === 'up' && accumulated < SHOW_THRESHOLD) {
                accumulated = 0;
                onShow();
            }

            ticking = false;
        }

        function onScroll() {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(handleScroll);
        }

        function start() {
            window.addEventListener('scroll', onScroll, { passive: true });
        }

        function stop() {
            window.removeEventListener('scroll', onScroll);
        }

        return { start, stop };
    }

    return { create };
})();
