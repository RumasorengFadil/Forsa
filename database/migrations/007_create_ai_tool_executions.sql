-- FORSA AI Assistant — tool execution log (PRD_FORSA_AI_Assistant.md §45.3).
-- Populated starting Phase 6, when query_forsa actually runs for the first
-- time. Table name follows the forsa_* convention like migration 006.
SET search_path TO forsa, public;

CREATE TABLE IF NOT EXISTS forsa_ai_tool_executions (
    id BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NULL REFERENCES forsa_ai_conversations(id) ON DELETE CASCADE,
    message_id BIGINT NULL REFERENCES forsa_ai_messages(id) ON DELETE SET NULL,
    tool_name VARCHAR(100) NOT NULL,
    arguments_json JSONB NULL,
    semantic_query_json JSONB NULL,
    result_summary_json JSONB NULL,
    execution_time_ms INTEGER NULL,
    status VARCHAR(30) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_forsa_ai_tool_executions_conversation ON forsa_ai_tool_executions (conversation_id, created_at);
