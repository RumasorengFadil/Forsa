-- FORSA AI Assistant — conversation storage (docs/ai-assistant/PRD.md §18/§45).
-- Table names follow the existing forsa_* convention (PRD's own example DDL
-- uses bare "ai_conversations"/"ai_messages"; adapted here to match every
-- other table in this schema, per PRD §41 "mengikuti existing FORSA").
SET search_path TO forsa, public;

CREATE TABLE IF NOT EXISTS forsa_ai_conversations (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES forsa_users(id),
    title VARCHAR(255) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_forsa_ai_conversations_user ON forsa_ai_conversations (user_id, updated_at DESC);

-- role: 'user' | 'assistant' | 'tool' | 'system' (PRD §18.2). tool_call_id
-- stays NULL until Phase 6 (query_forsa tool calling) actually populates it.
CREATE TABLE IF NOT EXISTS forsa_ai_messages (
    id BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NOT NULL REFERENCES forsa_ai_conversations(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL,
    content TEXT NULL,
    tool_call_id VARCHAR(255) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_forsa_ai_messages_conversation ON forsa_ai_messages (conversation_id, created_at);
