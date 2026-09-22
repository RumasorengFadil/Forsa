(function () {
    const fmt = (n) => (n ?? 0).toLocaleString('id-ID');
    const fmtPct = (n) => (n ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';

    let currentSelection = null;

    // Cache of the last successful dashboard_api / ftk_tree_api (root level)
    // responses, reused by the Tahap 3 drilldown modals so they never
    // recompute anything — they only slice/format data already fetched for
    // the dashboard and the tree.
    let lastDashboardData = null;
    let rootTreeNodes = [];

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
        lastDashboardData = data;

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
        populateTreeLevelFilterOptions(treeGroups);
        syncTreeHeaderStickyOffset();
        bindDashboardDrilldownClicks();
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
            <div class="shap-card clickable" data-shap="${s.shap_code}" role="button" tabindex="0" aria-label="Lihat detail ${s.shap_name}">
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
            <div class="bar-row clickable" data-shap="${g.shap_code}" role="button" tabindex="0" aria-label="Lihat detail ${g.shap_name}">
                <span class="bar-label">${g.shap_name}</span>
                <div class="bar-track"><div class="bar-fill" style="width:${g.bar_pct}%"></div></div>
                <span class="bar-value">${g.gap}</span>
            </div>`).join('');

        const jenjangRows = data.per_jenjang.map(j => {
            const hasData = j.ftk > 0 || j.realisasi > 0;
            const cls = hasData ? 'stack-bar-row clickable' : 'stack-bar-row';
            const attrs = hasData ? `data-group="${j.group}" role="button" tabindex="0" aria-label="Lihat detail jenjang ${j.label}"` : '';
            return `
            <div class="${cls}" ${attrs}>
                <span class="bar-label">${j.label}</span>
                <div class="stack-track">
                    <div class="stack-fill-ok" style="width:${j.bar_ok_pct}%"></div>
                    <div class="stack-fill-gap" style="width:${j.bar_gap_pct}%"></div>
                </div>
                <span class="stack-values">${fmt(j.ftk)} &nbsp; ${fmt(j.belum_dipenuhi)}</span>
            </div>`;
        }).join('');

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
                    ${renderTreeHeader(data.job_level_order)}
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

    const GROUP_LABELS = { 'gen 1-3': 'GEN 1-3', MD: 'MD', MM: 'MM', MA: 'MA', spesialist: 'SPECIALIST', 'Senior Specialist': 'SR. SPECIALIST', 'Junior Expert': 'JR. EXPERT', Expert: 'EXPERT', 'Senior Expert': 'SR. EXPERT' };

    function renderTreeHeader(groups) {
        treeGroups = groups;
        const metricCols = ['FTK', 'Organik', 'Tugas Karya', 'Pihak Ketiga', 'Total Real.', 'Sisa'];

        // The name column header is split into two independent single-row
        // sticky cells (one per header row) instead of one rowspan=2 cell.
        // A rowspan cell that must stick on BOTH axes at once (top *and*
        // left) renders at the wrong stacking order in Chrome/Safari once
        // the table is scrolled horizontally — its z-index is not reliably
        // respected against sibling header cells in that state. Two plain
        // cells with matching background/borders look identical but don't
        // hit that bug, since each is only ever sticky the same way its row
        // already is.
        const groupHeaderRow = ['<th class="col-name">UNIT / ORGANISASI / JABATAN</th>']
            .concat(['TOTAL', ...groups].map(g => `<th colspan="6">${g === 'TOTAL' ? 'TOTAL' : (GROUP_LABELS[g] || g)}</th>`))
            .join('');

        const subHeaderRow = ['<th class="col-name"></th>']
            .concat(['TOTAL', ...groups].map(() => metricCols.map(m => `<th>${m}</th>`).join('')))
            .join('');

        return `<thead>
            <tr class="group-row">${groupHeaderRow}</tr>
            <tr>${subHeaderRow}</tr>
        </thead>`;
    }

    // renderTreeHeader() above only builds a string — it runs *before* that
    // string is assigned to the DOM (it's called inside renderDashboard()'s
    // template literal), so it can never safely touch #tree-filter-level
    // itself (that element doesn't exist yet, or is the about-to-be-replaced
    // previous one). Populate the <select> here instead, once the new HTML
    // is actually in the document.
    function populateTreeLevelFilterOptions(groups) {
        const levelFilter = document.getElementById('tree-filter-level');
        if (!levelFilter) return;
        levelFilter.innerHTML = '<option value="">Semua Jenjang</option>' +
            groups.map(g => `<option value="${g}">${GROUP_LABELS[g] || g}</option>`).join('');
    }

    // Measures the *actual* rendered height of the jenjang group-row (the
    // first sticky header layer) and exposes it as a CSS variable so the
    // metric sub-header row underneath (the second sticky layer, Tahap 5)
    // can stick exactly below it instead of overlapping — see the CSS
    // comment above .tree-table thead th for the full picture.
    function syncTreeHeaderStickyOffset() {
        const table = document.getElementById('tree-table');
        const groupRow = table ? table.querySelector('thead tr.group-row') : null;
        if (!table || !groupRow) return;
        table.style.setProperty('--tree-header1-h', groupRow.getBoundingClientRect().height + 'px');
    }

    let stickyResizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(stickyResizeTimer);
        stickyResizeTimer = setTimeout(syncTreeHeaderStickyOffset, 150);
    });

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
        rootTreeNodes = nodes;
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

    // ---------- Detail Drilldown Modal (Tahap 3) ----------
    // Every number rendered here is read from lastDashboardData (the exact
    // dashboard_api response already on screen) or rootTreeNodes (the exact
    // ftk_tree_api root-level response already backing the tree table) — no
    // new aggregation, no new endpoint, so the modal can never disagree with
    // the KPI/chart the user clicked.
    const modalDetail = document.getElementById('modal-detail');

    function openDetailModal(title, bodyHtml, footerHtml) {
        document.getElementById('detail-modal-title').textContent = title;
        document.getElementById('detail-modal-body').innerHTML = bodyHtml;
        document.getElementById('detail-modal-footer').innerHTML = footerHtml;
        modalDetail.classList.add('open');
    }

    function closeDetailModal() {
        modalDetail.classList.remove('open');
    }

    // Delegated so header close button + dynamically injected footer buttons
    // are handled by one listener bound once (same pattern as the tree's
    // single delegated toggle listener — avoids re-binding duplicates).
    modalDetail.addEventListener('click', (e) => {
        if (e.target.closest('[data-close]')) closeDetailModal();
    });

    function jenjangLabel(group) {
        const entry = (lastDashboardData?.per_jenjang || []).find(j => j.group === group);
        return entry ? entry.label : group;
    }

    function detailStatRow(items) {
        return `<div class="preview-summary">${items.map(i => `
            <div class="preview-stat"><div class="n ${i.cls || ''}">${i.value}</div><div class="l">${i.label}</div></div>
        `).join('')}</div>`;
    }

    function findTreeRowByKey(key) {
        return Array.from(document.querySelectorAll('#tree-tbody tr')).find(r => r.dataset.key === key);
    }

    function scrollToTreeAndExpandShap(shapCode) {
        const treeTable = document.getElementById('tree-table');
        if (!treeTable) return;
        treeTable.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const row = findTreeRowByKey('shap:' + shapCode);
        if (!row) return;
        const toggle = row.querySelector('.tree-toggle');
        if (toggle && toggle.getAttribute('aria-expanded') === 'false') {
            toggle.click();
        }
        row.classList.remove('row-flash');
        void row.offsetWidth; // force reflow so the animation restarts if it already played once
        row.classList.add('row-flash');
    }

    function applyJenjangFilterAndScroll(group) {
        const select = document.getElementById('tree-filter-level');
        if (select) select.value = group;
        treeFilters.job_level_group = group;
        renderTree();
        document.getElementById('tree-table').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function openShapDetail(shapCode) {
        if (!lastDashboardData) return;
        const shap = lastDashboardData.per_shap.find(s => s.shap_code === shapCode);
        if (!shap) return;
        const node = rootTreeNodes.find(n => n.key === 'shap:' + shapCode);
        const m = node ? node.metrics.total : null;

        const stats = detailStatRow([
            { value: fmt(shap.ftk), label: 'FTK' },
            { value: fmt(shap.realisasi), label: 'Realisasi' },
            { value: fmtPct(shap.pct), label: 'Pemenuhan', cls: pctClass(shap.status_code) },
            { value: (shap.gap >= 0 ? '+' : '') + fmt(shap.gap), label: 'Gap', cls: gapClass(shap.gap) },
        ]);

        let breakdownHtml = '';
        if (m) {
            breakdownHtml = `
                <table class="history-table">
                    <thead><tr><th>Rincian Realisasi</th><th style="text-align:right;">Jumlah</th></tr></thead>
                    <tbody>
                        <tr><td>Organik</td><td style="text-align:right;">${fmt(m.realisasi_organik)}</td></tr>
                        <tr><td>Tugas Karya</td><td style="text-align:right;">${fmt(m.realisasi_tugas_karya)}</td></tr>
                        <tr><td>Pihak Ketiga</td><td style="text-align:right;">${fmt(m.realisasi_pihak_ketiga)}</td></tr>
                        <tr><td><b>Total Realisasi</b></td><td style="text-align:right;"><b>${fmt(m.total_realisasi)}</b></td></tr>
                        <tr><td>Sisa / Delta</td><td style="text-align:right;">${fmt(m.sisa_delta)}</td></tr>
                    </tbody>
                </table>`;
        }

        let jenjangHtml = '';
        if (node) {
            const rows = treeGroups.map(g => {
                const gm = node.metrics.groups[g] || { ftk: 0, total_realisasi: 0, sisa_delta: 0 };
                if (gm.ftk === 0 && gm.total_realisasi === 0) return '';
                return `<tr><td>${jenjangLabel(g)}</td><td style="text-align:right;">${fmt(gm.ftk)}</td><td style="text-align:right;">${fmt(gm.total_realisasi)}</td><td style="text-align:right;">${fmt(gm.sisa_delta)}</td></tr>`;
            }).join('');
            jenjangHtml = `
                <table class="history-table" style="margin-top:16px;">
                    <thead><tr><th>Jenjang</th><th style="text-align:right;">FTK</th><th style="text-align:right;">Realisasi</th><th style="text-align:right;">Sisa</th></tr></thead>
                    <tbody>${rows || '<tr><td colspan="4" style="text-align:center; color:var(--ink-soft);">Tidak ada rincian jenjang.</td></tr>'}</tbody>
                </table>`;
        }

        const footer = `
            <button class="btn btn-secondary" data-close>Tutup</button>
            <button class="btn btn-primary" id="btn-goto-tree">Lihat di Drill-down Tree</button>`;

        openDetailModal(shap.shap_name, stats + breakdownHtml + jenjangHtml, footer);

        document.getElementById('btn-goto-tree').addEventListener('click', () => {
            closeDetailModal();
            scrollToTreeAndExpandShap(shapCode);
        });
    }

    function openJenjangDetail(group) {
        if (!lastDashboardData) return;
        const entry = lastDashboardData.per_jenjang.find(j => j.group === group);
        if (!entry) return;

        const stats = detailStatRow([
            { value: fmt(entry.ftk), label: 'FTK' },
            { value: fmt(entry.realisasi), label: 'Realisasi' },
            { value: fmt(entry.belum_dipenuhi), label: 'Belum Dipenuhi', cls: 'pct-red' },
            { value: fmt(entry.lebih), label: 'Kelebihan', cls: 'pct-green' },
        ]);

        const rows = rootTreeNodes
            .map(node => {
                const gm = node.metrics.groups[group] || { ftk: 0, total_realisasi: 0, sisa_delta: 0 };
                if (gm.ftk === 0 && gm.total_realisasi === 0) return null;
                return { label: node.label, ...gm };
            })
            .filter(Boolean)
            .sort((a, b) => b.sisa_delta - a.sisa_delta);

        const tableHtml = `
            <table class="history-table" style="margin-top:16px;">
                <thead><tr><th>SH/AP</th><th style="text-align:right;">FTK</th><th style="text-align:right;">Realisasi</th><th style="text-align:right;">Sisa</th></tr></thead>
                <tbody>${rows.length ? rows.map(r => `<tr><td>${r.label}</td><td style="text-align:right;">${fmt(r.ftk)}</td><td style="text-align:right;">${fmt(r.total_realisasi)}</td><td style="text-align:right;">${fmt(r.sisa_delta)}</td></tr>`).join('') : '<tr><td colspan="4" style="text-align:center; color:var(--ink-soft);">Tidak ada SH/AP dengan jenjang ini.</td></tr>'}</tbody>
            </table>`;

        const footer = `
            <button class="btn btn-secondary" data-close>Tutup</button>
            <button class="btn btn-primary" id="btn-filter-tree">Filter Drill-down Berdasarkan Jenjang Ini</button>`;

        openDetailModal(entry.label, stats + tableHtml, footer);

        document.getElementById('btn-filter-tree').addEventListener('click', () => {
            closeDetailModal();
            applyJenjangFilterAndScroll(group);
        });
    }

    // Delegated + bound once on the stable #dashboard-content container, so
    // re-rendering the dashboard on selection change never stacks duplicate
    // listeners (same reasoning as the tree's single delegated listener).
    let dashboardClickBound = false;
    function bindDashboardDrilldownClicks() {
        if (dashboardClickBound) return;
        dashboardClickBound = true;
        const content = document.getElementById('dashboard-content');

        const activate = (target) => {
            if (target.dataset.shap) openShapDetail(target.dataset.shap);
            else if (target.dataset.group) openJenjangDetail(target.dataset.group);
        };

        content.addEventListener('click', (e) => {
            const target = e.target.closest('[role="button"][data-shap], [role="button"][data-group]');
            if (target) activate(target);
        });
        content.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const target = e.target.closest('[role="button"][data-shap], [role="button"][data-group]');
            if (!target) return;
            e.preventDefault();
            activate(target);
        });
    }

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
        if (e.key !== 'Escape') return;
        if (modalUpload.classList.contains('open')) closeUploadModal();
        if (modalDetail.classList.contains('open')) closeDetailModal();
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

    // ---------- Guided Tour (adapted from ORBIT GeoMutasi's admin.js Tour pattern:
    // same spotlight/popover mechanism, kept self-contained here since only the
    // dashboard page needs it) ----------
    const Tour = (() => {
        let steps = [];
        let index = 0;
        let overlay = null;
        let placeToken = 0;
        let resizeTimer = null;

        function build() {
            overlay = document.createElement('div');
            overlay.className = 'tour-overlay';
            overlay.hidden = true;
            overlay.innerHTML = `
                <div class="tour-dim"></div>
                <div class="tour-spotlight-ring"></div>
                <div class="tour-popover" role="dialog" aria-modal="true" aria-labelledby="tour-title">
                    <button type="button" class="tour-close" aria-label="Tutup panduan">&times;</button>
                    <span class="tour-popover__step"></span>
                    <h4 id="tour-title"></h4>
                    <p></p>
                    <div class="tour-popover__actions">
                        <button type="button" class="tour-popover__skip">Lewati</button>
                        <div class="tour-popover__nav">
                            <button type="button" class="btn btn-secondary" data-tour-back>Kembali</button>
                            <button type="button" class="btn btn-primary" data-tour-next>Lanjut</button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            overlay.querySelector('.tour-close').addEventListener('click', stop);
            overlay.querySelector('.tour-popover__skip').addEventListener('click', stop);
            overlay.querySelector('[data-tour-back]').addEventListener('click', back);
            overlay.querySelector('[data-tour-next]').addEventListener('click', next);
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !overlay.hidden) stop(); });
            window.addEventListener('resize', onViewportChange);
        }

        function onViewportChange() {
            if (!overlay || overlay.hidden) return;
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => place(), 150);
        }

        function isWellInView(rect, margin) {
            return rect.top >= margin && rect.left >= margin &&
                rect.bottom <= (window.innerHeight - margin) &&
                rect.right <= (window.innerWidth - margin);
        }

        function waitUntilSettled(target, token, onSettled) {
            const startedAt = Date.now();
            const maxWaitMs = 1200;
            const pollMs = 40;
            let lastRect = target.getBoundingClientRect();
            let stableTicks = 0;

            function tick() {
                if (token !== placeToken) return;
                const rect = target.getBoundingClientRect();
                const moved = Math.abs(rect.top - lastRect.top) > 0.5 || Math.abs(rect.left - lastRect.left) > 0.5;
                lastRect = rect;
                stableTicks = moved ? 0 : stableTicks + 1;
                if (stableTicks >= 3 || Date.now() - startedAt >= maxWaitMs) {
                    onSettled(rect);
                    return;
                }
                setTimeout(tick, pollMs);
            }
            setTimeout(tick, pollMs);
        }

        function renderAt(rect) {
            const ring = overlay.querySelector('.tour-spotlight-ring');
            const pop = overlay.querySelector('.tour-popover');
            const pad = 8;
            ring.style.top = (rect.top - pad) + 'px';
            ring.style.left = (rect.left - pad) + 'px';
            ring.style.width = (rect.width + pad * 2) + 'px';
            ring.style.height = (rect.height + pad * 2) + 'px';

            const popW = 320;
            let top = rect.bottom + 16;
            let left = Math.min(Math.max(8, rect.left), window.innerWidth - popW - 8);
            if (top + 200 > window.innerHeight) top = Math.max(8, rect.top - 16 - 200);
            pop.style.top = top + 'px';
            pop.style.left = left + 'px';
            overlay.classList.remove('is-positioning');
        }

        function place() {
            const step = steps[index];
            const target = step.selector ? document.querySelector(step.selector) : null;
            const pop = overlay.querySelector('.tour-popover');

            pop.querySelector('.tour-popover__step').textContent = `Langkah ${index + 1} dari ${steps.length}`;
            pop.querySelector('h4').textContent = step.title;
            pop.querySelector('p').textContent = step.description;
            pop.querySelector('[data-tour-back]').style.visibility = index === 0 ? 'hidden' : 'visible';
            pop.querySelector('[data-tour-next]').textContent = index === steps.length - 1 ? 'Selesai' : 'Lanjut';

            const token = ++placeToken;

            if (!target) {
                renderAt({ top: window.innerHeight / 2 - 10, left: window.innerWidth / 2 - 10, width: 20, height: 20, bottom: window.innerHeight / 2 + 10, right: window.innerWidth / 2 + 10 });
                return;
            }

            const currentRect = target.getBoundingClientRect();
            if (isWellInView(currentRect, 24)) {
                renderAt(currentRect);
                return;
            }

            overlay.classList.add('is-positioning');
            target.scrollIntoView({ block: 'center', behavior: 'auto' });
            waitUntilSettled(target, token, (rect) => {
                if (token !== placeToken) return;
                renderAt(rect);
            });
        }

        function next() { if (index < steps.length - 1) { index++; place(); } else { stop(); } }
        function back() { if (index > 0) { index--; place(); } }
        function stop() { if (overlay) overlay.hidden = true; }
        function start(newSteps) {
            if (!newSteps || newSteps.length === 0) return;
            if (!overlay) build();
            steps = newSteps.filter((s) => !s.selector || document.querySelector(s.selector));
            if (steps.length === 0) return;
            index = 0;
            overlay.hidden = false;
            place();
        }
        return { start, stop };
    })();

    const DASHBOARD_TOUR_STEPS = [
        { selector: '#select-history', title: 'Pilih Periode / Histori', description: 'Pilih periode Realisasi SH/AP terbaru, atau salah satu snapshot histori untuk melihat data pada tanggal upload tertentu (mode inspeksi).' },
        { selector: '#btn-open-upload', title: 'Upload Realisasi SH/AP', description: 'Unggah template FTK & realisasi terbaru untuk satu SH/AP. Setiap upload tersimpan sebagai snapshot baru tanpa menghapus data lama.' },
        { selector: '.tabs', title: 'Dashboard & Histori Upload', description: 'Beralih antara tampilan Dashboard dan daftar seluruh Histori Upload, tanpa berpindah halaman.' },
        { selector: '.kpi-row', title: 'KPI Utama', description: 'Ringkasan Total FTK, Total Realisasi, Pemenuhan, dan Gap untuk periode/snapshot yang sedang dipilih.' },
        { selector: '.shap-grid', title: 'Sebaran per SH/AP', description: 'Persentase pemenuhan dan gap tiap SH/AP untuk periode/snapshot terpilih.' },
        { selector: '.priority-card', title: 'Status Prioritas Pemenuhan', description: 'Pengelompokan SH/AP berdasarkan ambang batas pemenuhan: ≥100%, 90–99,9%, dan <90%.' },
        { selector: '.tree-toolbar', title: 'Pencarian & Filter Drill-down', description: 'Cari organisasi/jabatan, atau filter berdasarkan jenjang jabatan, Position Grade, dan status gap.' },
        { selector: '#tree-table', title: 'Drill-down FTK', description: 'Klik ikon "+" untuk memperluas struktur organisasi sampai ke jabatan, lengkap dengan rincian per jenjang.' },
    ];

    document.getElementById('btn-dashboard-guide').addEventListener('click', () => Tour.start(DASHBOARD_TOUR_STEPS));

    // ---------- Init ----------
    (async function init() {
        await loadHistoryOptions();
        await loadDashboard();
    })();
})();
