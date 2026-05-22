<?php

declare(strict_types=1);

namespace App\Models;

final class Store
{
    private static function datetime(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : str_replace('T', ' ', $value);
    }

    public static function all(?string $search = null): array
    {
        $sql = 'SELECT s.*, k.company_name stripe_company FROM stores s LEFT JOIN stripe_keys k ON k.id=s.stripe_key_id';
        $params = [];
        if ($search) {
            $sql .= ' WHERE s.name LIKE ? OR s.domain LIKE ?';
            $params = ["%$search%", "%$search%"];
        }
        $sql .= ' ORDER BY s.updated_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function create(array $data): void
    {
        $stmt = db()->prepare('INSERT INTO stores
            (stripe_key_id,name,domain,total_sales,monthly_sales,currency,order_count,average_order_value,status,last_sync_at,woocommerce_version,wordpress_version,created_at,updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([
            $data['stripe_key_id'] ?: null,
            trim($data['name'] ?? ''),
            trim($data['domain'] ?? ''),
            (float) ($data['total_sales'] ?? 0),
            (float) ($data['monthly_sales'] ?? 0),
            strtoupper(trim($data['currency'] ?? 'USD')),
            (int) ($data['order_count'] ?? 0),
            (float) ($data['average_order_value'] ?? 0),
            $data['status'] ?? 'active',
            self::datetime($data['last_sync_at'] ?? null),
            trim($data['woocommerce_version'] ?? ''),
            trim($data['wordpress_version'] ?? ''),
        ]);
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare('UPDATE stores SET stripe_key_id=?, name=?, domain=?, total_sales=?, monthly_sales=?, currency=?, order_count=?, average_order_value=?, status=?, last_sync_at=?, woocommerce_version=?, wordpress_version=?, updated_at=NOW() WHERE id=?');
        $stmt->execute([
            $data['stripe_key_id'] ?: null,
            trim($data['name'] ?? ''),
            trim($data['domain'] ?? ''),
            (float) ($data['total_sales'] ?? 0),
            (float) ($data['monthly_sales'] ?? 0),
            strtoupper(trim($data['currency'] ?? 'USD')),
            (int) ($data['order_count'] ?? 0),
            (float) ($data['average_order_value'] ?? 0),
            $data['status'] ?? 'active',
            self::datetime($data['last_sync_at'] ?? null),
            trim($data['woocommerce_version'] ?? ''),
            trim($data['wordpress_version'] ?? ''),
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM stores WHERE id=?')->execute([$id]);
    }
}
