USE orpiemma_storehub;

ALTER TABLE stripe_keys
    ADD COLUMN workflow_status ENUM('ready','payout_waiting') NOT NULL DEFAULT 'ready' AFTER status,
    ADD COLUMN baseline_volume DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER workflow_status,
    ADD COLUMN target_sales DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER baseline_volume,
    ADD COLUMN target_plan ENUM('starter','standard','established') NOT NULL DEFAULT 'starter' AFTER target_sales,
    ADD COLUMN target_step TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER target_plan,
    ADD COLUMN target_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER target_step,
    ADD COLUMN payout_due_date DATE NULL AFTER target_started_at,
    ADD COLUMN payout_received TINYINT(1) NOT NULL DEFAULT 0 AFTER payout_due_date,
    ADD COLUMN workflow_note VARCHAR(255) NULL AFTER payout_received;

UPDATE stripe_keys
SET baseline_volume = total_processed_volume,
    workflow_status = CASE WHEN total_processed_volume <= 0 THEN 'payout_waiting' ELSE 'ready' END,
    target_sales = CASE WHEN total_processed_volume <= 0 THEN 5.00 ELSE ROUND(total_processed_volume * 0.80, 2) END,
    target_plan = CASE WHEN total_processed_volume <= 0 THEN 'starter' ELSE 'established' END,
    target_step = 0,
    target_started_at = NOW(),
    payout_received = 0,
    workflow_note = CASE
        WHEN total_processed_volume <= 0 THEN 'Complete an initial $5 transaction, then record its payout.'
        ELSE 'Existing history recorded; begin with an 80% baseline target.'
    END;
