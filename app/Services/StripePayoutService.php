<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class StripePayoutService
{
    private const API_BASE = 'https://api.stripe.com/v1';

    public function __construct(private readonly string $secretKey)
    {
        if (!str_starts_with($secretKey, 'sk_') && !str_starts_with($secretKey, 'rk_')) {
            throw new RuntimeException('The saved Stripe secret key is not valid.');
        }
    }

    public function payoutSnapshot(?int $createdSince = null): array
    {
        $account = $this->request('/account');
        $parameters = ['limit' => 10];
        if ($createdSince) {
            $parameters['created']['gte'] = $createdSince;
        }
        $payouts = $this->request('/payouts', $parameters);
        $payout = $payouts['data'][0] ?? null;

        return [
            'schedule' => $this->payoutSchedule($account['settings']['payouts']['schedule'] ?? []),
            'payout' => is_array($payout) ? [
                'id' => (string) ($payout['id'] ?? ''),
                'amount' => ((float) ($payout['amount'] ?? 0)) / 100,
                'currency' => strtoupper((string) ($payout['currency'] ?? 'USD')),
                'status' => (string) ($payout['status'] ?? 'pending'),
                'arrival_date' => isset($payout['arrival_date']) ? gmdate('Y-m-d', (int) $payout['arrival_date']) : null,
                'created' => (int) ($payout['created'] ?? 0),
            ] : null,
        ];
    }

    private function request(string $path, array $parameters = []): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL must be enabled to refresh Stripe payout data.');
        }

        $url = self::API_BASE . $path;
        if ($parameters) {
            $url .= '?' . http_build_query($parameters);
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $this->secretKey . ':',
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER => ['Stripe-Version: ' . app_config('stripe')['api_version']],
        ]);
        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Stripe connection failed: ' . $error);
        }
        $payload = json_decode($response, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Stripe returned an invalid response.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException((string) ($payload['error']['message'] ?? 'Stripe payout request failed.'));
        }

        return $payload;
    }

    private function payoutSchedule(array $schedule): string
    {
        $interval = ucfirst((string) ($schedule['interval'] ?? ''));
        $delay = $schedule['delay_days'] ?? null;
        if ($interval === '') {
            return '';
        }

        return $delay !== null ? $interval . ' - ' . $delay . ' day delay' : $interval;
    }
}
