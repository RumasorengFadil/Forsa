-- Fills a gap against PRD_FORSA_AI_Assistant.md §17's audit log field list
-- (user_id) that migration 007 initially omitted from forsa_ai_tool_executions.
SET search_path TO forsa, public;

ALTER TABLE forsa_ai_tool_executions
    ADD COLUMN IF NOT EXISTS user_id BIGINT NULL REFERENCES forsa_users(id);

CREATE INDEX IF NOT EXISTS idx_forsa_ai_tool_executions_user ON forsa_ai_tool_executions (user_id, created_at);
