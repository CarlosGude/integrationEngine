<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\Action;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Tests\Support\Fixtures\DeleteFixture\Request\DeleteFixtureAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Request\GetHelloWorldAction;
use IntegrationEngine\Tests\Support\Fixtures\GetNoMappedAction\Request\GetNoMappedAction;

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

    public static function deleteFixtureAction(): AbstractAction
    {
        return DeleteFixtureAction::create(
            method: 'DELETE',
            path: '/delete-fixture',
            body: null,
            authorization: null,
        );
    }

    public static function getNoMappedAction(): AbstractAction
    {
        return GetNoMappedAction::create(
            method: 'GET',
            path: '/get-not-mapped-action',
            body: null,
            authorization: null,
        );
    }
}
