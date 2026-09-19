<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use Psr\Clock\ClockInterface;

/**
 * Timestamped HMAC-SHA256 signature verifier for webhook requests.
 *
 * Implements Stripe's webhook signature scheme:
 * Format: `t={timestamp},v1={hash},v0={old_hash},...`
 *
 * - Verifies that the signature is recent (within tolerance window)
 * - Ignores v0 versions
 * - Supports multiple v1 versions for key rotation
 * - Uses PSR-20 ClockInterface for testability
 *
 * @author Carlos Gude
 */
final readonly class TimestampedHmacSignatureVerifier implements SignatureVerifierInterface
{
    public function __construct(
        private string $headerName,
        private int $timestampToleranceSeconds,
        private ClockInterface $clock,
    ) {}

    public function verify(string $body, string $signature, string $secret): bool
    {
        $timestamp = null;
        $providedHashes = [];

        foreach (explode(',', $signature) as $part) {
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $providedHashes[] = substr($part, 3);
            }
        }

        // is_numeric() also rejects a missing (null) timestamp.
        if ([] === $providedHashes || !is_numeric($timestamp) || !$this->isWithinTolerance((int) $timestamp)) {
            return false;
        }

        $timestampInt = (int) $timestamp;
        $expectedHash = hash_hmac('sha256', "{$timestampInt}.{$body}", $secret);

        foreach ($providedHashes as $providedHash) {
            if (hash_equals($expectedHash, $providedHash)) {
                return true;
            }
        }

        return false;
    }

    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    private function isWithinTolerance(int $timestamp): bool
    {
        $currentTime = (int) $this->clock->now()->format('U');

        return abs($currentTime - $timestamp) <= $this->timestampToleranceSeconds;
    }
}
