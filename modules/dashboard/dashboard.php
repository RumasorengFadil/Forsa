<?php

declare(strict_types=1);

require __DIR__ . '/../../shared/bootstrap.php';
$currentUser = require_login();
require __DIR__ . '/../../shared/menu.php';

use Forsa\Database;

$pdo = Database::connection();
$shapList = $pdo->query('SELECT id, code, short_name FROM forsa_shap_entities WHERE is_active = TRUE ORDER BY sort_order')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FTK Workforce Monitoring Dashboard — FORSA</title>
<link rel="stylesheet" href="assets/css/forsa.css">
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
            <div id="dashboard-content">
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
                                    <option value="<?= (int) $s['id'] ?>"><?= e($s['short_name']) ?> — <?= e($s['code']) ?></option>
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

<script>
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
