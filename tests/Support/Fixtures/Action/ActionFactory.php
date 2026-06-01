<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Support\Fixtures\Action;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AuthorizationConfig;
use IntegrationEngine\Tests\Support\FakeAuthorizationConfig;
use IntegrationEngine\Tests\Support\Fixtures\DeleteFixture\Request\DeleteFixtureAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorld\Request\GetHelloWorldAction;
use IntegrationEngine\Tests\Support\Fixtures\GetHelloWorldWithStaticAuthAction\Request\GetHelloWorldWithStaticAuthAction;
use IntegrationEngine\Tests\Support\Fixtures\GetMapperNotCorrespondsAction\Request\GetMapperNotCorrespondsAction;
use IntegrationEngine\Tests\Support\Fixtures\GetNoMappedAction\Request\GetNoMappedAction;
use IntegrationEngine\Tests\Support\Fixtures\GetNotValidMapperAction\Request\GetNotValidMapperAction;

final class ActionFactory
{
    public static function getHelloWorld(): AbstractAction
    {
        return GetHelloWorldAction::create(
            method: 'GET',
            path: '/hello-world',
        );
    }

    public static function deleteFixtureAction(): AbstractAction
    {
        return DeleteFixtureAction::create(
            method: 'DELETE',
            path: '/delete-fixture',
        );
    }

    public static function getNoMappedAction(): AbstractAction
    {
        return GetNoMappedAction::create(
            method: 'GET',
            path: '/get-not-mapped-action',
        );
    }

    public static function getNotValidMappedAction(): AbstractAction
    {
        return GetNotValidMapperAction::create(
            method: 'GET',
            path: '/get-not-valid-mapped-action',
        );
    }

    public static function getMapperNotCorrespondsAction(): AbstractAction
    {
        return GetMapperNotCorrespondsAction::create(
            method: 'GET',
            path: '/get-mapper-not-corresponds-action',
        );
    }

    public static function getGetHelloWorldWithStaticAuthAction(): AbstractAction
    {
        return GetHelloWorldWithStaticAuthAction::create(
            method: 'GET',
            path: '/get-hello-world-with-static-auth',
            authorization: FakeAuthorizationConfig::fromArray([
                AuthorizationConfig::TYPE => 'bearer',
                'token' => 'FAKE_TOKEN',
            ]),
        );
    }
}
