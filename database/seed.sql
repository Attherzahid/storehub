USE orpiemma_storehub;

INSERT INTO users (name, email, password_hash, role) VALUES
('Avery Stone', 'ameerhamzadeveloper@gmail.com', '$2y$10$APoNpEb7C1nlf.Og.IkaseqR99yH0Ny9sEarr0pgpa8VLOemy8DNCX', 'admin');

INSERT INTO stripe_keys (company_name,email,phone,country_name,country_flag,public_key,secret_key_encrypted,account_age,payout_timing,last_payout_date,total_processed_volume,status,created_at) VALUES
('Northstar Payments','ops@northstar.test','+1 555 0101','United States','🇺🇸','pk_test_northstar','YRt6BBbVsklJbsAzXf/xHoN0djVrE5RkOIi2GnVlRWXxBffXCFtsA2OtJ9UaJI9M','3 years','Rolling 2 days','2026-05-18',186430.40,'active',NOW() - INTERVAL 80 DAY),
('Atlas Retail Group','finance@atlas.test','+44 20 5555 0102','United Kingdom','🇬🇧','pk_test_atlas','YRt6BBbVsklJbsAzXf/xHoN0djVrE5RkOIi2GnVlRWXxBffXCFtsA2OtJ9UaJI9M','18 months','Weekly','2026-05-14',94760.00,'active',NOW() - INTERVAL 40 DAY),
('Crescent Commerce','billing@crescent.test','+971 55 000 1234','United Arab Emirates','🇦🇪','pk_test_crescent','YRt6BBbVsklJbsAzXf/xHoN0djVrE5RkOIi2GnVlRWXxBffXCFtsA2OtJ9UaJI9M','9 months','Rolling 7 days','2026-05-12',35210.75,'disabled',NOW() - INTERVAL 15 DAY);

INSERT INTO stores (stripe_key_id,name,domain,total_sales,monthly_sales,currency,order_count,average_order_value,status,last_sync_at,woocommerce_version,wordpress_version,created_at,updated_at) VALUES
(1,'Luma Home','https://luma-home.test',124600.40,18420.50,'USD',2498,49.88,'active',NOW() - INTERVAL 8 MINUTE,'8.8.3','6.5.4',NOW() - INTERVAL 75 DAY,NOW()),
(1,'Peak Outfitters','https://peak-outfitters.test',61830.00,9600.25,'USD',1162,53.21,'active',NOW() - INTERVAL 18 MINUTE,'8.9.1','6.5.5',NOW() - INTERVAL 60 DAY,NOW()),
(2,'Verde Market','https://verde-market.test',94760.00,12180.00,'GBP',884,107.19,'syncing',NOW() - INTERVAL 1 HOUR,'8.7.0','6.4.5',NOW() - INTERVAL 35 DAY,NOW()),
(3,'Crescent Beauty','https://crescent-beauty.test',35210.75,4210.00,'AED',520,67.71,'disabled',NOW() - INTERVAL 2 DAY,'8.6.1','6.4.4',NOW() - INTERVAL 20 DAY,NOW());

INSERT INTO transactions (store_id,stripe_key_id,stripe_transaction_id,customer_email,amount,currency,status,created_at) VALUES
(1,1,'txn_demo_001','mia@example.test',240.00,'USD','succeeded',NOW() - INTERVAL 1 DAY),
(1,1,'txn_demo_002','liam@example.test',89.50,'USD','succeeded',NOW() - INTERVAL 2 DAY),
(2,1,'txn_demo_003','noah@example.test',318.10,'USD','failed',NOW() - INTERVAL 2 DAY),
(3,2,'txn_demo_004','ava@example.test',720.00,'GBP','succeeded',NOW() - INTERVAL 3 DAY),
(4,3,'txn_demo_005','zoe@example.test',140.75,'AED','refunded',NOW() - INTERVAL 4 DAY);

INSERT INTO transactions (store_id,stripe_key_id,stripe_transaction_id,customer_email,amount,currency,status,created_at)
SELECT 1,1,CONCAT('txn_luma_', n), 'demo@example.test', 900 + (n * 37), 'USD', 'succeeded', DATE_SUB(CURDATE(), INTERVAL n MONTH)
FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11) months;

INSERT INTO payouts (stripe_key_id,amount,currency,payout_date,status) VALUES
(1,8420.50,'USD','2026-05-18','paid'),
(2,6100.00,'GBP','2026-05-14','paid'),
(3,1730.75,'AED','2026-05-12','pending');

INSERT INTO analytics (store_id,metric_key,metric_value,captured_at) VALUES
(1,'payment_success_rate',97.4,NOW()),
(2,'payment_success_rate',94.1,NOW()),
(3,'refund_rate',2.8,NOW()),
(4,'risk_score',61,NOW());

INSERT INTO activity_logs (user_id,type,message,ip_address,created_at) VALUES
(1,'auth','Admin signed in','127.0.0.1',NOW() - INTERVAL 15 MINUTE),
(1,'sync','Luma Home synced 42 orders','127.0.0.1',NOW() - INTERVAL 8 MINUTE),
(1,'stripe','Northstar Payments payout imported','127.0.0.1',NOW() - INTERVAL 4 MINUTE);

INSERT INTO store_connections (store_id,token_hash,status) VALUES
(1, SHA2('demo-store-token-change-me', 256), 'active');
