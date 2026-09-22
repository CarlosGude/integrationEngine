<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

final readonly class WebhookDefinition
{
    /** @param array<string, class-string<AbstractWebhookMapper>> $mappers */
    public function __construct(
        public string $typeField,
        public string $idField,
        public SignatureConfig $signature,
        public UnknownEventPolicy $unknownEvents,
        public array $mappers,
    ) {
        foreach ([$typeField, $idField] as $path) {
            if (1 !== preg_match('/^[^.\s]+(?:\.[^.\s]+)*$/D', $path)) {
                throw new \InvalidArgumentException('Webhook field paths must contain non-empty dot-separated keys.');
            }
        }
        foreach ($mappers as $eventType => $mapper) {
            if ('' === $eventType || !is_subclass_of($mapper, AbstractWebhookMapper::class)) {
                throw new \InvalidArgumentException('Webhook mapper must extend AbstractWebhookMapper.');
            }
            if ($mapper::eventType() !== $eventType) {
                throw new \InvalidArgumentException('Webhook event key must match mapper eventType().');
            }
        }
    }

    /** @return class-string<AbstractWebhookMapper>|null */
    public function mapperFor(string $eventType): ?string
    {
        return $this->mappers[$eventType] ?? null;
    }
}
