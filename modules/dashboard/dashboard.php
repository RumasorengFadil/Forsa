<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
$currentUser = require_login();
require __DIR__ . '/../../shared/menu.php';

use Forsa\Database;

$pdo = Database::connection();
$shapList = $pdo->query('SELECT id, code, short_name FROM forsa_shap_entities WHERE is_active = TRUE ORDER BY sort_order')->fetchAll();
$aiConfig = require __DIR__ . '/../../config/ai.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FTK Workforce Monitoring Dashboard - FORSA</title>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/forsa.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/ai-assistant.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php render_topbar($currentUser, 'dashboard'); ?>

    <div class="page-wrap">
        <div class="dash-header">
            <div class="dash-title">
                <h2>
                    FTK WORKFORCE MONITORING DASHBOARD
                    <button type="button" class="help-btn" id="btn-dashboard-guide" aria-label="Bantuan / mulai panduan halaman dashboard" title="Panduan halaman">!</button>
                </h2>
                <div class="period-label" id="period-label">Memuat…</div>
            </div>
            <div class="dash-controls">
                <select class="select-history" id="select-history"></select>
                <button class="btn btn-primary" id="btn-open-upload">Upload Realisasi SH/AP</button>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" data-tab="tab-dashboard">Dashboard</button>
            <button class="tab-btn" data-tab="tab-history">Histori Upload</button>
        </div>

        <div id="tab-dashboard" class="tab-panel active">
            <div id="dashboard-content" aria-live="polite">
                <div class="empty-state"><span class="spin"></span><p>Memuat dashboard…</p></div>
            </div>
        </div>

        <div id="tab-history" class="tab-panel">
            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                    <h3 class="card-title" style="margin:0;">Histori Upload</h3>
                    <select id="history-shap-filter" style="padding:8px 10px; border:1px solid var(--border); border-radius:8px; font:inherit; font-size:12.5px;">
                        <option value="">Semua SH/AP</option>
                        <?php foreach ($shapList as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['short_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="history-table-wrap"><div class="empty-state"><span class="spin"></span></div></div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal-backdrop" id="modal-upload">
    <div class="modal">
        <div class="modal-header">
            <h3>Upload Realisasi SH/AP</h3>
            <button class="modal-close" data-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="step-indicator">
                <div class="step-dot active" data-step="1"></div>
                <div class="step-dot" data-step="2"></div>
                <div class="step-dot" data-step="3"></div>
            </div>

            <div id="upload-step-1">
                <form id="form-upload">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>SH/AP <span class="req">*</span></label>
                            <select name="shap_id" id="up-shap" required>
                                <option value="">Pilih SH/AP…</option>
                                <?php foreach ($shapList as $s): ?>
                                    <option value="<?= (int) $s['id'] ?>"><?= e($s['short_name']) ?> - <?= e($s['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label>Periode (Bulan &amp; Tahun) <span class="req">*</span></label>
                            <input type="month" name="period" id="up-period" required>
                        </div>
                        <div class="form-field full">
                            <label>File Template (.xlsx) <span class="req">*</span></label>
                            <div class="dropzone" id="dropzone">
                                <div id="dropzone-text">Klik atau seret file .xlsx ke sini</div>
                                <input type="file" name="file" id="up-file" accept=".xlsx" style="display:none;">
                            </div>
                        </div>
                        <div class="form-field full">
                            <label>Catatan (opsional)</label>
                            <textarea name="note" id="up-note" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <div id="upload-step-2" style="display:none;">
                <div id="preview-content"></div>
            </div>

            <div id="upload-step-3" style="display:none;">
                <div class="empty-state" id="confirm-result"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-close>Batal</button>
            <button class="btn btn-secondary" id="btn-back-upload" style="display:none;">Kembali</button>
            <button class="btn btn-primary" id="btn-next-upload">Validasi &amp; Preview</button>
            <button class="btn btn-primary" id="btn-confirm-upload" style="display:none;">Konfirmasi Import</button>
        </div>
    </div>
</div>

<!-- Detail Drilldown Modal (Tahap 3) — populated from data already fetched for
     the dashboard/tree, never a separate calculation. -->
<div class="modal-backdrop" id="modal-detail">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
        <div class="modal-header">
            <h3 id="detail-modal-title">Detail</h3>
            <button class="modal-close" data-close aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body" id="detail-modal-body"></div>
        <div class="modal-footer" id="detail-modal-footer"></div>
    </div>
</div>

<!-- FORSA AI Assistant (PRD_FORSA_AI_Assistant.md, Phase 1 — UI Foundation):
     mascot placeholder + sidebar shell. No AI backend is wired yet — see
     public/assets/js/ai/ai-chat.js. -->
<button type="button" class="ai-mascot" id="ai-mascot" aria-label="Buka FORSA AI Assistant" data-state="idle" data-scroll="idle">
    <div class="ai-mascot-greeting" id="ai-mascot-greeting" aria-live="polite"></div>
    <svg class="ai-mascot-figure" viewBox="0 0 120 140" aria-hidden="true">
        <ellipse class="ai-mascot-shadow" cx="60" cy="132" rx="30" ry="6"></ellipse>
        <g class="ai-mascot-body">
            <path class="ai-mascot-arm-left" d="M22 60 Q6 70 10 90"></path>
            <path class="ai-mascot-arm-right" d="M98 60 Q114 70 110 90"></path>
            <rect class="ai-mascot-torso" x="20" y="18" width="80" height="88" rx="30"></rect>
            <path class="ai-mascot-antenna-stem" d="M60 18 L60 6"></path>
            <circle class="ai-mascot-antenna-tip" cx="60" cy="5" r="4"></circle>
            <circle class="ai-mascot-core" cx="60" cy="76" r="13"></circle>
            <path class="ai-mascot-core-bars" d="M55 80 L55 72 M60 80 L60 68 M65 80 L65 74"></path>
            <g class="ai-mascot-face">
                <g class="ai-mascot-eye ai-mascot-eye-left"><circle cx="45" cy="46" r="6"></circle></g>
                <g class="ai-mascot-eye ai-mascot-eye-right"><circle cx="75" cy="46" r="6"></circle></g>
                <path class="ai-mascot-mouth" d="M50 60 Q60 66 70 60"></path>
            </g>
        </g>
    </svg>
</button>

<div class="ai-sidebar" id="ai-sidebar" aria-hidden="true">
    <div class="ai-sidebar-header">
        <div class="ai-sidebar-mascot-mini" aria-hidden="true"></div>
        <div class="ai-sidebar-title">FORSA AI Assistant</div>
        <div class="ai-sidebar-header-actions">
            <button type="button" class="ai-sidebar-btn" id="btn-ai-new-chat">Baru</button>
            <button type="button" class="ai-sidebar-btn" id="btn-ai-history" aria-label="Riwayat percakapan">Riwayat</button>
            <button type="button" class="ai-sidebar-btn ai-sidebar-icon" id="btn-ai-expand" aria-label="Perbesar lebar sidebar" aria-pressed="false" title="Perbesar/perkecil sidebar">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M6 2H2v4M10 14h4v-4M2 2l4.5 4.5M14 14L9.5 9.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <button type="button" class="ai-sidebar-btn ai-sidebar-close" id="btn-ai-close" aria-label="Tutup FORSA AI Assistant">&times;</button>
        </div>
    </div>
    <div class="ai-sidebar-body">
        <div class="ai-history-list" id="ai-history-list" hidden></div>
        <div class="ai-greeting-bubble" id="ai-sidebar-greeting">Halo, ada yang bisa saya bantu untuk membaca data FORSA?</div>
        <div class="ai-chat-messages" id="ai-chat-messages"></div>
        <div class="ai-suggested-questions" id="ai-suggested-questions"></div>
    </div>
    <div class="ai-sidebar-footer">
        <form id="ai-chat-form" autocomplete="off">
            <input type="text" id="ai-chat-input" name="message" placeholder="Tanyakan data FORSA…" aria-label="Tulis pertanyaan untuk FORSA AI Assistant">
            <button type="submit" class="btn btn-primary btn-sm">Kirim</button>
        </form>
    </div>
</div>

<script>
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    // Config-driven (config/ai.php + .env MASCOT_GREETING_INTERVAL_MS),
    // not hardcoded in mascot-animation.js.
    window.ForsaAiConfig = { mascotGreetingIntervalMs: <?= (int) $aiConfig['mascot_greeting_interval_ms'] ?> };
</script>
<script src="<?= e(asset_url('assets/js/dashboard.js')) ?>"></script>
<script src="<?= e(asset_url('assets/js/ai/mascot-animation.js')) ?>"></script>
<script src="<?= e(asset_url('assets/js/ai/mascot-scroll.js')) ?>"></script>
<script src="<?= e(asset_url('assets/js/ai/mascot-controller.js')) ?>"></script>
<script src="<?= e(asset_url('assets/js/ai/ai-markdown.js')) ?>"></script>
<script src="<?= e(asset_url('assets/js/ai/ai-chat.js')) ?>"></script>
<script src="<?= e(asset_url('assets/js/ai/ai-assistant.js')) ?>"></script>
</body>
</html>
