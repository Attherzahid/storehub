<?php

declare(strict_types=1);

namespace App\Models;

final class Dashboard
{
    public static function metrics(): array
    {
        $pdo = db();
        return [
            'monthly_sales' => (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE status='succeeded' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn(),
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
        $storeSales = $pdo->query("SELECT s.name label, COALESCE(SUM(t.amount),0) value FROM stores s LEFT JOIN transactions t ON t.store_id=s.id AND t.status='succeeded' GROUP BY s.id ORDER BY value DESC LIMIT 8")->fetchAll();
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
}
