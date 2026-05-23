<?php

declare(strict_types=1);

namespace App\Models;

final class Dashboard
{
    public static function metrics(): array
    {
        $pdo = db();
        return [
            'monthly_sales' => (float) $pdo->query("SELECT COALESCE(SUM(monthly_sales),0) FROM stores WHERE status IN ('active','syncing')")->fetchColumn(),
            'stores' => (int) $pdo->query('SELECT COUNT(*) FROM stores')->fetchColumn(),
            'keys' => (int) $pdo->query('SELECT COUNT(*) FROM stripe_keys')->fetchColumn(),
            'active_stores' => (int) $pdo->query("SELECT COUNT(*) FROM stores WHERE status='active'")->fetchColumn(),
            'failed_payments' => (int) $pdo->query("SELECT COUNT(*) FROM transactions WHERE status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
        ];
    }

    public static function charts(): array
    {
        $pdo = db();
        $monthly = $pdo->query("SELECT DATE_FORMAT(created_at, '%b') label, SUM(amount) value FROM transactions WHERE status='succeeded' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY YEAR(created_at), MONTH(created_at) ORDER BY MIN(created_at)")->fetchAll();
        $storeSales = $pdo->query("SELECT s.name label, COALESCE(s.monthly_sales,0) value FROM stores s ORDER BY value DESC LIMIT 8")->fetchAll();
        $trend = $pdo->query("SELECT DATE(created_at) label, SUM(amount) value FROM transactions WHERE status='succeeded' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY label")->fetchAll();

        return [
            'monthlySales' => $monthly,
            'storeSales' => $storeSales,
            'topStores' => $storeSales,
            'revenueTrend' => $trend,
        ];
    }

    public static function recentTransactions(int $limit = 8): array
    {
        $stmt = db()->prepare('SELECT t.*, s.name store_name FROM transactions t LEFT JOIN stores s ON s.id=t.store_id ORDER BY t.created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function activity(int $limit = 8): array
    {
        $stmt = db()->prepare('SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function payouts(int $limit = 5): array
    {
        $stmt = db()->prepare('SELECT p.*, k.company_name FROM payouts p LEFT JOIN stripe_keys k ON k.id=p.stripe_key_id ORDER BY payout_date DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function analyticsReport(?int $storeId, string $from, string $to): array
    {
        $where = "WHERE t.created_at BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($storeId) {
            $where .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }

        $summary = db()->prepare("
            SELECT
                COUNT(t.id) transaction_count,
                COALESCE(SUM(CASE WHEN t.status = 'succeeded' THEN t.amount ELSE 0 END), 0) revenue,
                COALESCE(SUM(CASE WHEN t.status = 'failed' THEN 1 ELSE 0 END), 0) failed_count,
                COALESCE(SUM(CASE WHEN t.status = 'refunded' THEN 1 ELSE 0 END), 0) refund_count,
                COALESCE(SUM(CASE WHEN t.status = 'succeeded' THEN 1 ELSE 0 END), 0) success_count,
                COALESCE(AVG(CASE WHEN t.status = 'succeeded' THEN t.amount ELSE NULL END), 0) average_order_value
            FROM transactions t
            {$where}
        ");
        $summary->execute($params);
        $metrics = $summary->fetch() ?: [];
        $transactionCount = (int) ($metrics['transaction_count'] ?? 0);
        $successCount = (int) ($metrics['success_count'] ?? 0);

        $daily = db()->prepare("
            SELECT DATE_FORMAT(t.created_at, '%Y-%m-%d %H:00') label, COALESCE(SUM(CASE WHEN t.status='succeeded' THEN t.amount ELSE 0 END), 0) value
            FROM transactions t
            {$where}
            GROUP BY DATE_FORMAT(t.created_at, '%Y-%m-%d %H:00')
            ORDER BY MIN(t.created_at)
        ");
        $daily->execute($params);

        $storeSales = db()->prepare("
            SELECT COALESCE(s.name, 'Unassigned') label, COALESCE(SUM(CASE WHEN t.status='succeeded' THEN t.amount ELSE 0 END), 0) value
            FROM transactions t
            LEFT JOIN stores s ON s.id = t.store_id
            {$where}
            GROUP BY s.id, s.name
            ORDER BY value DESC
            LIMIT 12
        ");
        $storeSales->execute($params);

        $statuses = db()->prepare("
            SELECT t.status label, COUNT(*) value
            FROM transactions t
            {$where}
            GROUP BY t.status
            ORDER BY value DESC
        ");
        $statuses->execute($params);

        $transactions = db()->prepare("
            SELECT t.*, s.name store_name
            FROM transactions t
            LEFT JOIN stores s ON s.id = t.store_id
            {$where}
            ORDER BY t.created_at DESC
            LIMIT 100
        ");
        $transactions->execute($params);

        return [
            'metrics' => [
                'revenue' => (float) ($metrics['revenue'] ?? 0),
                'transaction_count' => $transactionCount,
                'average_order_value' => (float) ($metrics['average_order_value'] ?? 0),
                'failed_count' => (int) ($metrics['failed_count'] ?? 0),
                'refund_count' => (int) ($metrics['refund_count'] ?? 0),
                'success_rate' => $transactionCount > 0 ? round(($successCount / $transactionCount) * 100, 1) : 0,
            ],
            'charts' => [
                'revenueTrend' => $daily->fetchAll(),
                'storeSales' => $storeSales->fetchAll(),
                'statuses' => $statuses->fetchAll(),
            ],
            'transactions' => $transactions->fetchAll(),
        ];
    }
}
