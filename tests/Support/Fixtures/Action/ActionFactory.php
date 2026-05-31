<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\Action;




use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Request\GetHelloWorldAction;


final class ActionFactory
{
    public static function getHelloWorld(): AbstractAction
    {
        return GetHelloWorldAction::create(
            method: 'GET',
            path: '/hello-world',
            body: null,
            authorization: null,
        );
    }
}