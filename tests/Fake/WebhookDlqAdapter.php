<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use IntegrationEngine\Core\Contract\Webhook\WebhookDlqPort;
use IntegrationEngine\Core\Webhook\WebhookFailure;

/**
 * In-memory webhook DLQ adapter for testing.
 *
 * @author Carlos Gude
 */
final class WebhookDlqAdapter implements WebhookDlqPort
{
    /** @var array<string, WebhookFailure> */
    private array $failures = [];

    /** @var array<string> IDs of resolved (removed) failures */
    private array $resolved = [];

    public function store(WebhookFailure $failure): void
    {
        $this->failures[$failure->id] = $failure;
    }

    public function findById(string $failureId): ?WebhookFailure
    {
        return $this->failures[$failureId] ?? null;
    }

    public function findUnresolved(): array
    {
        $unresolved = [];
        foreach ($this->failures as $failure) {
            if (!\in_array($failure->id, $this->resolved, true)) {
                $unresolved[] = $failure;
            }
        }

        // Sort by creation time (oldest first)
        usort($unresolved, static fn (WebhookFailure $a, WebhookFailure $b) => $a->createdAt <=> $b->createdAt);

        return $unresolved;
    }

    public function resolve(string $failureId): bool
    {
        if (!isset($this->failures[$failureId])) {
            return false;
        }

        $this->resolved[] = $failureId;

        return true;
    }

    public function recordRetry(WebhookFailure $failure): void
    {
        $this->failures[$failure->id] = $failure;
    }

    public function countUnresolved(): int
    {
        return \count($this->findUnresolved());
    }

    /**
     * Clear all failures (for testing).
     */
    public function clear(): void
    {
        $this->failures = [];
        $this->resolved = [];
    }
}
