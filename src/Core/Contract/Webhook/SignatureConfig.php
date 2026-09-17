<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Configuration for webhook signature verification.
 *
 * @author Carlos Gude
 */
final readonly class SignatureConfig
{
    /**
     * Supported signature types.
     */
    private const SUPPORTED_TYPES = ['hmac_sha256', 'timestamped_hmac'];

    public function __construct(
        private string $type,
        private string $header,
        private ?int $timestampTolerance = null,
    ) {
        if (!\in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf(
                'Unsupported signature type "%s". Supported: %s',
                $type,
                implode(', ', self::SUPPORTED_TYPES),
            ));
        }

        if ('timestamped_hmac' === $type && null === $timestampTolerance) {
            throw new \InvalidArgumentException(
                'Signature type "timestamped_hmac" requires "timestamp_tolerance" to be set (in seconds).',
            );
        }

        if ('hmac_sha256' === $type && null !== $timestampTolerance) {
            throw new \InvalidArgumentException(
                'Signature type "hmac_sha256" does not support "timestamp_tolerance".',
            );
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getHeader(): string
    {
        return $this->header;
    }

    public function getTimestampTolerance(): ?int
    {
        return $this->timestampTolerance;
    }

    /**
     * Create from YAML config array.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $type = $config['type'] ?? null;
        if (!\is_string($type)) {
            throw new \InvalidArgumentException('Signature config must define a string "type".');
        }

        $header = $config['header'] ?? null;
        if (!\is_string($header)) {
            throw new \InvalidArgumentException('Signature config must define a string "header".');
        }

        $timestampTolerance = $config['timestamp_tolerance'] ?? null;
        if (null !== $timestampTolerance && !\is_int($timestampTolerance)) {
            throw new \InvalidArgumentException('Signature config "timestamp_tolerance" must be an integer.');
        }

        return new self($type, $header, $timestampTolerance);
    }
}
