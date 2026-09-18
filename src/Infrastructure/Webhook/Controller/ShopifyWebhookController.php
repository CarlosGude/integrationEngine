<?php

declare(strict_types=1);

namespace IntegrationEngine\Infrastructure\Webhook\Controller;

use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use IntegrationEngine\Infrastructure\Webhook\Parser\ShopifyOrderCreatedParser;
use IntegrationEngine\Infrastructure\Webhook\Parser\ShopifyProductUpdatedParser;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

/**
 * Ingests Shopify webhook events.
 *
 * Routes inbound Shopify webhooks (products/update, orders/create, etc.)
 * to the correct parser, validates signatures, and dispatches domain events.
 *
 * @author Carlos Gude
 */
final class ShopifyWebhookController
{
    /**
     * Map Shopify topic → parser instance.
     *
     * @var array<string, class-string>
     */
    private const TOPIC_PARSER_MAP = [
        'products/update' => ShopifyProductUpdatedParser::class,
        'orders/create' => ShopifyOrderCreatedParser::class,
    ];

    public function __construct(
        private WebhookEventDispatcher $dispatcher,
        private string $shopifyWebhookSecret,
    ) {}

    #[Route('/webhooks/shopify', name: 'shopify_webhook', methods: ['POST'])]
    public function handleShopifyWebhook(Request $request): Response
    {
        $topic = $request->headers->get('X-Shopify-Topic');
        if (!$topic) {
            throw new BadRequestHttpException('Missing X-Shopify-Topic header');
        }

        if (!isset(self::TOPIC_PARSER_MAP[$topic])) {
            // Return 204 to acknowledge but don't process unknown events
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        $parserClass = self::TOPIC_PARSER_MAP[$topic];

        /** @var class-string<IntegrationWebhookRequestParser> $parserClass */
        $parser = new $parserClass($this->shopifyWebhookSecret);

        try {
            $remoteEvent = $parser->parse($request, $this->shopifyWebhookSecret);

            if (!$remoteEvent instanceof RemoteEvent) {
                // Parser rejected the event silently
                return new Response('', Response::HTTP_NO_CONTENT);
            }

            // Extract headers for mapper
            $headers = [];
            foreach ($request->headers->all() as $key => $headerValues) {
                $headers[$key] = reset($headerValues) ?: null;
            }

            $mapper = $parser->getMapper();
            $this->dispatcher->dispatch($remoteEvent, $mapper, $headers);

            return new Response('', Response::HTTP_NO_CONTENT);
        } catch (RejectWebhookException $e) {
            // Signature verification failed or malformed payload
            throw new BadRequestHttpException(
                $e->getMessage(),
                null,
                $e->getStatusCode(),
            );
        }
    }
}
