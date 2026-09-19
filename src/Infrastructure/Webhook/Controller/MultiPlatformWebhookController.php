<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Controller;

use IntegrationEngine\Infrastructure\Webhook\WebhookPlatformRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles webhook ingestion for multiple platforms.
 *
 * Routes by path (/webhooks/shopify, /webhooks/woocommerce) or
 * X-Platform header to the correct verifier and event registry.
 *
 * @deprecated since 5.3.2: it verifies and acknowledges webhooks but never
 *             dispatches them. Use IntegrationWebhookRequestParser with
 *             Symfony's Webhook component instead (see WEBHOOK.md).
 *
 * @author Carlos Gude
 */
final class MultiPlatformWebhookController
{
    public function __construct(
        private WebhookPlatformRegistry $platformRegistry,
    ) {}

    #[Route(
        path: '/webhooks/{platform}',
        methods: ['POST'],
        name: 'webhook_ingest',
        requirements: ['platform' => '(shopify|woocommerce)'],
    )]
    public function ingest(Request $request): JsonResponse
    {
        try {
            $platformConfig = $this->platformRegistry->detectPlatform(
                $request->headers->get('X-Platform'),
                $request->getPathInfo()
            );

            $body = $request->getContent();
            $headerName = $platformConfig->verifier->getHeaderName();
            $signature = $request->headers->get($headerName);

            return match (true) {
                null === $signature => new JsonResponse(
                    ['error' => \sprintf('Missing signature header: %s', $headerName)],
                    400
                ),
                // Verifying with an empty key would accept HMACs anyone can compute.
                '' === $platformConfig->secret => new JsonResponse(['error' => 'Webhook secret not configured for this platform'], 500),
                !$platformConfig->verifier->verify($body, $signature, $platformConfig->secret) => new JsonResponse(['error' => 'Invalid webhook signature'], 401),
                default => new JsonResponse(['status' => 'accepted'], 202),
            };
        } catch (\DomainException|\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception) {
            return new JsonResponse(['error' => 'Webhook processing failed'], 500);
        }
    }
}
