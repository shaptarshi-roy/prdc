-- =====================================================================
--  Role passwords + LOGIN attribute
--
--  The schema defines roles as NOINHERIT (no login). This file gives
--  each role a password and the LOGIN attribute, reading per-role
--  passwords from secret files mounted at /run/secrets.
--
--  Runs automatically on first DB boot via /docker-entrypoint-initdb.d.
-- =====================================================================

\set api_pw         `cat /run/secrets/api_db_password`
\set worker_pw      `cat /run/secrets/worker_db_password`
\set researcher_pw  `cat /run/secrets/researcher_db_password`

ALTER ROLE api_role    LOGIN PASSWORD :'api_pw';
ALTER ROLE worker_role LOGIN PASSWORD :'worker_pw';
ALTER ROLE researcher  LOGIN PASSWORD :'researcher_pw';
