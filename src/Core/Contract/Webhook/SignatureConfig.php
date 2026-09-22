<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

final readonly class SignatureConfig
{
    public function __construct(
        public SignatureType $type,
        public string $header,
        #[\SensitiveParameter] public string $secret,
        public ?int $tolerance = null,
        public ?string $prefix = null,
    ) {
        if ('' === trim($header) || '' === $secret) {
            throw new \InvalidArgumentException('Signature header and secret must not be empty.');
        }
        if (SignatureType::TimestampedHmac === $type && (null === $tolerance || $tolerance < 0)) {
            throw new \InvalidArgumentException('Timestamped HMAC requires a non-negative tolerance.');
        }
        if (SignatureType::TimestampedHmac !== $type && null !== $tolerance) {
            throw new \InvalidArgumentException('Only timestamped HMAC accepts tolerance.');
        }
        if (SignatureType::HmacSha256 !== $type && null !== $prefix) {
            throw new \InvalidArgumentException('Only HMAC-SHA256 accepts prefix.');
        }
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $type = $config['type'] ?? null;
        $header = $config['header'] ?? null;
        $secret = $config['secret'] ?? null;
        $tolerance = $config['tolerance'] ?? null;
        $prefix = $config['prefix'] ?? null;
        if (!\is_string($type) || null === ($type = SignatureType::tryFrom($type)) || !\is_string($header) || !\is_string($secret) || (null !== $tolerance && !\is_int($tolerance)) || (null !== $prefix && !\is_string($prefix))) {
            throw new \InvalidArgumentException('Invalid webhook signature configuration.');
        }

        return new self($type, $header, $secret, $tolerance, $prefix);
    }
}
