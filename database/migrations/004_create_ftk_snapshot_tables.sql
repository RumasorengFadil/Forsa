SET search_path TO forsa, public;

CREATE TABLE IF NOT EXISTS forsa_ftk_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    shap_id BIGINT NOT NULL REFERENCES forsa_shap_entities(id),
    import_job_id UUID NOT NULL REFERENCES forsa_import_jobs(id),
    period_month DATE NOT NULL,
    revision_no INT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT FALSE,
    total_ftk BIGINT NOT NULL DEFAULT 0,
    total_realisasi_organik BIGINT NOT NULL DEFAULT 0,
    total_realisasi_tugas_karya BIGINT NOT NULL DEFAULT 0,
    total_realisasi_pihak_ketiga BIGINT NOT NULL DEFAULT 0,
    total_realisasi BIGINT NOT NULL DEFAULT 0,
    created_by BIGINT NOT NULL REFERENCES forsa_users(id),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_forsa_ftk_snapshots_rev UNIQUE (shap_id, period_month, revision_no)
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_forsa_ftk_snapshots_active
    ON forsa_ftk_snapshots (shap_id, period_month)
    WHERE is_active = TRUE;

CREATE INDEX IF NOT EXISTS idx_forsa_ftk_snapshots_period ON forsa_ftk_snapshots (period_month, is_active);

CREATE TABLE IF NOT EXISTS forsa_ftk_snapshot_rows (
    id BIGSERIAL PRIMARY KEY,
    snapshot_id UUID NOT NULL REFERENCES forsa_ftk_snapshots(id) ON DELETE CASCADE,
    source_row_no INT NULL,
    position_name TEXT NOT NULL,
    job_level_raw VARCHAR(150) NULL,
    job_level_group VARCHAR(100) NULL,
    organization_level_2 VARCHAR(255) NULL,
    organization_level_3 VARCHAR(255) NULL,
    organization_level_4 VARCHAR(255) NULL,
    position_grade VARCHAR(100) NULL,
    ftk INT NOT NULL DEFAULT 0,
    realisasi_organik INT NOT NULL DEFAULT 0,
    realisasi_tugas_karya INT NOT NULL DEFAULT 0,
    realisasi_pihak_ketiga INT NOT NULL DEFAULT 0,
    total_realisasi INT NOT NULL DEFAULT 0,
    sisa_delta INT NOT NULL DEFAULT 0,
    rencana_pemenuhan TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_snapshot_rows_snapshot ON forsa_ftk_snapshot_rows (snapshot_id);
CREATE INDEX IF NOT EXISTS idx_snapshot_rows_l2 ON forsa_ftk_snapshot_rows (snapshot_id, organization_level_2);
CREATE INDEX IF NOT EXISTS idx_snapshot_rows_l3 ON forsa_ftk_snapshot_rows (snapshot_id, organization_level_2, organization_level_3);
CREATE INDEX IF NOT EXISTS idx_snapshot_rows_l4 ON forsa_ftk_snapshot_rows (snapshot_id, organization_level_2, organization_level_3, organization_level_4);
CREATE INDEX IF NOT EXISTS idx_snapshot_rows_level_group ON forsa_ftk_snapshot_rows (snapshot_id, job_level_group);
CREATE INDEX IF NOT EXISTS idx_snapshot_rows_grade ON forsa_ftk_snapshot_rows (snapshot_id, position_grade);
