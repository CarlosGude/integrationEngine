<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Exception;

final class RequestResponseException extends \RuntimeException
{
    public const ERROR = 'REQUEST_RESPONSE_ERROR';

    public function __construct(
        public readonly int $statusCode,
        public readonly string $context,
    ) {
        parent::__construct(self::ERROR.': '.$context, 0);
    }
}