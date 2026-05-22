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
}
