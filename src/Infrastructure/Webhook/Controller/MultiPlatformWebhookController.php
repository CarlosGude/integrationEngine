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
    public function ingest(Request $request, string $platform): JsonResponse
    {
        try {

            $platformConfig = $this->platformRegistry->detectPlatform(
                $request->headers->get('X-Platform'),
                $request->getPathInfo()
            );

            $body = $request->getContent();
            $signature = $request->headers->get(
                $platformConfig->verifier->getHeaderName()
            );

            if (null === $signature) {
                return new JsonResponse(
                    ['error' => \sprintf('Missing signature header: %s', $platformConfig->verifier->getHeaderName())],
                    400
                );
            }



            if (!$platformConfig->verifier->verify($body, $signature, '')) {
                return new JsonResponse(['error' => 'Invalid webhook signature'], 401);
            }


            return new JsonResponse(['status' => 'accepted'], 202);
        } catch (\DomainException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Webhook processing failed'], 500);
        }
    }
}
