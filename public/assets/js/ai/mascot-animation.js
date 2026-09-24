// PRD 27: a single greeting timeline; hiding never resets the first greeting.
window.ForsaAi = window.ForsaAi || {};
window.ForsaAi.MascotGreeting = {
    create(bubble, intervalMs) {
        const rotationMs = Number.isFinite(intervalMs) && intervalMs > 0 ? intervalMs : 10000;
        const choices = ['Mau lihat gap FTK hari ini?', 'Ada data yang ingin dibandingkan?',
            'Saya bisa bantu membaca data FORSA.', 'Mau cari organisasi dengan gap terbesar?',
            'Coba tanyakan data FTK atau realisasi.'];
        let last = 'Halo, ada yang bisa saya bantu...?';
        let elapsed = 0;
        bubble.textContent = last;
        return {
            tick(delta, visible) {
                elapsed += delta;
                if (elapsed >= rotationMs) {
                    elapsed %= rotationMs;
                    const candidates = choices.filter(text => text !== last);
                    last = candidates[Math.floor(Math.random()*candidates.length)];
                    bubble.textContent = last;
                }
                bubble.setAttribute('aria-hidden', visible ? 'false' : 'true');
                bubble.classList.toggle('visible', visible);
            }
        };
    }
};
