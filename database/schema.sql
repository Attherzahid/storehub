CREATE DATABASE IF NOT EXISTS orpiemma_storehub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE orpiemma_storehub;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE stripe_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(60) NULL,
    country_name VARCHAR(100) NULL,
    country_flag VARCHAR(16) NULL,
    public_key VARCHAR(255) NOT NULL,
    secret_key_encrypted TEXT NOT NULL,
    account_age VARCHAR(80) NULL,
    payout_timing VARCHAR(120) NULL,
    last_payout_date DATE NULL,
    total_processed_volume DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stripe_status (status),
    INDEX idx_stripe_company (company_name)
) ENGINE=InnoDB;

CREATE TABLE stores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_key_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    domain VARCHAR(190) NOT NULL UNIQUE,
    total_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    monthly_sales DECIMAL(14,2) NOT NULL DEFAULT 0,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    order_count INT UNSIGNED NOT NULL DEFAULT 0,
    average_order_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active','disabled','syncing') NOT NULL DEFAULT 'active',
    last_sync_at DATETIME NULL,
    woocommerce_version VARCHAR(40) NULL,
    wordpress_version VARCHAR(40) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stores_stripe_key FOREIGN KEY (stripe_key_id) REFERENCES stripe_keys(id) ON DELETE SET NULL,
    INDEX idx_store_status (status)
) ENGINE=InnoDB;

CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NULL,
    stripe_key_id BIGINT UNSIGNED NULL,
    stripe_transaction_id VARCHAR(190) NULL UNIQUE,
    customer_email VARCHAR(190) NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('succeeded','failed','refunded','pending') NOT NULL DEFAULT 'succeeded',
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_transactions_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    CONSTRAINT fk_transactions_key FOREIGN KEY (stripe_key_id) REFERENCES stripe_keys(id) ON DELETE SET NULL,
    INDEX idx_transactions_status_date (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE payouts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stripe_key_id BIGINT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    payout_date DATE NOT NULL,
    status ENUM('paid','pending','failed') NOT NULL DEFAULT 'paid',
    CONSTRAINT fk_payouts_key FOREIGN KEY (stripe_key_id) REFERENCES stripe_keys(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE analytics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NULL,
    metric_key VARCHAR(120) NOT NULL,
    metric_value DECIMAL(14,2) NOT NULL DEFAULT 0,
    captured_at DATETIME NOT NULL,
    CONSTRAINT fk_analytics_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
    INDEX idx_analytics_metric (metric_key, captured_at)
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    type VARCHAR(60) NOT NULL DEFAULT 'info',
    message VARCHAR(255) NOT NULL,
    ip_address VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_logs_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE store_connections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    status ENUM('active','revoked') NOT NULL DEFAULT 'active',
    last_seen_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_connections_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
    UNIQUE KEY uniq_connections_store (store_id)
) ENGINE=InnoDB;
