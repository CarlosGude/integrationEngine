<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Core\Webhook;

use IntegrationEngine\Core\Webhook\WebhookEventState;
use IntegrationEngine\Core\Webhook\WebhookEventStateTransition;
use IntegrationEngine\Tests\Fake\WebhookEventAuditAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for webhook event state machine and audit trail.
 *
 * @author Carlos Gude
 */
final class WebhookEventStateTransitionTest extends TestCase
{
    private WebhookEventAuditAdapter $audit;

    protected function setUp(): void
    {
        $this->audit = new WebhookEventAuditAdapter();
    }

    public function testHappyPathTransitions(): void
    {
        $webhookId = 'webhook-123';
        $now = new \DateTimeImmutable();

        // Record the happy path: received → validating → processing → success
        $t1 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::RECEIVED,
            toState: WebhookEventState::VALIDATING,
            transitionAt: $now,
        );
        $this->audit->recordTransition($t1);

        $t2 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::VALIDATING,
            toState: WebhookEventState::PROCESSING,
            transitionAt: $now->modify('+1 second'),
        );
        $this->audit->recordTransition($t2);

        $t3 = WebhookEventStateTransition::success($webhookId, $now->modify('+2 seconds'));
        $this->audit->recordTransition($t3);

        // Verify final state
        self::assertSame(WebhookEventState::SUCCESS, $this->audit->getCurrentState($webhookId));

        // Verify history
        $history = $this->audit->getTransitionHistory($webhookId);
        self::assertCount(3, $history);
        self::assertSame(WebhookEventState::RECEIVED, $history[0]->fromState);
        self::assertSame(WebhookEventState::VALIDATING, $history[0]->toState);
        self::assertSame(WebhookEventState::SUCCESS, $history[2]->toState);
    }

    public function testFailureTransition(): void
    {
        $webhookId = 'webhook-456';
        $now = new \DateTimeImmutable();

        // received → processing → failed
        $t1 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::RECEIVED,
            toState: WebhookEventState::PROCESSING,
            transitionAt: $now,
        );
        $this->audit->recordTransition($t1);

        $t2 = WebhookEventStateTransition::failed(
            $webhookId,
            $now->modify('+1 second'),
            'Invalid payload format',
            ['error_code' => 'INVALID_JSON'],
        );
        $this->audit->recordTransition($t2);

        // Verify final state
        self::assertSame(WebhookEventState::FAILED, $this->audit->getCurrentState($webhookId));

        // Verify failure transition has metadata
        $history = $this->audit->getTransitionHistory($webhookId);
        $failureTransition = $history[1];
        self::assertSame('Invalid payload format', $failureTransition->reason);
        self::assertSame('INVALID_JSON', $failureTransition->metadata['error_code']);
    }

    public function testRetryAfterFailure(): void
    {
        $webhookId = 'webhook-789';
        $now = new \DateTimeImmutable();

        // Initial failure
        $t1 = WebhookEventStateTransition::failed(
            $webhookId,
            $now,
            'Temporary service error',
            ['attempt' => 1],
        );
        $this->audit->recordTransition($t1);

        // Retry transition
        $t2 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::FAILED,
            toState: WebhookEventState::RETRYING,
            transitionAt: $now->modify('+1 minute'),
            reason: 'Automatic retry',
            metadata: ['retry_attempt' => 1],
        );
        $this->audit->recordTransition($t2);

        // Successful retry
        $t3 = WebhookEventStateTransition::success($webhookId, $now->modify('+1 minute 5 seconds'));
        $this->audit->recordTransition($t3);

        // Verify final state
        self::assertSame(WebhookEventState::SUCCESS, $this->audit->getCurrentState($webhookId));

        // Verify history shows the retry path
        $history = $this->audit->getTransitionHistory($webhookId);
        self::assertCount(3, $history);
        self::assertSame(WebhookEventState::RETRYING, $history[1]->toState);
    }

    public function testStateIsTerminal(): void
    {
        self::assertFalse(WebhookEventState::RECEIVED->isTerminal());
        self::assertFalse(WebhookEventState::VALIDATING->isTerminal());
        self::assertFalse(WebhookEventState::PROCESSING->isTerminal());
        self::assertFalse(WebhookEventState::RETRYING->isTerminal());

        self::assertTrue(WebhookEventState::SUCCESS->isTerminal());
        self::assertTrue(WebhookEventState::FAILED->isTerminal());
    }

    public function testTransitionDescription(): void
    {
        $t1 = new WebhookEventStateTransition(
            webhookId: 'test',
            fromState: WebhookEventState::RECEIVED,
            toState: WebhookEventState::VALIDATING,
            transitionAt: new \DateTimeImmutable(),
        );
        self::assertSame('Received → Validating', $t1->description());

        $t2 = WebhookEventStateTransition::failed(
            'test',
            new \DateTimeImmutable(),
            'Signature mismatch',
        );
        self::assertStringContainsString('Failed', $t2->description());
        self::assertStringContainsString('Signature mismatch', $t2->description());
    }

    public function testGetTransitionsFromState(): void
    {
        $webhookId = 'webhook-filter-test';
        $now = new \DateTimeImmutable();

        // Create multiple transitions
        $t1 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::RECEIVED,
            toState: WebhookEventState::VALIDATING,
            transitionAt: $now,
        );
        $this->audit->recordTransition($t1);

        $t2 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::VALIDATING,
            toState: WebhookEventState::PROCESSING,
            transitionAt: $now->modify('+1 second'),
        );
        $this->audit->recordTransition($t2);

        $t3 = new WebhookEventStateTransition(
            webhookId: $webhookId,
            fromState: WebhookEventState::PROCESSING,
            toState: WebhookEventState::SUCCESS,
            transitionAt: $now->modify('+2 seconds'),
        );
        $this->audit->recordTransition($t3);

        // Query transitions from VALIDATING
        $fromValidating = $this->audit->getTransitionsFromState($webhookId, WebhookEventState::VALIDATING);
        self::assertCount(1, $fromValidating);
        self::assertSame(WebhookEventState::PROCESSING, $fromValidating[0]->toState);

        // Query transitions from PROCESSING
        $fromProcessing = $this->audit->getTransitionsFromState($webhookId, WebhookEventState::PROCESSING);
        self::assertCount(1, $fromProcessing);
        self::assertSame(WebhookEventState::SUCCESS, $fromProcessing[0]->toState);
    }

    public function testMultipleWebhooksIndependent(): void
    {
        $webhook1 = 'webhook-1';
        $webhook2 = 'webhook-2';
        $now = new \DateTimeImmutable();

        // Webhook 1: success path
        $t1 = new WebhookEventStateTransition(
            webhookId: $webhook1,
            fromState: WebhookEventState::RECEIVED,
            toState: WebhookEventState::SUCCESS,
            transitionAt: $now,
        );
        $this->audit->recordTransition($t1);

        // Webhook 2: failure path
        $t2 = new WebhookEventStateTransition(
            webhookId: $webhook2,
            fromState: WebhookEventState::RECEIVED,
            toState: WebhookEventState::FAILED,
            transitionAt: $now,
            reason: 'Invalid signature',
        );
        $this->audit->recordTransition($t2);

        // Verify independence
        self::assertSame(WebhookEventState::SUCCESS, $this->audit->getCurrentState($webhook1));
        self::assertSame(WebhookEventState::FAILED, $this->audit->getCurrentState($webhook2));
        self::assertCount(1, $this->audit->getTransitionHistory($webhook1));
        self::assertCount(1, $this->audit->getTransitionHistory($webhook2));
    }

    public function testUnknownWebhookIdReturnsNull(): void
    {
        self::assertNull($this->audit->getCurrentState('unknown-webhook'));
        self::assertEmpty($this->audit->getTransitionHistory('unknown-webhook'));
    }
}
