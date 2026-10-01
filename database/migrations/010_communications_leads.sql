-- Phase 10: leads, communications, campaigns, consent, and webhooks.
-- Import once on a database that already has Phase 9.
-- Do not import schema.sql on that database.

SET NAMES utf8mb4;

ALTER TABLE communications
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER contact_id,
    ADD COLUMN opportunity_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN quote_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN job_id INT UNSIGNED NULL AFTER quote_id,
    ADD COLUMN invoice_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN message_body MEDIUMTEXT NULL AFTER message_summary,
    ADD COLUMN external_message_id VARCHAR(120) NULL AFTER external_reference,
    ADD COLUMN thread_id VARCHAR(120) NULL AFTER external_message_id,
    ADD COLUMN template_id INT UNSIGNED NULL AFTER thread_id,
    ADD COLUMN failure_reason VARCHAR(255) NULL AFTER status,
    ADD COLUMN received_at DATETIME NULL AFTER sent_at,
    ADD KEY idx_communications_lead (lead_id),
    ADD KEY idx_communications_contact (contact_id),
    ADD KEY idx_communications_channel (channel),
    ADD KEY idx_communications_sent (sent_at);

ALTER TABLE sales_opportunities
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER contact_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER source,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER campaign_id;

ALTER TABLE quotes
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN attribution_source VARCHAR(40) NULL AFTER campaign_id,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER attribution_source;

ALTER TABLE jobs
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER opportunity_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN attribution_source VARCHAR(40) NULL AFTER campaign_id,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER attribution_source;

ALTER TABLE invoices
    ADD COLUMN lead_id INT UNSIGNED NULL AFTER job_id,
    ADD COLUMN campaign_id INT UNSIGNED NULL AFTER lead_id,
    ADD COLUMN attribution_source VARCHAR(40) NULL AFTER campaign_id,
    ADD COLUMN attribution_model VARCHAR(40) NULL AFTER attribution_source;

CREATE TABLE marketing_campaigns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    campaign_code VARCHAR(60) NOT NULL,
    channel VARCHAR(30) NOT NULL DEFAULT 'OTHER',
    start_date DATE NULL,
    end_date DATE NULL,
    budget DECIMAL(14,2) NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketing_campaigns_code (campaign_code),
    KEY idx_marketing_campaigns_status (status),
    CONSTRAINT fk_marketing_campaigns_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    response_hours INT UNSIGNED NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lead_sources_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_number VARCHAR(40) NOT NULL,
    source VARCHAR(40) NOT NULL,
    source_detail VARCHAR(180) NULL,
    campaign_id INT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    company_name VARCHAR(180) NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    phone_normalised VARCHAR(20) NULL,
    message TEXT NOT NULL,
    message_hash CHAR(64) NULL,
    service_interest VARCHAR(180) NULL,
    estimated_value DECIMAL(14,2) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'NEW',
    assigned_to INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    contact_id INT UNSIGNED NULL,
    opportunity_id INT UNSIGNED NULL,
    attribution_json JSON NULL,
    attribution_model VARCHAR(40) NOT NULL DEFAULT 'FIRST_TOUCH',
    first_contact_at DATETIME NULL,
    last_contact_at DATETIME NULL,
    next_followup_at DATETIME NULL,
    converted_at DATETIME NULL,
    converted_by INT UNSIGNED NULL,
    lost_reason VARCHAR(180) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_leads_number (lead_number),
    KEY idx_leads_status (status),
    KEY idx_leads_source (source),
    KEY idx_leads_assigned (assigned_to),
    KEY idx_leads_created (created_at),
    KEY idx_leads_followup (next_followup_at),
    KEY idx_leads_email (email),
    KEY idx_leads_phone (phone_normalised),
    KEY idx_leads_message_hash (message_hash),
    KEY idx_leads_campaign (campaign_id),
    CONSTRAINT fk_leads_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_assigned FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_opportunity FOREIGN KEY (opportunity_id) REFERENCES sales_opportunities (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_leads_converted_by FOREIGN KEY (converted_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sales_opportunities
    ADD KEY idx_opportunities_lead (lead_id),
    ADD KEY idx_opportunities_campaign (campaign_id),
    ADD CONSTRAINT fk_opportunities_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_opportunities_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE quotes
    ADD KEY idx_quotes_lead (lead_id),
    ADD KEY idx_quotes_campaign (campaign_id),
    ADD CONSTRAINT fk_quotes_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_quotes_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE jobs
    ADD KEY idx_jobs_lead (lead_id),
    ADD KEY idx_jobs_campaign (campaign_id),
    ADD CONSTRAINT fk_jobs_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_jobs_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE invoices
    ADD KEY idx_invoices_lead (lead_id),
    ADD KEY idx_invoices_campaign (campaign_id),
    ADD CONSTRAINT fk_invoices_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_invoices_campaign FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns (id) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE communications
    ADD CONSTRAINT fk_communications_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE communication_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'CUSTOM',
    subject_template VARCHAR(180) NULL,
    body_template MEDIUMTEXT NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_communication_templates_channel (channel, active),
    CONSTRAINT fk_communication_templates_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE communications
    ADD CONSTRAINT fk_communications_template FOREIGN KEY (template_id) REFERENCES communication_templates (id) ON DELETE SET NULL ON UPDATE CASCADE;

CREATE TABLE contact_communication_preferences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NOT NULL,
    email_allowed TINYINT(1) NOT NULL DEFAULT 1,
    whatsapp_allowed TINYINT(1) NOT NULL DEFAULT 1,
    sms_allowed TINYINT(1) NOT NULL DEFAULT 0,
    marketing_allowed TINYINT(1) NOT NULL DEFAULT 0,
    transactional_allowed TINYINT(1) NOT NULL DEFAULT 1,
    preferred_channel VARCHAR(20) NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'STAFF',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_preferences (contact_id),
    CONSTRAINT fk_contact_preferences_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE communication_suppressions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id INT UNSIGNED NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    channel VARCHAR(20) NOT NULL,
    reason VARCHAR(180) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_suppressions_email (email, channel),
    KEY idx_suppressions_phone (phone, channel),
    KEY idx_suppressions_contact (contact_id, channel),
    CONSTRAINT fk_suppressions_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    rating TINYINT UNSIGNED NULL,
    feedback_text TEXT NULL,
    source VARCHAR(40) NOT NULL DEFAULT 'STAFF',
    submitted_at DATETIME NOT NULL,
    followup_required TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_feedback_customer (customer_id, submitted_at),
    KEY idx_feedback_job (job_id),
    CONSTRAINT fk_feedback_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE review_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    job_id INT UNSIGNED NULL,
    channel VARCHAR(20) NOT NULL,
    destination VARCHAR(255) NULL,
    requested_at DATETIME NOT NULL,
    requested_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_review_requests_customer (customer_id, requested_at),
    CONSTRAINT fk_review_requests_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_review_requests_contact FOREIGN KEY (contact_id) REFERENCES customer_contacts (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_review_requests_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_review_requests_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_referrals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lead_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NULL,
    referred_by_customer_id INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_referrals_lead (lead_id),
    KEY idx_referrals_by (referred_by_customer_id),
    CONSTRAINT fk_referrals_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_referrals_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_referrals_referrer FOREIGN KEY (referred_by_customer_id) REFERENCES customers (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(40) NOT NULL,
    external_event_id VARCHAR(120) NOT NULL,
    event_type VARCHAR(80) NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    status VARCHAR(20) NOT NULL,
    received_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    error_message VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integration_events_external (provider, external_event_id),
    KEY idx_integration_events_received (received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE integration_secrets (
    secret_key VARCHAR(80) NOT NULL,
    secret_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (secret_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE public_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(40) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_public_rate_limits_bucket (bucket, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE call_outcomes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(40) NOT NULL,
    label VARCHAR(80) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_call_outcomes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (code, name, description)
SELECT 'MARKETING', 'Marketing', 'Campaigns, lead sources, templates, and retention reports.'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE code = 'MARKETING');

INSERT IGNORE INTO lead_sources (code, label, response_hours, active) VALUES
('WEBSITE', 'Website', 2, 1),
('WHATSAPP', 'WhatsApp', 1, 1),
('EMAIL', 'Email', 4, 1),
('PHONE', 'Phone', 4, 1),
('WALK_IN', 'Walk-in', 4, 1),
('FACEBOOK', 'Facebook', 4, 1),
('INSTAGRAM', 'Instagram', 4, 1),
('REFERRAL', 'Referral', 4, 1),
('RETURN_CUSTOMER', 'Return customer', 4, 1),
('GOOGLE', 'Google', 2, 1),
('OTHER', 'Other', 4, 1);

INSERT IGNORE INTO call_outcomes (code, label, active) VALUES
('ANSWERED', 'Answered', 1),
('NO_ANSWER', 'No answer', 1),
('VOICEMAIL', 'Voicemail', 1),
('CALL_BACK', 'Call back', 1),
('INTERESTED', 'Interested', 1),
('NOT_INTERESTED', 'Not interested', 1),
('FOLLOW_UP', 'Follow up', 1),
('OTHER', 'Other', 1);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('lead_prefix', 'SFL'),
('stale_lead_business_hours', '4'),
('quote_followup_business_days', '3'),
('stale_quote_business_days', '5'),
('dormant_customer_months', '12'),
('post_job_followup_days', '2'),
('review_request_url', ''),
('lead_response_hours', '4'),
('website_lead_min_seconds', '3'),
('website_lead_rate_per_hour', '8'),
('automation_outbound_enabled', '0'),
('email_delivery_mode', 'off'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_encryption', 'tls'),
('smtp_username', ''),
('smtp_from_email', ''),
('smtp_from_name', ''),
('smtp_reply_to', '');

INSERT INTO communication_templates (name, channel, category, subject_template, body_template, active)
SELECT 'Quote ready', 'EMAIL', 'QUOTE_SEND', 'Quotation {{quote_number}}', 'Hello {{contact_name}},\n\nYour quotation {{quote_number}} for {{company_name}} totals {{quote_total}} and is valid until {{quote_expiry}}.\n\n{{portal_link}}', 1
WHERE NOT EXISTS (SELECT 1 FROM communication_templates WHERE category = 'QUOTE_SEND' AND channel = 'EMAIL');

INSERT INTO communication_templates (name, channel, category, subject_template, body_template, active)
SELECT 'Quote follow-up', 'EMAIL', 'QUOTE_FOLLOW_UP', 'Following up on {{quote_number}}', 'Hello {{contact_name}},\n\nI am following up on quotation {{quote_number}} ({{quote_total}}).', 1
WHERE NOT EXISTS (SELECT 1 FROM communication_templates WHERE category = 'QUOTE_FOLLOW_UP' AND channel = 'EMAIL');

INSERT INTO communication_templates (name, channel, category, subject_template, body_template, active)
SELECT 'Review request', 'EMAIL', 'REVIEW_REQUEST', 'How did we do?', 'Hello {{contact_name}},\n\nIf you have a moment, you can leave a public review here: {{portal_link}}', 1
WHERE NOT EXISTS (SELECT 1 FROM communication_templates WHERE category = 'REVIEW_REQUEST' AND channel = 'EMAIL');

INSERT INTO communication_templates (name, channel, category, subject_template, body_template, active)
SELECT 'WhatsApp quote', 'WHATSAPP', 'QUOTE_SEND', NULL, 'Hello {{contact_name}}, quotation {{quote_number}} is ready. Total {{quote_total}}.', 1
WHERE NOT EXISTS (SELECT 1 FROM communication_templates WHERE category = 'QUOTE_SEND' AND channel = 'WHATSAPP');

INSERT IGNORE INTO permissions (code, name, module) VALUES
('leads.view', 'View leads', 'crm'),
('leads.create', 'Create leads', 'crm'),
('leads.assign', 'Assign leads', 'crm'),
('leads.convert', 'Convert leads', 'crm'),
('leads.mark_lost', 'Mark a lead lost or spam', 'crm'),
('communications.view', 'View communications', 'crm'),
('communications.create', 'Log communications', 'crm'),
('communications.send_email', 'Send email', 'crm'),
('communications.whatsapp', 'Prepare WhatsApp messages', 'crm'),
('communication_templates.view', 'View communication templates', 'crm'),
('communication_templates.manage', 'Manage communication templates', 'crm'),
('campaigns.view', 'View campaigns', 'marketing'),
('campaigns.manage', 'Manage campaigns', 'marketing'),
('marketing_reports.view', 'View marketing reports', 'marketing'),
('customer_retention.view', 'View customer retention', 'marketing'),
('feedback.view', 'View customer feedback', 'marketing'),
('feedback.manage', 'Record customer feedback', 'marketing'),
('integrations.view', 'View integrations', 'admin'),
('integrations.manage', 'Manage integrations', 'admin'),
('bulk_communications.send', 'Send a checked customer list', 'marketing');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p WHERE r.code = 'ADMIN';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'SALES' AND p.code IN (
    'leads.view', 'leads.create', 'leads.assign', 'leads.convert', 'leads.mark_lost',
    'communications.view', 'communications.create', 'communications.send_email', 'communications.whatsapp',
    'communication_templates.view', 'feedback.view', 'customer_retention.view'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MARKETING' AND p.code IN (
    'dashboard.view', 'leads.view', 'communications.view',
    'communication_templates.view', 'communication_templates.manage',
    'campaigns.view', 'campaigns.manage', 'marketing_reports.view',
    'customer_retention.view', 'feedback.view', 'bulk_communications.send'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'MANAGEMENT' AND p.code IN (
    'leads.view', 'communications.view', 'communication_templates.view',
    'campaigns.view', 'marketing_reports.view', 'customer_retention.view',
    'feedback.view', 'integrations.view'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'PRODUCTION' AND p.code IN ('communications.view');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'ACCOUNTS' AND p.code IN (
    'communications.view', 'communications.create', 'communications.send_email'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p
WHERE r.code = 'DESIGN' AND p.code IN ('communications.view');
