// FORSA AI Assistant — Phase 1 bootstrap. Wires the mascot controller to the
// sidebar chat shell. Loaded once, after mascot-animation.js, mascot-scroll.js,
// mascot-controller.js and ai-chat.js (see dashboard.php's <script> order).
(function () {
    const mascotEl = document.getElementById('ai-mascot');
    const greetingEl = document.getElementById('ai-mascot-greeting');
    const sidebarEl = document.getElementById('ai-sidebar');
    if (!mascotEl || !sidebarEl) return; // page doesn't include the AI assistant markup

    const chat = window.ForsaAi.AiChat.create({
        root: sidebarEl,
        onClose: () => { chat.close(); mascot.notifySidebarClosed(); },
    });

    const mascot = window.ForsaAi.MascotController.create({
        mascotEl,
        greetingEl,
        sidebarEl,
        appShellEl: document.querySelector('.ai-dashboard-content'),
        onPrepareSidebar: () => chat.prepareOpen(),
        onActivateSidebar: () => chat.activate(),
        onCloseSidebar: () => chat.close(),
    });
})();
