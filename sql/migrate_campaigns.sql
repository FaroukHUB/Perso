-- Migration: Système de campagnes email (inspiré Brevo)

CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL DEFAULT '',
    preheader VARCHAR(255) DEFAULT NULL,
    content LONGTEXT DEFAULT NULL,
    sender_name VARCHAR(100) DEFAULT NULL,
    sender_email VARCHAR(255) DEFAULT NULL,
    status ENUM('draft', 'scheduled', 'sending', 'sent', 'paused') DEFAULT 'draft',
    recipients_type ENUM('all_clients', 'newsletter', 'custom', 'segment') DEFAULT 'all_clients',
    recipients_data JSON DEFAULT NULL,
    total_recipients INT UNSIGNED DEFAULT 0,
    total_sent INT UNSIGNED DEFAULT 0,
    total_opened INT UNSIGNED DEFAULT 0,
    total_clicked INT UNSIGNED DEFAULT 0,
    scheduled_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaign_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    status ENUM('pending', 'sent', 'failed', 'opened', 'clicked') DEFAULT 'pending',
    sent_at DATETIME DEFAULT NULL,
    opened_at DATETIME DEFAULT NULL,
    error_message VARCHAR(255) DEFAULT NULL,
    INDEX idx_campaign (campaign_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    CONSTRAINT fk_cr_campaign FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
