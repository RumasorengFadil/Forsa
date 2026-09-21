CREATE SCHEMA IF NOT EXISTS forsa;
SET search_path TO forsa, public;

CREATE TABLE IF NOT EXISTS forsa_users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT uq_forsa_users_email UNIQUE (email)
);

CREATE TABLE IF NOT EXISTS forsa_roles (
    id SMALLSERIAL PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_forsa_roles_code UNIQUE (code)
);

CREATE TABLE IF NOT EXISTS forsa_user_roles (
    user_id BIGINT NOT NULL REFERENCES forsa_users(id) ON DELETE CASCADE,
    role_id SMALLINT NOT NULL REFERENCES forsa_roles(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, role_id)
);
