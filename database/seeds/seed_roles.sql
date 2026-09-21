SET search_path TO forsa, public;

INSERT INTO forsa_roles (code, name, is_active)
VALUES ('SUPER_ADMIN', 'Super Admin', TRUE)
ON CONFLICT (code) DO NOTHING;
