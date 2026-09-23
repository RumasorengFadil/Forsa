# FORSA AI Assistant — Tahap 1: UI Foundation (PRD Phase 1)

Implementasi bertahap sesuai [`docs/ai-assistant/PRD.md`](../../../ai-assistant/PRD.md) (disalin dari `PRD_FORSA_AI_Assistant.md`). Tahap ini mencakup **Phase 1 — UI Foundation** (PRD §56): mascot placeholder, sidebar, greeting, scroll behavior, animation state machine, conversation UI. **Tidak ada backend AI, tool calling, semantic layer, atau akses database baru pada tahap ini** — itu Phase 2 dan seterusnya, dan Phase 3 mewajibkan gate pertanyaan ke user (PRD §5/§57) sebelum semantic layer/tool mapping dibuat.

## Summary

- Mascot placeholder (SVG, karakter original: badan biru bulat, "data core" bercahaya di dada sebagai identitas visual, antena, mata yang berkedip) tampil fixed di kanan-bawah halaman dashboard.
- Idle animation: breathing, blink, body sway, look-around, arm sway, micro bounce — seluruhnya CSS keyframes, dinonaktifkan otomatis lewat `prefers-reduced-motion: reduce`.
- Greeting: teks pertama selalu tetap ("Halo, ada yang bisa saya bantu...?"), berubah acak (tidak mengulang teks sebelumnya) setiap 10 detik, berhenti saat mascot disembunyikan scroll atau sidebar terbuka.
- Scroll behavior: scroll ke bawah (hysteresis `deltaY > 50`) memicu mascot "jump" keluar viewport (`display:none` tidak dipakai — transform+opacity); scroll ke atas (`deltaY < -40`) memicu animasi "climb" kembali ke idle.
- Klik mascot: state machine `reacting → pulling_sidebar → jumping_to_sidebar → (docked)` sambil sidebar AI slide dari kanan, mascot "masuk" ke sidebar (memudar) selama sidebar terbuka, lalu kembali muncul & idle saat sidebar ditutup.
- Sidebar: header (mascot mini, judul, tombol Baru/Riwayat/Tutup), area chat (pesan user/assistant, suggested questions dari PRD §33), riwayat percakapan (state kosong jujur — belum ada penyimpanan, itu Phase 2), form kirim pesan.
- Karena belum ada LLM/tool calling, mengirim pesan menghasilkan balasan placeholder yang jujur ("FORSA AI Assistant belum terhubung ke model AI..."), bukan jawaban yang dipalsukan — sesuai prinsip "real content or honest placeholder".

## Files Changed

- **Baru:**
  - [public/assets/css/ai-assistant.css](../../../../public/assets/css/ai-assistant.css) — seluruh styling mascot, sidebar, animasi.
  - [public/assets/js/ai/mascot-animation.js](../../../../public/assets/js/ai/mascot-animation.js) — rotasi teks greeting (PRD §27).
  - [public/assets/js/ai/mascot-scroll.js](../../../../public/assets/js/ai/mascot-scroll.js) — deteksi arah scroll + hysteresis (PRD §28).
  - [public/assets/js/ai/mascot-controller.js](../../../../public/assets/js/ai/mascot-controller.js) — state machine mascot (PRD §43), wiring scroll + klik.
  - [public/assets/js/ai/ai-chat.js](../../../../public/assets/js/ai/ai-chat.js) — UI sidebar chat, suggested questions, riwayat placeholder, state machine sidebar (PRD §44).
  - [public/assets/js/ai/ai-assistant.js](../../../../public/assets/js/ai/ai-assistant.js) — bootstrap penghubung mascot ↔ sidebar.
  - [docs/ai-assistant/PRD.md](../../../ai-assistant/PRD.md) — salinan PRD sumber (source of truth fitur ini).
  - [docs/ai-assistant/README.md](../../../ai-assistant/README.md) — status implementasi per phase.
- **Diubah:**
  - [modules/dashboard/dashboard.php](../../../../modules/dashboard/dashboard.php) — tambah `<link>` CSS, markup mascot + sidebar, 5 `<script>` tag baru (di dashboard saja, sesuai PRD §24.1 "Dashboard / halaman utama FORSA").
  - [docs/README.md](../../../README.md) — tambah tautan ke `docs/ai-assistant/`.
  - `.env` / `.env.example` — tambah `MODEL_API_KEY` (kosong di `.env.example`, terisi di `.env` yang sudah gitignored — **tidak pernah masuk git**) dan `MODEL_NAME=gpt-4o-mini`. **Belum dipakai di kode manapun pada tahap ini** — disiapkan untuk Phase 2.

## Database Changes
None.

## API Changes
None.

## Architecture Changes
Modul baru `public/assets/js/ai/` (frontend-only untuk tahap ini), mengikuti struktur folder yang direkomendasikan PRD §41 (`ai-assistant.js`, `ai-chat.js`, `mascot-controller.js`, `mascot-scroll.js`, `mascot-animation.js`). Belum ada modul backend (`modules/ai/`) — itu mulai Phase 2.

## Documentation Updated
- `docs/ai-assistant/README.md` (baru, status tracking per phase).
- `docs/README.md` (tautan baru).
- Laporan ini.

## Tests Performed
- `php -l modules/dashboard/dashboard.php` — tanpa error.
- `node --check` pada seluruh file di `public/assets/js/ai/*.js` — tanpa error.
- `grep` em dash di seluruh file baru — nihil di teks yang dirender ke user (hanya di komentar kode, di luar cakupan R-02).

## Manual Test
Di Browser pane (`http://localhost:8888/Forsa/dashboard`):
1. Halaman dimuat → mascot muncul kanan-bawah, greeting bubble tampil "Halo, ada yang bisa saya bantu...?" — dicek visual via screenshot.
2. Klik mascot → sidebar terbuka (dicek `#ai-sidebar.open`), header lengkap (mini-mascot, judul, Baru/Riwayat/Tutup), greeting bubble sidebar + 4 suggested questions dari PRD §33.
3. Klik "Riwayat" → menampilkan "Belum ada percakapan tersimpan." (state kosong jujur, dicek via `textContent`).
4. Ketik pesan & submit form (juga diuji lewat klik suggested question) → muncul bubble user + balasan placeholder assistant, dicek `innerHTML` `#ai-chat-messages` — tidak ada em dash, tidak ada data yang dipalsukan.
5. Klik "Baru" → chat kosong lagi, suggested questions muncul ulang (dicek `querySelectorAll('.ai-suggested-q').length === 4`).
6. Klik "Tutup" → sidebar tertutup (`open` class hilang), mascot kembali `data-state="idle"` dan `docked` class terlepas.
7. Scroll ke bawah (scroll asli via mouse wheel, bukan `scrollTo` terprogram yang tidak memicu `requestAnimationFrame` dengan andal) → `#ai-mascot[data-scroll]` berubah jadi `"hidden"`.
8. Scroll ke atas → `data-scroll` kembali jadi `"idle"` (climb animation).
9. Tidak ada error di console browser pada seluruh langkah di atas (`read_console_messages`).

## Known Limitations
- Mascot adalah placeholder SVG 2D, bukan aset 3D (`.glb`) final — lihat catatan di `docs/ai-assistant/README.md`.
- Sidebar chat belum terhubung ke LLM/tool calling apa pun — balasan selalu placeholder yang sama, sesuai scope Phase 1.
- Riwayat percakapan hanya UI shell kosong; penyimpanan (`ai_conversations`/`ai_messages`) belum dibuat — itu Phase 2 (PRD §45).
- Mascot & sidebar baru dipasang di halaman Dashboard (sesuai PRD §24.1 "Dashboard / halaman utama FORSA"); belum di halaman Manajemen User — beri tahu kalau PRD dimaksudkan tampil di seluruh halaman setelah login.

## Next Step (mengikuti PRD, bukan asumsi implementor)

Tahap berikutnya secara alami adalah **Phase 2 — AI Core** (provider abstraction `AiProviderInterface`, `OpenAiProvider` memakai `MODEL_API_KEY`/`gpt-4o-mini` yang sudah disiapkan, LLM gateway, tool registry kosong, conversation storage `ai_conversations`/`ai_messages`). Phase 2 **tidak menyentuh data FTK/dashboard sama sekali** (hanya infrastruktur AI generik + tabel-tabel yang sudah didefinisikan eksplisit di PRD §45), jadi tidak melanggar gate PRD §5.

Setelah Phase 2 selesai, PRD §5/§57/Phase 3 **mewajibkan berhenti dan bertanya**:

> "Tabel atau view PostgreSQL FORSA mana saja yang ingin digunakan sebagai sumber data AI Assistant?"

Implementasi tidak akan lanjut ke semantic layer/tool `query_forsa`/materialized view sebelum pertanyaan itu dijawab.
