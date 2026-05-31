<?php

declare(strict_types=1);

namespace IntegrationEngine\Core\Contract;

use IntegrationEngine\Core\Exception\InvalidMethodException;

abstract readonly class AbstractAction
{
    /**
     * Returns the action name used as the first argument to Integration::send().
     *
     * Example:
     *   public static function getName(): string { return 'getOrders'; }
     *
     * Usage:
     *   $registry->get(AcmeErpIntegration::NAME)->send(GetOrdersAction::getName(), $body);
     */
    abstract public static function getName(): string;

    /**
     * Returns the fully qualified mapper class name for this action.
     * The mapper must declare this action class as its expected action.
     *
     * Example:
     *   public static function getMapper(): string { return GetOrdersMapper::class; }
     */
    abstract public static function getMapper(): string;

    public const string METHOD_GET    = 'GET';
    public const string METHOD_POST   = 'POST';
    public const string METHOD_PUT    = 'PUT';
    public const string METHOD_DELETE = 'DELETE';

    public const array METHODS = [
        self::METHOD_GET,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
    ];

    protected function __construct(
        protected string $method,
        protected string $path,
        protected ?ActionBodyInterface $body = null,
        protected ?AuthorizationConfig $authorization = null,
    ) {
    }

    /**
     * @throws InvalidMethodException
     */
    public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body = null,
        ?AuthorizationConfig $authorization = null,
    ): static {
        if (!in_array($method, self::METHODS, strict: true)) {
            throw new InvalidMethodException();
        }

        return new static(
            method: $method,
            path: $path,
            body: $body,
            authorization: $authorization,
        );
    }

    public function getMethod(): string                      { return $this->method; }
    public function getPath(): string                        { return $this->path; }
    public function getBody(): ?ActionBodyInterface          { return $this->body; }
    public function getAuthorization(): ?AuthorizationConfig { return $this->authorization; }
}
