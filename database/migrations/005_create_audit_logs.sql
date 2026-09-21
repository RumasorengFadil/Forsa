SET search_path TO forsa, public;

CREATE TABLE IF NOT EXISTS forsa_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NULL REFERENCES forsa_users(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id TEXT NULL,
    old_data JSONB NULL,
    new_data JSONB NULL,
    ip_address INET NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_forsa_audit_logs_user ON forsa_audit_logs (user_id, created_at DESC);
