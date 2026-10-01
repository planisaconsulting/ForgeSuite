-- Phase 15 production hardening.
-- Import once on a database that already has migrations 001–014.
-- Do not import schema.sql over a live database.
-- Do not run this file on a database created from the current schema.sql.
-- This file adds one index. It does not change business data.

SET NAMES utf8mb4;

ALTER TABLE login_events
    ADD INDEX idx_login_events_ip_created (ip_address, created_at);
