<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$connection = require_api_token();
throttle_api('sync:' . $connection['id']);
$input = request_input();

$pdo = db();
$pdo->beginTransaction();
try {
    $storeId = (int) $connection['store_id'];
    $summary = $input['summary'] ?? [];
    $stmt = $pdo->prepare('UPDATE stores SET total_sales=?, monthly_sales=?, currency=?, order_count=?, average_order_value=?, last_sync_at=NOW(), woocommerce_version=?, wordpress_version=?, updated_at=NOW() WHERE id=?');
    $stmt->execute([
        (float) ($summary['total_sales'] ?? 0),
        (float) ($summary['monthly_sales'] ?? 0),
        strtoupper((string) ($summary['currency'] ?? 'USD')),
        (int) ($summary['order_count'] ?? 0),
        (float) ($summary['average_order_value'] ?? 0),
        (string) ($summary['woocommerce_version'] ?? ''),
        (string) ($summary['wordpress_version'] ?? ''),
        $storeId,
    ]);

    $insert = $pdo->prepare('INSERT INTO transactions (store_id, stripe_key_id, stripe_transaction_id, customer_email, amount, currency, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE amount=VALUES(amount), status=VALUES(status)');
    foreach (($input['orders'] ?? []) as $order) {
        $insert->execute([
            $storeId,
            null,
            (string) ($order['id'] ?? ''),
            filter_var($order['customer_email'] ?? '', FILTER_SANITIZE_EMAIL),
            (float) ($order['total'] ?? 0),
            strtoupper((string) ($order['currency'] ?? 'USD')),
            (string) ($order['status'] ?? 'succeeded'),
            (string) ($order['created_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }

    log_activity('WooCommerce store synced', 'sync');
    $pdo->commit();
    json_response(['message' => 'Store synced']);
} catch (Throwable $exception) {
    $pdo->rollBack();
    json_response(['error' => 'Sync failed'], 422);
}
