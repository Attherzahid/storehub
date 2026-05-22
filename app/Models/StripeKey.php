<?php

declare(strict_types=1);

namespace App\Models;

final class StripeKey
{
    public static function all(?string $search = null): array
    {
        $sql = 'SELECT k.*, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ") connected_stores
                FROM stripe_keys k
                LEFT JOIN stores s ON s.stripe_key_id = k.id';
        $params = [];
        if ($search) {
            $sql .= ' WHERE k.company_name LIKE ? OR k.email LIKE ? OR k.country_name LIKE ?';
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        $sql .= ' GROUP BY k.id ORDER BY k.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): void
    {
        $stmt = db()->prepare('INSERT INTO stripe_keys
            (company_name,email,phone,country_name,country_flag,public_key,secret_key_encrypted,account_age,payout_timing,last_payout_date,total_processed_volume,status,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([
            trim($data['company_name'] ?? ''),
            filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL),
            trim($data['phone'] ?? ''),
            trim($data['country_name'] ?? ''),
            trim($data['country_flag'] ?? ''),
            trim($data['public_key'] ?? ''),
            encrypt_secret((string) ($data['secret_key'] ?? '')),
            trim($data['account_age'] ?? 'New'),
            trim($data['payout_timing'] ?? 'Rolling 2 days'),
            $data['last_payout_date'] ?: null,
            (float) ($data['total_processed_volume'] ?? 0),
            $data['status'] ?? 'active',
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $fields = [
            'company_name' => trim($data['company_name'] ?? ''),
            'email' => filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL),
            'phone' => trim($data['phone'] ?? ''),
            'country_name' => trim($data['country_name'] ?? ''),
            'country_flag' => trim($data['country_flag'] ?? ''),
            'public_key' => trim($data['public_key'] ?? ''),
            'account_age' => trim($data['account_age'] ?? ''),
            'payout_timing' => trim($data['payout_timing'] ?? ''),
            'last_payout_date' => $data['last_payout_date'] ?: null,
            'total_processed_volume' => (float) ($data['total_processed_volume'] ?? 0),
            'status' => $data['status'] ?? 'active',
        ];

        $sql = 'UPDATE stripe_keys SET company_name=?, email=?, phone=?, country_name=?, country_flag=?, public_key=?, account_age=?, payout_timing=?, last_payout_date=?, total_processed_volume=?, status=?';
        $params = array_values($fields);
        if (!empty($data['secret_key'])) {
            $sql .= ', secret_key_encrypted=?';
            $params[] = encrypt_secret((string) $data['secret_key']);
        }
        $sql .= ' WHERE id=?';
        $params[] = $id;
        db()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM stripe_keys WHERE id=?')->execute([$id]);
    }

    public static function details(int $id): ?array
    {
        $keyStmt = db()->prepare('SELECT id, company_name, email, phone, country_name, country_flag, account_age, payout_timing, last_payout_date, total_processed_volume, status, created_at FROM stripe_keys WHERE id = ? LIMIT 1');
        $keyStmt->execute([$id]);
        $key = $keyStmt->fetch();
        if (!$key) {
            return null;
        }

        $storesStmt = db()->prepare("
            SELECT
                s.id,
                s.name,
                s.domain,
                s.currency,
                s.status,
                s.last_sync_at,
                COUNT(t.id) transaction_count,
                COALESCE(SUM(CASE WHEN t.status = 'succeeded' THEN t.amount ELSE 0 END), 0) successful_amount,
                COALESCE(SUM(t.amount), 0) total_amount,
                COALESCE(SUM(CASE WHEN t.status = 'failed' THEN 1 ELSE 0 END), 0) failed_count,
                COALESCE(SUM(CASE WHEN t.status = 'refunded' THEN 1 ELSE 0 END), 0) refund_count
            FROM stores s
            LEFT JOIN transactions t ON t.store_id = s.id
            WHERE s.stripe_key_id = ?
            GROUP BY s.id, s.name, s.domain, s.currency, s.status, s.last_sync_at
            ORDER BY successful_amount DESC, s.name ASC
        ");
        $storesStmt->execute([$id]);
        $stores = $storesStmt->fetchAll();

        $summaryStmt = db()->prepare("
            SELECT
                COUNT(t.id) transaction_count,
                COALESCE(SUM(CASE WHEN t.status = 'succeeded' THEN t.amount ELSE 0 END), 0) successful_amount,
                COALESCE(SUM(CASE WHEN t.status = 'failed' THEN 1 ELSE 0 END), 0) failed_count,
                COALESCE(SUM(CASE WHEN t.status = 'refunded' THEN 1 ELSE 0 END), 0) refund_count,
                COALESCE(SUM(CASE WHEN t.status = 'succeeded' THEN 1 ELSE 0 END), 0) success_count
            FROM transactions t
            LEFT JOIN stores s ON s.id = t.store_id
            WHERE t.stripe_key_id = ? OR s.stripe_key_id = ?
        ");
        $summaryStmt->execute([$id, $id]);
        $summary = $summaryStmt->fetch();

        $transactionsStmt = db()->prepare("
            SELECT t.id, t.store_id, t.stripe_transaction_id, t.customer_email, t.amount, t.currency, t.status, t.created_at, s.name store_name
            FROM transactions t
            LEFT JOIN stores s ON s.id = t.store_id
            WHERE t.stripe_key_id = ? OR s.stripe_key_id = ?
            ORDER BY t.created_at DESC
            LIMIT 100
        ");
        $transactionsStmt->execute([$id, $id]);
        $transactions = $transactionsStmt->fetchAll();

        $payoutsStmt = db()->prepare('SELECT amount, currency, payout_date, status FROM payouts WHERE stripe_key_id = ? ORDER BY payout_date DESC LIMIT 20');
        $payoutsStmt->execute([$id]);

        $transactionCount = (int) ($summary['transaction_count'] ?? 0);
        $successCount = (int) ($summary['success_count'] ?? 0);

        return [
            'key' => $key,
            'summary' => [
                'connected_store_count' => count($stores),
                'transaction_count' => $transactionCount,
                'successful_amount' => (float) ($summary['successful_amount'] ?? 0),
                'failed_count' => (int) ($summary['failed_count'] ?? 0),
                'refund_count' => (int) ($summary['refund_count'] ?? 0),
                'success_rate' => $transactionCount > 0 ? round(($successCount / $transactionCount) * 100, 1) : 0,
            ],
            'stores' => $stores,
            'transactions' => $transactions,
            'payouts' => $payoutsStmt->fetchAll(),
        ];
    }
}
