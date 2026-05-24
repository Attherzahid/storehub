USE store_hub;

ALTER TABLE stripe_keys
    ADD COLUMN waiting_started_at DATETIME NULL AFTER target_started_at,
    ADD COLUMN stripe_payout_id VARCHAR(190) NULL AFTER payout_received,
    ADD COLUMN stripe_payout_status VARCHAR(40) NULL AFTER stripe_payout_id,
    ADD COLUMN stripe_payout_synced_at DATETIME NULL AFTER stripe_payout_status;

ALTER TABLE payouts
    ADD COLUMN stripe_payout_id VARCHAR(190) NULL UNIQUE AFTER stripe_key_id,
    MODIFY COLUMN status ENUM('paid','pending','in_transit','failed','canceled') NOT NULL DEFAULT 'paid';

UPDATE stripe_keys
SET waiting_started_at = CASE WHEN workflow_status = 'payout_waiting' THEN NOW() ELSE NULL END;
