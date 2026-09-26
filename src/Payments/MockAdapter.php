<?php
declare(strict_types=1);

namespace Belis\Payments;

/**
 * A pretend payment provider for local development only. The customer is sent to /mock-pay/{reference}, a page
 * with buttons to simulate success, failure or walking away. Outcomes are kept in storage/mock-payments. It is
 * refused in production by the environment guard, and its routes answer 404 outside the local environment.
 * It proves the shop's own order logic. It proves nothing about Paystack.
 */
final class MockAdapter implements PaymentAdapter
{
    public function __construct(private readonly ?string $dir = null)
    {
    }

    public function initialize(string $email, int $amountPesewas, string $reference, string $callbackUrl): string
    {
        $this->write($reference, ['status' => 'pending', 'amount' => $amountPesewas, 'callback' => $callbackUrl]);
        return '/mock-pay/' . rawurlencode($reference);
    }

    public function verify(string $reference): array
    {
        $r = $this->read($reference);
        if ($r === null) {
            return ['status' => 'pending', 'amount' => 0, 'currency' => 'GHS', 'reference' => $reference];
        }
        return ['status' => (string) $r['status'], 'amount' => (int) $r['amount'], 'currency' => 'GHS', 'reference' => $reference];
    }

    /** @return array<string,mixed>|null */
    public function read(string $reference): ?array
    {
        $file = $this->file($reference);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($data) ? $data : null;
    }

    /** @param 'success'|'failed'|'pending' $status */
    public function settle(string $reference, string $status): void
    {
        $r = $this->read($reference);
        if ($r !== null && in_array($status, ['success', 'failed', 'pending'], true)) {
            $r['status'] = $status;
            $this->write($reference, $r);
        }
    }

    /** @param array<string,mixed> $data */
    private function write(string $reference, array $data): void
    {
        $file = $this->file($reference);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        file_put_contents($file, (string) json_encode($data), LOCK_EX);
    }

    private function file(string $reference): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $reference) ?? '';
        return ($this->dir ?? dirname(__DIR__, 2) . '/storage/mock-payments') . '/' . $safe . '.json';
    }
}
