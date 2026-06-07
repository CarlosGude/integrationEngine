<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

interface GraphQLBodyInterface extends ActionBodyInterface
{
    /**
     * The GraphQL query or mutation string.
     * Return it inline or load it yourself from an external file:
     *
     *   return 'query { user { id } }';
     *   // or: return file_get_contents(__DIR__ . '/queries/get_user.graphql');
     */
    public function getQuery(): string;

    /**
     * Variables to pass alongside the query.
     *
     * @return array<string, mixed>
     */
    public function getVariables(): array;
}
