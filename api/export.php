<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require_auth();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="store-hub-analytics.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['store', 'customer', 'amount', 'currency', 'status', 'date']);
$stmt = db()->query('SELECT s.name store_name, t.customer_email, t.amount, t.currency, t.status, t.created_at FROM transactions t LEFT JOIN stores s ON s.id=t.store_id ORDER BY t.created_at DESC');
foreach ($stmt as $row) {
    fputcsv($out, $row);
}
exit;
