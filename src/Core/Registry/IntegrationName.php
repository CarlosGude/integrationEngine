<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Registry;

/**
 * Marker interface for classes that declare an integration NAME constant.
 *
 * Implement this in a dedicated class per integration to avoid magic strings:
 *
 *   final class AcmeErpIntegration implements IntegrationName
 *   {
 *       public const string NAME = 'acme_erp';
 *   }
 *
 *   // Usage:
 *   $registry->get(AcmeErpIntegration::NAME)->send(GetOrdersAction::ACTION, $body);
 */
interface IntegrationName
{
    public const  NAME = '__MUST_OVERRIDE__';
}
