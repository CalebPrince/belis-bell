<?php
declare(strict_types=1);

namespace Belis\Payments;

/**
 * Paystack over HTTPS (redirect flow). Written from Paystack's documented Transaction API: initialize with
 * POST /transaction/initialize (amount in the smallest unit, currency GHS), verify with
 * GET /transaction/verify/:reference. NOT YET RUN against Paystack: no test key has been used, and the current
 * Paystack documentation must be re-checked at first use (CTL-PAY-002 says so). The secret key comes only from the
 * server environment. Every call has a timeout and verifies the TLS certificate.
 */
final class PaystackAdapter implements PaymentAdapter
{
    private const BASE = 'https://api.paystack.co';

    /** @var callable(string,string,list<string>,?string):array{status:int,body:string} */
    private $http;

    /** @param callable(string,string,list<string>,?string):array{status:int,body:string}|null $http replaceable for tests */
    public function __construct(private readonly string $secretKey, ?callable $http = null)
    {
        $this->http = $http ?? [self::class, 'curl'];
    }

    public function initialize(string $email, int $amountPesewas, string $reference, string $callbackUrl): string
    {
        $payload = (string) json_encode([
            'email' => $email,
            'amount' => $amountPesewas,
            'currency' => 'GHS',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
        ]);
        $data = $this->call('POST', '/transaction/initialize', $payload);
        $url = $data['data']['authorization_url'] ?? null;
        if (($data['status'] ?? false) !== true || !is_string($url) || !str_starts_with($url, 'https://')) {
            throw new \RuntimeException('Paystack did not return a payment page');
        }
        return $url;
    }

    public function verify(string $reference): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{6,60}$/', $reference) !== 1) {
            throw new \RuntimeException('Bad reference');
        }
        $data = $this->call('GET', '/transaction/verify/' . $reference, null);
        $d = $data['data'] ?? null;
        if (($data['status'] ?? false) !== true || !is_array($d)) {
            throw new \RuntimeException('Paystack did not confirm the transaction lookup');
        }
        $raw = (string) ($d['status'] ?? '');
        return [
            'status' => $raw === 'success' ? 'success' : ($raw === 'failed' ? 'failed' : 'pending'),
            'amount' => is_int($d['amount'] ?? null) ? $d['amount'] : -1,
            'currency' => (string) ($d['currency'] ?? ''),
            'reference' => (string) ($d['reference'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function call(string $method, string $path, ?string $body): array
    {
        if ($this->secretKey === '') {
            throw new \RuntimeException('No Paystack key is configured');
        }
        $headers = ['Authorization: Bearer ' . $this->secretKey, 'Content-Type: application/json', 'Accept: application/json'];
        $res = ($this->http)($method, self::BASE . $path, $headers, $body);
        $json = json_decode($res['body'], true);
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($json)) {
            throw new \RuntimeException('Paystack answered with status ' . $res['status']);
        }
        return $json;
    }

    /** @param list<string> $headers @return array{status:int,body:string} */
    public static function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($out)) {
            throw new \RuntimeException('Could not reach Paystack');
        }
        return ['status' => $status, 'body' => $out];
    }
}
