(function () {
    const fmt = (n) => (n ?? 0).toLocaleString('id-ID');
    const fmtPct = (n) => (n ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';

    let currentSelection = null;

    // ---------- History dropdown ----------
    async function loadHistoryOptions() {
        const res = await fetch('/history_api.php');
        const json = await res.json();
        const opts = json.data;
        const select = document.getElementById('select-history');
        select.innerHTML = '';

        opts.periods.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.value;
            opt.textContent = `Realisasi SH/AP • ${p.label}`;
            select.appendChild(opt);
        });

        if (opts.history.length) {
            const group = document.createElement('optgroup');
            group.label = 'Histori Upload (per SH/AP)';
            opts.history.forEach(h => {
                const opt = document.createElement('option');
                opt.value = h.value;
                opt.textContent = h.label;
                group.appendChild(opt);
            });
            select.appendChild(group);
        }

        currentSelection = opts.default;
        select.value = currentSelection;
        select.addEventListener('change', () => {
            currentSelection = select.value;
            loadDashboard();
            resetTree();
        });
    }

    // ---------- Dashboard content ----------
    function renderKpiSkeleton() {
        const card = () => '<div class="kpi-card"><div class="skeleton" style="height:26px; width:70%; margin-bottom:8px;"></div><div class="skeleton" style="height:12px; width:50%;"></div></div>';
        return `<div class="kpi-row">${card()}${card()}${card()}${card()}${card()}</div>
            <p style="color:var(--ink-soft); font-size:12.5px;">Memuat KPI &amp; agregasi SH/AP untuk periode terpilih…</p>`;
    }

    async function loadDashboard() {
        const content = document.getElementById('dashboard-content');
        content.innerHTML = renderKpiSkeleton();

        const res = await fetch('/dashboard_api.php?selection=' + encodeURIComponent(currentSelection || ''));
        const json = await res.json();
        if (!json.success) {
            content.innerHTML = `<div class="empty-state"><h3>Gagal memuat data</h3><p>${json.message}</p></div>`;
            return;
        }
        const data = json.data;

        document.getElementById('period-label').textContent =
            (data.mode === 'inspection' ? 'Mode Inspeksi Histori • ' : 'Realisasi SH/AP • ') + data.period_label;

        if (!data.has_data) {
            content.innerHTML = `
                <div class="empty-state">
                    <h3>Belum ada data Realisasi SH/AP untuk periode ini.</h3>
                    <p>Upload template FTK/Realisasi untuk mulai menampilkan dashboard.</p>
                    <button class="btn btn-primary" id="btn-empty-upload" style="margin-top:12px;">Upload Realisasi SH/AP</button>
                </div>`;
            document.getElementById('btn-empty-upload').addEventListener('click', openUploadModal);
            return;
        }

        content.innerHTML = renderDashboard(data);
        renderTree(true);
    }

    function pctClass(status) {
        return { fulfilled: 'pct-blue', monitor: 'pct-amber', priority: 'pct-red' }[status] || 'pct-blue';
    }

    // Same buckets as config/ftk_rules.php (fulfillment_thresholds), used only to
    // color the aggregate KPI value so it always reflects the real state instead
    // of a fixed hardcoded color.
    function pemenuhanClass(pct) {
        if (pct >= 100) return 'pct-green';
        if (pct >= 90) return 'pct-amber';
        return 'pct-red';
    }
    function gapClass(gap) {
        return gap >= 0 ? 'pct-green' : 'pct-red';
    }

    function renderDashboard(data) {
        const shapCards = data.per_shap.map(s => `
            <div class="shap-card">
                <div class="shap-name">${s.shap_name}</div>
                <span class="shap-pct ${pctClass(s.status_code)}">${fmtPct(s.pct)}</span>
                <span class="shap-gap">Gap ${s.gap >= 0 ? '+' : ''}${fmt(s.gap)}</span>
            </div>`).join('');

        const buckets = [
            { key: 'fulfilled', range: '≥100%', color: '#1f8a4c' },
            { key: 'monitor', range: '90–99,9%', color: '#c98a1f' },
            { key: 'priority', range: '<90%', color: '#c0392b' },
        ];
        const priorityRows = buckets.map(b => {
            const names = data.priority[b.key] || [];
            return `<div class="priority-row">
                <span class="pct-range" style="color:${b.color}">${b.range}</span>
                <span class="pct-count">${names.length} entitas</span>
                <span class="pct-list">${names.join(', ') || '-'}</span>
            </div>`;
        }).join('');

        const maxGap = Math.max(1, ...data.gap_terbesar.map(g => Math.abs(g.gap)));
        const gapRows = data.gap_terbesar.map(g => `
            <div class="bar-row">
                <span class="bar-label">${g.shap_name}</span>
                <div class="bar-track"><div class="bar-fill" style="width:${g.bar_pct}%"></div></div>
                <span class="bar-value">${g.gap}</span>
            </div>`).join('');

        const jenjangRows = data.per_jenjang.map(j => `
            <div class="stack-bar-row">
                <span class="bar-label">${j.label}</span>
                <div class="stack-track">
                    <div class="stack-fill-ok" style="width:${j.bar_ok_pct}%"></div>
                    <div class="stack-fill-gap" style="width:${j.bar_gap_pct}%"></div>
                </div>
                <span class="stack-values">${fmt(j.ftk)} &nbsp; ${fmt(j.belum_dipenuhi)}</span>
            </div>`).join('');

        const insightLines = data.insight.lines.map(l => `
            <li>
                <span class="insight-num">${l.no}</span>
                <div><b>${l.title}</b>${l.text}</div>
            </li>`).join('');

        return `
        <div class="kpi-row">
            <div class="kpi-card kpi-total">
                <div class="kpi-value">${fmt(data.kpi.total_ftk)}</div>
                <div class="kpi-label">Total FTK</div>
            </div>
            <div class="kpi-card kpi-realisasi">
                <div class="kpi-value">${fmt(data.kpi.total_realisasi)}</div>
                <div class="kpi-label">Total Realisasi</div>
            </div>
            <div class="kpi-card kpi-pemenuhan">
                <div class="kpi-value ${pemenuhanClass(data.kpi.pemenuhan_pct)}">${fmtPct(data.kpi.pemenuhan_pct)}</div>
                <div class="kpi-label">Pemenuhan FTK</div>
            </div>
            <div class="kpi-card kpi-gap">
                <div class="kpi-value ${gapClass(data.kpi.gap_dashboard)}">${data.kpi.gap_dashboard >= 0 ? '+' : ''}${fmt(data.kpi.gap_dashboard)}</div>
                <div class="kpi-label">Gap FTK</div>
            </div>
            <div class="kpi-card kpi-insight">
                <div class="insight-label">EXECUTIVE INSIGHT</div>
                <div class="insight-text">Fokus pemenuhan diarahkan ke SH/AP &lt;90% dan jenjang dengan gap terbesar.</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <h3 class="card-title">Sebaran &amp; Pemenuhan per SH/AP</h3>
                <div class="shap-grid">${shapCards}</div>
            </div>
            <div class="priority-card">
                <h3 class="card-title">Status Prioritas Pemenuhan</h3>
                ${priorityRows}
            </div>
        </div>

        <div class="grid-3">
            <div class="card">
                <h3 class="card-title">Gap Terbesar per SH/AP</h3>
                ${gapRows || '<p style="color:var(--ink-soft); font-size:12.5px;">Tidak ada gap.</p>'}
            </div>
            <div class="card">
                <h3 class="card-title">Pemenuhan FTK per Jenjang Jabatan</h3>
                <div style="font-size:11.5px; color:var(--ink-soft); margin-bottom:12px;">Terpenuhi vs belum dipenuhi pada posisi FTK</div>
                ${jenjangRows}
                <div class="legend-row">
                    <span><span class="legend-dot" style="background:#1f8a4c"></span>Terpenuhi</span>
                    <span><span class="legend-dot" style="background:#d64545"></span>Belum dipenuhi</span>
                </div>
            </div>
            <div class="card">
                <h3 class="card-title">Management Insight</h3>
                <ul class="insight-list">${insightLines || '<li style="color:var(--ink-soft);">Tidak ada insight.</li>'}</ul>
                ${data.insight.footer ? `<div class="insight-footer">${data.insight.footer}</div>` : ''}
            </div>
        </div>

        <div class="section-title">DRILL-DOWN SH/AP › UI/UP/UL › Jenjang Jabatan › Position Grade › Jabatan (masked)</div>
        <div class="card">
            <div class="tree-toolbar">
                <input type="text" id="tree-search" placeholder="Cari organisasi / jabatan…" style="min-width:220px;">
                <select id="tree-filter-level"><option value="">Semua Jenjang</option></select>
                <input type="text" id="tree-filter-grade" placeholder="Position Grade">
                <select id="tree-filter-gap">
                    <option value="">Semua Status Gap</option>
                    <option value="kurang">Kurang</option>
                    <option value="terpenuhi">Terpenuhi</option>
                    <option value="lebih">Lebih</option>
                </select>
                <button class="btn btn-secondary btn-sm" id="btn-apply-tree-filter">Terapkan</button>
            </div>
            <div class="tree-scroll">
                <table class="tree-table" id="tree-table">
                    ${renderTreeHeader(data.per_jenjang.map(j => j.group))}
                    <tbody id="tree-tbody"></tbody>
                </table>
            </div>
            <p class="coverage-note">Gap jenjang dihitung dari data FTK masked.</p>
        </div>
        `;
    }

    // ---------- Tree table ----------
    let treeGroups = [];
    let treeFilters = { search: '', job_level_group: '', position_grade: '', gap_status: '' };

    function renderTreeHeader(groups) {
        treeGroups = groups;
        const groupLabels = { 'gen 1-3': 'GEN 1-3', MD: 'MD', MM: 'MM', MA: 'MA', spesialist: 'SPECIALIST', 'Senior Specialist': 'SR. SPECIALIST', 'Junior Expert': 'JR. EXPERT', Expert: 'EXPERT', 'Senior Expert': 'SR. EXPERT' };
        const metricCols = ['FTK', 'Organik', 'Tugas Karya', 'Pihak Ketiga', 'Total Real.', 'Sisa'];

        const groupHeaderRow = ['<th class="col-name" rowspan="2">UNIT / ORGANISASI / JABATAN</th>']
            .concat(['TOTAL', ...groups].map(g => `<th colspan="6">${g === 'TOTAL' ? 'TOTAL' : (groupLabels[g] || g)}</th>`))
            .join('');

        const subHeaderRow = ['TOTAL', ...groups].map(() =>
            metricCols.map(m => `<th>${m}</th>`).join('')
        ).join('');

        const levelFilter = document.getElementById('tree-filter-level');
        if (levelFilter) {
            levelFilter.innerHTML = '<option value="">Semua Jenjang</option>' +
                groups.map(g => `<option value="${g}">${groupLabels[g] || g}</option>`).join('');
        }

        return `<thead>
            <tr class="group-row">${groupHeaderRow}</tr>
            <tr>${subHeaderRow}</tr>
        </thead>`;
    }

    function metricCells(metrics) {
        const cols = ['ftk', 'realisasi_organik', 'realisasi_tugas_karya', 'realisasi_pihak_ketiga', 'total_realisasi', 'sisa_delta'];
        return cols.map(c => {
            if (c === 'sisa_delta') {
                const v = metrics[c];
                const cls = v > 0 ? 'sisa-pos' : (v < 0 ? 'sisa-neg' : 'sisa-zero');
                return `<td class="num ${cls}">${fmt(v)}</td>`;
            }
            return `<td class="num">${fmt(metrics[c])}</td>`;
        }).join('');
    }

    function renderNodeRow(node, indent) {
        const m = node.metrics.total;
        const groupCells = ['total', ...treeGroups].map(g => {
            const gm = g === 'total' ? m : (node.metrics.groups[g] || { ftk: 0, realisasi_organik: 0, realisasi_tugas_karya: 0, realisasi_pihak_ketiga: 0, total_realisasi: 0, sisa_delta: 0 });
            return metricCells(gm);
        }).join('');

        const toggle = node.has_children
            ? `<button type="button" class="tree-toggle" data-key="${escapeAttr(node.key)}" aria-expanded="false" aria-label="Perluas ${escapeAttr(node.label)}">+</button>`
            : `<span class="tree-leaf-dot" aria-hidden="true"></span>`;

        const gradeSuffix = node.position_grade ? ` — PoG ${node.position_grade}` : '';

        return `<tr data-key="${escapeAttr(node.key)}" data-node='${escapeAttr(JSON.stringify({ path: node.path, snapshot_id: node.snapshot_id, shap: node.type === 'shap' ? node.key.replace('shap:', '') : undefined }))}'>
            <td class="col-name" style="padding-left:${10 + indent * 20}px;">
                <span class="tree-node">${toggle}<span>${node.label}${gradeSuffix}</span></span>
            </td>
            ${groupCells}
        </tr>`;
    }

    function escapeAttr(s) {
        return String(s).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
    }

    async function fetchNodes(shapCode, path) {
        const params = new URLSearchParams({
            selection: currentSelection || '',
            path_json: JSON.stringify(path || []),
            search: treeFilters.search,
            job_level_group: treeFilters.job_level_group,
            position_grade: treeFilters.position_grade,
            gap_status: treeFilters.gap_status,
        });
        if (shapCode) params.set('shap', shapCode);
        const res = await fetch('/ftk_tree_api.php?' + params.toString());
        const json = await res.json();
        return json.success ? json.data.nodes : [];
    }

    async function renderTree() {
        const tbody = document.getElementById('tree-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="99" style="text-align:center; padding:16px;"><span class="spin"></span></td></tr>';
        const nodes = await fetchNodes(null, []);
        tbody.innerHTML = nodes.map(n => renderNodeRow(n, 0)).join('') || '<tr><td colspan="99" style="text-align:center; padding:16px; color:var(--ink-soft);">Tidak ada data.</td></tr>';
        attachTreeHandlers();
    }

    function resetTree() {
        const tbody = document.getElementById('tree-tbody');
        if (tbody) tbody.innerHTML = '';
    }

    // Single delegated listener bound once on the tbody (instead of re-binding a
    // listener on every .tree-toggle each time a branch expands), so repeated
    // expand/collapse never stacks duplicate handlers on old rows.
    let treeHandlersBound = false;
    function attachTreeHandlers() {
        if (treeHandlersBound) return;
        treeHandlersBound = true;

        document.getElementById('tree-tbody').addEventListener('click', async (e) => {
            const toggle = e.target.closest('.tree-toggle');
            if (!toggle) return;

            const row = toggle.closest('tr');
            const nodeData = JSON.parse(row.dataset.node.replace(/&quot;/g, '"').replace(/&#39;/g, "'"));
            const currentIndent = Math.round((parseInt(getComputedStyle(row.querySelector('.col-name')).paddingLeft) - 10) / 20);

            if (toggle.textContent === '+') {
                toggle.disabled = true;
                toggle.textContent = '…';
                const resolvedShap = row.dataset.shapCode || nodeData.shap;
                const children = await fetchNodes(resolvedShap, nodeData.path);
                toggle.disabled = false;
                toggle.textContent = '−';
                toggle.setAttribute('aria-expanded', 'true');
                const rowsHtml = children.map(c => renderNodeRow(Object.assign({}, c), currentIndent + 1)).join('');
                row.insertAdjacentHTML('afterend', rowsHtml);
                // propagate shap code to descendant rows for further expansion
                let sib = row.nextElementSibling;
                let count = children.length;
                while (sib && count > 0) {
                    sib.dataset.shapCode = resolvedShap;
                    sib = sib.nextElementSibling;
                    count--;
                }
            } else {
                toggle.textContent = '+';
                toggle.setAttribute('aria-expanded', 'false');
                removeDescendantRows(row, currentIndent);
            }
        });
    }

    function removeDescendantRows(row, indent) {
        let sib = row.nextElementSibling;
        while (sib) {
            const sibIndent = Math.round((parseInt(getComputedStyle(sib.querySelector('.col-name')).paddingLeft) - 10) / 20);
            if (sibIndent <= indent) break;
            const toRemove = sib;
            sib = sib.nextElementSibling;
            toRemove.remove();
        }
    }

    document.addEventListener('click', (e) => {
        if (e.target && e.target.id === 'btn-apply-tree-filter') {
            treeFilters.search = document.getElementById('tree-search').value.trim();
            treeFilters.job_level_group = document.getElementById('tree-filter-level').value;
            treeFilters.position_grade = document.getElementById('tree-filter-grade').value.trim();
            treeFilters.gap_status = document.getElementById('tree-filter-gap').value;
            renderTree();
        }
    });

    // ---------- Tabs ----------
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab).classList.add('active');
            if (btn.dataset.tab === 'tab-history') {
                loadUploadHistory();
            }
        });
    });

    // ---------- Histori Upload tab ----------
    async function loadUploadHistory(page = 1) {
        const wrap = document.getElementById('history-table-wrap');
        wrap.innerHTML = '<div class="empty-state"><span class="spin"></span></div>';
        const shapId = document.getElementById('history-shap-filter').value;
        const params = new URLSearchParams({ page });
        if (shapId) params.set('shap_id', shapId);
        const res = await fetch('/upload_history_api.php?' + params.toString());
        const json = await res.json();
        if (!json.success) { wrap.innerHTML = `<p>${json.message}</p>`; return; }
        const d = json.data;

        if (!d.rows.length) {
            wrap.innerHTML = '<p style="color:var(--ink-soft); text-align:center; padding:24px;">Belum ada histori upload.</p>';
            return;
        }

        const rows = d.rows.map(r => `
            <tr>
                <td>${r.shap_short_name}</td>
                <td>${formatPeriodLabel(r.period_month)}</td>
                <td>Rev ${r.revision_no ?? '-'}</td>
                <td>${r.original_filename}</td>
                <td>${fmt(r.total_rows)}</td>
                <td>${fmt(r.total_ftk)}</td>
                <td>${fmt(r.total_realisasi)}</td>
                <td><span class="status-pill status-${r.status}">${r.status}</span></td>
                <td>${r.uploaded_by_name}</td>
                <td>${new Date(r.created_at).toLocaleString('id-ID')}</td>
                <td>${r.is_active ? '<span class="active-yes">Ya</span>' : '<span class="active-no">Tidak</span>'}</td>
            </tr>`).join('');

        let pager = '<div class="pagination">';
        for (let p = 1; p <= d.total_pages; p++) {
            pager += `<button class="${p === d.page ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }
        pager += '</div>';

        wrap.innerHTML = `
            <table class="history-table">
                <thead><tr>
                    <th>SH/AP</th><th>Periode</th><th>Revisi</th><th>File</th><th>Total Row</th>
                    <th>Total FTK</th><th>Total Realisasi</th><th>Status</th><th>Uploaded By</th><th>Uploaded At</th><th>Aktif</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>
            ${pager}
        `;

        wrap.querySelectorAll('.pagination button').forEach(b => {
            b.addEventListener('click', () => loadUploadHistory(parseInt(b.dataset.page)));
        });
    }

    function formatPeriodLabel(ymd) {
        const months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        const [y, m] = ymd.split('-');
        return `${months[parseInt(m)]} ${y}`;
    }

    document.getElementById('history-shap-filter').addEventListener('change', () => loadUploadHistory(1));

    // ---------- Upload modal ----------
    const modalUpload = document.getElementById('modal-upload');
    let uploadToken = null;

    function openUploadModal() {
        resetUploadModal();
        modalUpload.classList.add('open');
    }
    function closeUploadModal() {
        modalUpload.classList.remove('open');
    }
    function resetUploadModal() {
        goToStep(1);
        document.getElementById('form-upload').reset();
        document.getElementById('dropzone-text').textContent = 'Klik atau seret file .xlsx ke sini';
        uploadToken = null;
    }

    function goToStep(step) {
        [1, 2, 3].forEach(i => {
            document.getElementById('upload-step-' + i).style.display = i === step ? '' : 'none';
            document.querySelector(`.step-dot[data-step="${i}"]`).classList.toggle('active', i <= step);
        });
        document.getElementById('btn-next-upload').style.display = step === 1 ? '' : 'none';
        document.getElementById('btn-back-upload').style.display = step === 2 ? '' : 'none';
        document.getElementById('btn-confirm-upload').style.display = step === 2 ? '' : 'none';
    }

    document.getElementById('btn-open-upload').addEventListener('click', openUploadModal);
    modalUpload.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', closeUploadModal));

    // Escape closes whichever modal is currently open — keyboard-only users
    // must be able to dismiss it without reaching for the mouse.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalUpload.classList.contains('open')) {
            closeUploadModal();
        }
    });

    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('up-file');
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('drag'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('drag');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            document.getElementById('dropzone-text').textContent = fileInput.files[0].name;
        }
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) document.getElementById('dropzone-text').textContent = fileInput.files[0].name;
    });

    document.getElementById('btn-next-upload').addEventListener('click', async () => {
        const form = document.getElementById('form-upload');
        if (!form.reportValidity()) return;
        if (!fileInput.files.length) { alert('File wajib diunggah.'); return; }

        const btn = document.getElementById('btn-next-upload');
        btn.disabled = true;
        btn.textContent = 'Memvalidasi…';

        const fd = new FormData();
        fd.append('shap_id', document.getElementById('up-shap').value);
        fd.append('period', document.getElementById('up-period').value);
        fd.append('file', fileInput.files[0]);
        fd.append('_csrf', CSRF_TOKEN);

        const res = await fetch('/upload_submit.php?action=preview', { method: 'POST', body: fd });
        const json = await res.json();

        btn.disabled = false;
        btn.textContent = 'Validasi & Preview';

        if (!json.success) { alert(json.message); return; }

        uploadToken = json.data.token;
        renderPreview(json.data);
        goToStep(2);
    });

    function renderPreview(d) {
        const s = d.summary;
        let html = `
            <div class="preview-summary">
                <div class="preview-stat"><div class="n">${fmt(s.total_rows)}</div><div class="l">Total Rows</div></div>
                <div class="preview-stat"><div class="n">${fmt(s.valid_rows)}</div><div class="l">Valid Rows</div></div>
                <div class="preview-stat"><div class="n">${fmt(d.warning_count)}</div><div class="l">Warning</div></div>
                <div class="preview-stat"><div class="n">${fmt(d.error_count)}</div><div class="l">Error</div></div>
            </div>
            <p style="font-size:12.5px; color:var(--ink-soft);">Total FTK: <b>${fmt(s.total_ftk)}</b> · Total Realisasi: <b>${fmt(s.total_realisasi)}</b></p>
        `;

        if (d.errors.length) {
            html += `<h4 style="margin:14px 0 6px;">Error (${d.error_count})</h4><div class="err-list">` +
                d.errors.map(e => `<div class="err-item ERROR">Baris ${e.row_number} — ${e.column_name}: ${e.message}</div>`).join('') +
                '</div>';
        }
        if (d.warnings.length) {
            html += `<h4 style="margin:14px 0 6px;">Warning (${d.warning_count})</h4><div class="err-list">` +
                d.warnings.map(e => `<div class="err-item WARNING">Baris ${e.row_number} — ${e.column_name || ''}: ${e.message}</div>`).join('') +
                '</div>';
        }

        html += `<h4 style="margin:14px 0 6px;">Contoh Data (${Math.min(d.sample_rows.length, 20)} baris pertama)</h4>
            <div style="overflow:auto; max-height:220px; border:1px solid var(--border); border-radius:8px;">
            <table class="preview-table">
                <thead><tr><th>Jabatan</th><th>Jenjang</th><th>UI/UP/UL</th><th>PoG</th><th>FTK</th><th>Realisasi</th><th>Sisa</th></tr></thead>
                <tbody>${d.sample_rows.map(r => `<tr>
                    <td>${r.position_name}</td>
                    <td>${r.job_level_group || '-'}</td>
                    <td>${r.organization_level_2 || '-'}</td>
                    <td>${r.position_grade || '-'}</td>
                    <td style="text-align:right;">${fmt(r.ftk)}</td>
                    <td style="text-align:right;">${fmt(r.total_realisasi)}</td>
                    <td style="text-align:right;">${fmt(r.sisa_delta)}</td>
                </tr>`).join('')}</tbody>
            </table></div>`;

        document.getElementById('preview-content').innerHTML = html;
        document.getElementById('btn-confirm-upload').disabled = !d.can_confirm;
        document.getElementById('btn-confirm-upload').textContent = d.can_confirm ? 'Konfirmasi Import' : 'Tidak dapat diimpor (ada error)';
    }

    document.getElementById('btn-back-upload').addEventListener('click', () => goToStep(1));

    document.getElementById('btn-confirm-upload').addEventListener('click', async () => {
        const btn = document.getElementById('btn-confirm-upload');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';

        const fd = new FormData();
        fd.append('token', uploadToken);
        fd.append('note', document.getElementById('up-note').value);
        fd.append('_csrf', CSRF_TOKEN);

        const res = await fetch('/upload_submit.php?action=confirm', { method: 'POST', body: fd });
        const json = await res.json();

        if (!json.success) {
            alert(json.message);
            btn.disabled = false;
            btn.textContent = 'Konfirmasi Import';
            return;
        }

        document.getElementById('confirm-result').innerHTML = `<h3>Berhasil!</h3><p>${json.message}</p>`;
        goToStep(3);
        document.getElementById('btn-confirm-upload').style.display = 'none';
        document.getElementById('btn-back-upload').style.display = 'none';

        setTimeout(async () => {
            closeUploadModal();
            await loadHistoryOptions();
            await loadDashboard();
        }, 1200);
    });

    // ---------- Init ----------
    (async function init() {
        await loadHistoryOptions();
        await loadDashboard();
    })();
})();
