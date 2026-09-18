<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Dispatch;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use IntegrationEngine\Core\Exception\NotMappedActionException;
use IntegrationEngine\Core\Response\EmptyResponse;

final class ResponseBuilder
{
    /**
     * @param array<mixed>                $body
     * @param array<string, list<string>> $headers
     */
    public function build(AbstractAction $action, array $body, array $headers): ResponseInterface
    {
        if (!$action::hasResponse()) {
            return new EmptyResponse();
        }

        return $this->applyMapper($action, $body, $headers);
    }

    /**
     * @param array<mixed>                $body
     * @param array<string, list<string>> $headers
     */
    private function applyMapper(AbstractAction $action, array $body, array $headers): ResponseInterface
    {
        $mapperClass = $action::mapper();

        if (null === $mapperClass) {
            throw new NotMappedActionException($action::getName());
        }

        if ($mapperClass::getAction() !== $action::class) {
            throw new MapperActionMismatchException(
                mapperClass: $mapperClass,
                expectedActionClass: $mapperClass::getAction(),
                actualActionClass: $action::class
            );
        }

        return $mapperClass::map($action, $body, $headers);
    }
}
