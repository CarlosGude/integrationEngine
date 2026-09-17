<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract\Webhook;

/**
 * Typed webhook event DTO.
 *
 * Represents a parsed and mapped webhook payload from an external provider,
 * converted to a type-safe domain object.
 *
 * Must be serializable for passing through async message queues (Messenger).
 *
 * @author Carlos Gude
 */
interface WebhookEventInterface {}
