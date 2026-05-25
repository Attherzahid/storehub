<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\StripePayoutService;

final class StripeKey
{
    public const AUTO_WAIT_PERCENT = 95;
    private const STANDARD_TARGETS = [1000, 2000, 3500, 5000, 8000, 10000];
    private const ESTABLISHED_ADDITIONS = [0, 2000, 3500, 5000, 8000, 10000];

    public static function all(?string $search = null): array
    {
        $sql = 'SELECT k.*, GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ", ") connected_stores,
                    (SELECT p.amount FROM payouts p WHERE p.stripe_key_id=k.id ORDER BY p.payout_date DESC, p.id DESC LIMIT 1) last_payout_amount,
                    (SELECT p.currency FROM payouts p WHERE p.stripe_key_id=k.id ORDER BY p.payout_date DESC, p.id DESC LIMIT 1) last_payout_currency,
                    (SELECT COALESCE(SUM(t.amount),0) FROM transactions t WHERE t.stripe_key_id=k.id AND t.status="succeeded" AND t.created_at >= k.target_started_at) cycle_sales
                FROM stripe_keys k
                LEFT JOIN stores s ON s.stripe_key_id = k.id';
        $params = [];
        if ($search) {
            $sql .= ' WHERE k.company_name LIKE ? OR k.email LIKE ? OR k.country_name LIKE ?';
            $params = ["%$search%", "%$search%", "%$search%"];
        }
        $sql .= ' GROUP BY k.id ORDER BY
                    CASE WHEN k.workflow_status = "payout_waiting" THEN 0 ELSE 1 END,
                    CASE WHEN k.workflow_status = "payout_waiting" THEN COALESCE(k.payout_due_date, "9999-12-31") END ASC,
                    k.created_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return array_map(static function (array $key): array {
            $key['public_key_masked'] = self::maskedCredential((string) $key['public_key']);
            $key['secret_key_masked'] = self::maskedSecret((string) $key['secret_key_encrypted']);
            return $key;
        }, $stmt->fetchAll());
    }

    public static function create(array $data): void
    {
        $baseline = max(0, (float) ($data['baseline_volume'] ?? 0));
        $isNew = $baseline <= 0;
        $stmt = db()->prepare('INSERT INTO stripe_keys
            (company_name,email,phone,country_name,country_flag,public_key,secret_key_encrypted,account_age,payout_timing,last_payout_date,total_processed_volume,status,workflow_status,baseline_volume,target_sales,target_plan,target_step,waiting_started_at,payout_due_date,payout_received,workflow_note,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
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
            $baseline,
            $data['status'] ?? 'active',
            $isNew ? 'payout_waiting' : 'ready',
            $baseline,
            $isNew ? 5 : round($baseline * 0.8, 2),
            $isNew ? 'starter' : 'established',
            0,
            $isNew ? date('Y-m-d H:i:s') : null,
            null,
            0,
            $isNew
                ? 'Complete an initial $5 transaction, then record its payout.'
                : 'Existing history recorded; begin with an 80% baseline target.',
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
            'status' => $data['status'] ?? 'active',
        ];

        $sql = 'UPDATE stripe_keys SET company_name=?, email=?, phone=?, country_name=?, country_flag=?, public_key=?, account_age=?, payout_timing=?, last_payout_date=?, status=?';
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

    public static function automaticallyWaitForReachedTargets(?int $keyId = null): array
    {
        $sql = 'SELECT candidate.*
                FROM (
                    SELECT k.id, k.company_name, k.target_sales,
                    (SELECT COALESCE(SUM(t.amount), 0)
                        FROM transactions t
                        WHERE t.stripe_key_id = k.id
                          AND t.status = "succeeded"
                          AND t.created_at >= k.target_started_at) cycle_sales
                    FROM stripe_keys k
                    WHERE k.workflow_status = "ready"
                      AND k.status = "active"
                      AND k.target_sales > 0';
        $params = [];
        if ($keyId !== null) {
            $sql .= ' AND k.id = ?';
            $params[] = $keyId;
        }
        $sql .= ') candidate
                WHERE candidate.cycle_sales >= candidate.target_sales * ?';
        $params[] = self::AUTO_WAIT_PERCENT / 100;

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $reached = $stmt->fetchAll();
        if (!$reached) {
            return [];
        }

        $note = 'Automatically paused near the sales target. Replace this key on connected stores and wait for payout confirmation.';
        $update = db()->prepare('UPDATE stripe_keys SET workflow_status="payout_waiting", waiting_started_at=NOW(), payout_due_date=NULL, payout_received=0, stripe_payout_status=NULL, workflow_note=? WHERE id=? AND workflow_status="ready"');
        $moved = [];
        foreach ($reached as $key) {
            $update->execute([$note, (int) $key['id']]);
            if ($update->rowCount() > 0) {
                $moved[] = $key;
            }
        }

        return $moved;
    }

    public static function moveToPayoutWaiting(int $id, ?string $dueDate, ?int $replacementId): void
    {
        $pdo = db();
        if ($replacementId !== null) {
            if ($replacementId === $id) {
                throw new \RuntimeException('Choose a different replacement key.');
            }
            $replacement = $pdo->prepare('SELECT id FROM stripe_keys WHERE id=? AND workflow_status="ready" AND status="active" LIMIT 1');
            $replacement->execute([$replacementId]);
            if (!$replacement->fetchColumn()) {
                throw new \RuntimeException('Replacement key must be active and ready to use.');
            }
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE stripe_keys SET workflow_status="payout_waiting", waiting_started_at=NOW(), payout_due_date=?, payout_received=0, stripe_payout_status=NULL, workflow_note="Sales target reached; waiting for payout confirmation." WHERE id=?');
            $stmt->execute([$dueDate ?: null, $id]);
            if ($replacementId !== null) {
                $stores = $pdo->prepare('UPDATE stores SET stripe_key_id=?, updated_at=NOW() WHERE stripe_key_id=?');
                $stores->execute([$replacementId, $id]);
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public static function assignReplacementForWaiting(int $id, int $replacementId): void
    {
        if ($replacementId === $id) {
            throw new \RuntimeException('Choose a different replacement key.');
        }

        $pdo = db();
        $waiting = $pdo->prepare('SELECT id FROM stripe_keys WHERE id=? AND workflow_status="payout_waiting" LIMIT 1');
        $waiting->execute([$id]);
        if (!$waiting->fetchColumn()) {
            throw new \RuntimeException('This key is not waiting for payout.');
        }

        $replacement = $pdo->prepare('SELECT id FROM stripe_keys WHERE id=? AND workflow_status="ready" AND status="active" LIMIT 1');
        $replacement->execute([$replacementId]);
        if (!$replacement->fetchColumn()) {
            throw new \RuntimeException('Replacement key must be active and ready to use.');
        }

        $stores = $pdo->prepare('UPDATE stores SET stripe_key_id=?, updated_at=NOW() WHERE stripe_key_id=?');
        $stores->execute([$replacementId, $id]);
        $note = 'Waiting for payout confirmation. Connected stores were assigned to a replacement key.';
        $pdo->prepare('UPDATE stripe_keys SET workflow_note=? WHERE id=?')->execute([$note, $id]);
    }

    public static function recordPayout(int $id, float $amount, string $currency, string $payoutDate, ?string $stripePayoutId = null): void
    {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT target_plan, target_step, baseline_volume FROM stripe_keys WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $key = $stmt->fetch();
        if (!$key) {
            throw new \RuntimeException('Stripe key not found.');
        }

        [$target, $plan, $step] = self::nextTarget($key);

        $pdo->beginTransaction();
        try {
            $payout = $pdo->prepare('INSERT INTO payouts (stripe_key_id,stripe_payout_id,amount,currency,payout_date,status) VALUES (?,?,?,?,?, "paid") ON DUPLICATE KEY UPDATE amount=VALUES(amount), currency=VALUES(currency), payout_date=VALUES(payout_date), status="paid"');
            $payout->execute([$id, $stripePayoutId, $amount, strtoupper($currency), $payoutDate]);
            $update = $pdo->prepare('UPDATE stripe_keys SET workflow_status="ready", target_sales=?, target_plan=?, target_step=?, target_started_at=NOW(), waiting_started_at=NULL, payout_received=1, last_payout_date=?, payout_due_date=NULL, stripe_payout_id=COALESCE(?, stripe_payout_id), stripe_payout_status="paid", workflow_note="Payout received; ready for the next sales target." WHERE id=?');
            $update->execute([$target, $plan, $step, $payoutDate, $stripePayoutId, $id]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private static function nextTarget(array $key): array
    {
        $plan = (string) $key['target_plan'];
        $step = (int) $key['target_step'];
        $baseline = (float) $key['baseline_volume'];

        if ($plan === 'starter') {
            return [(float) self::STANDARD_TARGETS[0], 'standard', 0];
        }

        if ($plan === 'established') {
            $nextStep = min($step + 1, count(self::ESTABLISHED_ADDITIONS) - 1);
            return [$baseline + self::ESTABLISHED_ADDITIONS[$nextStep], 'established', $nextStep];
        }

        $nextStep = min($step + 1, count(self::STANDARD_TARGETS) - 1);
        return [(float) self::STANDARD_TARGETS[$nextStep], 'standard', $nextStep];
    }

    public static function refreshPayout(int $id): array
    {
        $stmt = db()->prepare('SELECT * FROM stripe_keys WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $key = $stmt->fetch();
        if (!$key) {
            throw new \RuntimeException('Stripe key not found.');
        }

        $secret = decrypt_secret((string) $key['secret_key_encrypted']);
        $since = $key['workflow_status'] === 'payout_waiting' && !empty($key['waiting_started_at'])
            ? (new \DateTimeImmutable((string) $key['waiting_started_at']))->getTimestamp()
            : null;
        $snapshot = (new StripePayoutService($secret))->payoutSnapshot($since);
        $payout = $snapshot['payout'];

        $update = db()->prepare('UPDATE stripe_keys SET payout_timing=COALESCE(NULLIF(?, ""), payout_timing), stripe_payout_id=?, stripe_payout_status=?, stripe_payout_synced_at=NOW(), payout_due_date=COALESCE(?, payout_due_date) WHERE id=?');
        $update->execute([
            $snapshot['schedule'],
            $payout['id'] ?? null,
            $payout['status'] ?? null,
            $payout['arrival_date'] ?? null,
            $id,
        ]);

        if ($payout && $key['workflow_status'] === 'payout_waiting' && $payout['status'] === 'paid') {
            self::recordPayout($id, (float) $payout['amount'], (string) $payout['currency'], (string) ($payout['arrival_date'] ?: date('Y-m-d')), (string) $payout['id']);
            return ['message' => 'Stripe confirmed the payout. This key is ready with its next target.', 'became_ready' => true];
        }

        if ($payout) {
            return ['message' => 'Stripe payout status refreshed: ' . $payout['status'] . '.', 'became_ready' => false];
        }

        return ['message' => 'Payout schedule refreshed. Stripe has no new payout for this waiting cycle yet.', 'became_ready' => false];
    }

    public static function revealSecret(int $id, string $password): string
    {
        $userStmt = db()->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $userStmt->execute([$_SESSION['user_id'] ?? 0]);
        $passwordHash = $userStmt->fetchColumn();
        if (!$passwordHash || !password_verify($password, (string) $passwordHash)) {
            throw new \RuntimeException('Admin password is incorrect.');
        }

        $stmt = db()->prepare('SELECT secret_key_encrypted FROM stripe_keys WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $encrypted = $stmt->fetchColumn();
        if (!$encrypted) {
            throw new \RuntimeException('Stripe key not found.');
        }
        $secret = decrypt_secret((string) $encrypted);
        if ($secret === '') {
            throw new \RuntimeException('Unable to decrypt the saved secret key.');
        }

        return $secret;
    }

    private static function maskedSecret(string $encrypted): string
    {
        $secret = decrypt_secret($encrypted);
        if ($secret === '') {
            return 'Unavailable';
        }

        return self::maskedCredential($secret);
    }

    private static function maskedCredential(string $key): string
    {
        if ($key === '') {
            return 'Unavailable';
        }

        return substr($key, 0, min(8, strlen($key))) . '******' . substr($key, -4);
    }

    public static function details(int $id): ?array
    {
        $keyStmt = db()->prepare('SELECT id, company_name, email, phone, country_name, country_flag, public_key, secret_key_encrypted, account_age, payout_timing, last_payout_date, total_processed_volume, status, stripe_payout_status, stripe_payout_synced_at, created_at FROM stripe_keys WHERE id = ? LIMIT 1');
        $keyStmt->execute([$id]);
        $key = $keyStmt->fetch();
        if (!$key) {
            return null;
        }
        $key['public_key_masked'] = self::maskedCredential((string) $key['public_key']);
        $key['secret_key_masked'] = self::maskedSecret((string) $key['secret_key_encrypted']);
        unset($key['secret_key_encrypted']);

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
