SET search_path TO forsa, public;

CREATE TABLE IF NOT EXISTS forsa_import_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    shap_id BIGINT NOT NULL REFERENCES forsa_shap_entities(id),
    period_month DATE NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    stored_path TEXT NOT NULL,
    file_hash VARCHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'UPLOADED',
    total_rows INT NOT NULL DEFAULT 0,
    valid_rows INT NOT NULL DEFAULT 0,
    warning_rows INT NOT NULL DEFAULT 0,
    error_rows INT NOT NULL DEFAULT 0,
    note TEXT NULL,
    uploaded_by BIGINT NOT NULL REFERENCES forsa_users(id),
    started_at TIMESTAMPTZ NULL,
    completed_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_forsa_import_jobs_shap_period ON forsa_import_jobs (shap_id, period_month);

CREATE TABLE IF NOT EXISTS forsa_import_errors (
    id BIGSERIAL PRIMARY KEY,
    import_job_id UUID NOT NULL REFERENCES forsa_import_jobs(id) ON DELETE CASCADE,
    row_number INT NOT NULL,
    column_name VARCHAR(150) NULL,
    severity VARCHAR(20) NOT NULL,
    error_code VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    raw_value TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_forsa_import_errors_job ON forsa_import_errors (import_job_id);
