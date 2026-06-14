<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Fake;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

final class FakeLogger implements LoggerInterface
{
    use LoggerTrait;

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $logs = [];

    /** @param array<string, mixed> $context */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasEntry(string $level, string $messageContains): bool
    {
        foreach ($this->logs as $entry) {
            if ($entry['level'] === $level && str_contains($entry['message'], $messageContains)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{level: string, message: string, context: array<string, mixed>}> */
    public function all(): array
    {
        return $this->logs;
    }
}
