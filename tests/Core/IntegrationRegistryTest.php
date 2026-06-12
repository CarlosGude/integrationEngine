<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core;

use IntegrationEngine\Core\Exception\IntegrationNotFoundException;
use IntegrationEngine\Core\IntegrationEngine;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use IntegrationEngine\Tests\Fake\FakeCache;
use IntegrationEngine\Tests\Fake\FakeClient;
use IntegrationEngine\Tests\Fake\FakeConfigPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegrationRegistryTest extends TestCase
{
    // ── register + get ───────────────────────────────────────────────────────

    #[Test]
    public function getReturnsRegisteredEngine(): void
    {
        $registry = new IntegrationRegistry();
        $engine = $this->buildEngine('acme_erp');

        $registry->register('acme_erp', $engine);

        self::assertSame($engine, $registry->get('acme_erp'));
    }

    #[Test]
    public function registerOverwritesExistingName(): void
    {
        $registry = new IntegrationRegistry();
        $first = $this->buildEngine('acme_erp');
        $second = $this->buildEngine('acme_erp');

        $registry->register('acme_erp', $first);
        $registry->register('acme_erp', $second);

        self::assertSame($second, $registry->get('acme_erp'));
    }

    #[Test]
    public function getThrowsForUnknownIntegration(): void
    {
        $registry = new IntegrationRegistry();

        $this->expectException(IntegrationNotFoundException::class);
        $this->expectExceptionMessage('Integration "unknown" is not registered.');

        $registry->get('unknown');
    }

    // ── register name validation ─────────────────────────────────────────────

    #[Test]
    public function registerRejectsEmptyName(): void
    {
        $registry = new IntegrationRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('', $this->buildEngine(''));
    }

    #[Test]
    public function registerRejectsBlankName(): void
    {
        $registry = new IntegrationRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('   ', $this->buildEngine('   '));
    }

    #[Test]
    public function registerRejectsMustOverridePlaceholder(): void
    {
        $registry = new IntegrationRegistry();

        $this->expectException(\InvalidArgumentException::class);

        $registry->register('__MUST_OVERRIDE__', $this->buildEngine('__MUST_OVERRIDE__'));
    }

    // ── has ──────────────────────────────────────────────────────────────────

    #[Test]
    public function hasReturnsTrueForRegisteredIntegration(): void
    {
        $registry = new IntegrationRegistry();
        $registry->register('acme_erp', $this->buildEngine('acme_erp'));

        self::assertTrue($registry->has('acme_erp'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownIntegration(): void
    {
        $registry = new IntegrationRegistry();

        self::assertFalse($registry->has('unknown'));
    }

    private function buildEngine(string $name): IntegrationEngine
    {
        return new IntegrationEngine(new FakeConfigPort(), new FakeClient(), new FakeCache(), $name);
    }
}
