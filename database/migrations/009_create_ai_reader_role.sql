-- FORSA AI Assistant — dedicated read-only Postgres role for the AI
-- Analytics Service (PRD_FORSA_AI_Assistant.md §4.3/§15.1). Grants SELECT
-- ONLY on the three tables approved as AI data sources (Phase 3 gate) —
-- nothing else in the schema, and no INSERT/UPDATE/DELETE/DDL anywhere.
--
-- NOT run via the generic "for f in migrations/*.sql" loop: it needs a
-- password supplied at runtime, never hardcoded in a versioned file. Run it
-- explicitly with the password as a psql variable, e.g.:
--   psql -h 127.0.0.1 -p 5432 -U <admin> -d forsa \
--     -v ai_reader_password="'<paste-a-strong-password>'" \
--     -f database/migrations/009_create_ai_reader_role.sql
-- Then put the SAME password in .env as AI_DB_PASSWORD (never in git).
--
-- Idempotent: creates the role only if missing, otherwise just updates its
-- password; GRANTs below are naturally idempotent (re-granting an already-
-- held privilege is a no-op, not an error).
--
-- psql's `:'var'` substitution is plain text substitution and does NOT
-- reach inside a dollar-quoted ($$...$$) PL/pgSQL body, so the password is
-- first staged through a transaction-local GUC (set_config) at the top
-- level, where `:'var'` substitution does apply, and read back inside the
-- DO block via current_setting() instead.
SELECT set_config('ai_migration.reader_password', :'ai_reader_password', false);

DO $$
DECLARE
    pw text := current_setting('ai_migration.reader_password');
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'forsa_ai_reader') THEN
        EXECUTE format('CREATE ROLE forsa_ai_reader LOGIN PASSWORD %L NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT', pw);
    ELSE
        EXECUTE format('ALTER ROLE forsa_ai_reader PASSWORD %L', pw);
    END IF;
END
$$;

GRANT USAGE ON SCHEMA forsa TO forsa_ai_reader;

-- Exactly the three tables approved in docs/ai-assistant/database-sources.md
-- — never GRANT SELECT ON ALL TABLES, so a future table added to the schema
-- is NOT automatically readable by the AI role.
GRANT SELECT ON forsa.forsa_ftk_snapshot_rows TO forsa_ai_reader;
GRANT SELECT ON forsa.forsa_ftk_snapshots TO forsa_ai_reader;
GRANT SELECT ON forsa.forsa_shap_entities TO forsa_ai_reader;
