<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureConfig;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Exception\WebhookRejectionReason;
use IntegrationEngine\Core\Exception\WebhookSignatureException;
use Psr\Clock\ClockInterface;

final readonly class TimestampedHmacSignatureVerifier implements SignatureVerifierInterface
{
    public function __construct(private ClockInterface $clock) {}

    public function verify(string $rawBody, array $headers, SignatureConfig $config): void
    {
        $signature = SignatureHeader::read($headers, $config);
        $timestamp = null;
        $hashes = [];
        foreach (explode(',', $signature) as $part) {
            if (str_starts_with($part, 't=')) {
                if (null !== $timestamp) {
                    throw new WebhookSignatureException(WebhookRejectionReason::HeaderMalformed);
                }
                $timestamp = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $hashes[] = substr($part, 3);
            }
        }
        if (null === $timestamp || 1 !== preg_match('/^(0|[1-9][0-9]*)$/D', $timestamp) || false === filter_var($timestamp, FILTER_VALIDATE_INT)) {
            throw new WebhookSignatureException(WebhookRejectionReason::HeaderMalformed);
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $config->secret);
        $matched = false;
        foreach ($hashes as $hash) {
            $matched = hash_equals($expected, $hash) || $matched;
        }
        if (!$matched) {
            throw new WebhookSignatureException(WebhookRejectionReason::SignatureInvalid);
        }
        if (null === $config->tolerance || abs($this->clock->now()->getTimestamp() - (int) $timestamp) > $config->tolerance) {
            throw new WebhookSignatureException(WebhookRejectionReason::TimestampOutOfTolerance);
        }
    }
}
