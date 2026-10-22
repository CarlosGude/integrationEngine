# Class relationship graph

This is a navigation aid, not a second architecture specification. The invariants, dependency rules and request flow live in [architecture.md](../../architecture.md); this page shows the main class relationships.

## Engine and ports

```mermaid
classDiagram
    class IntegrationEngine
    class ConfigPort
    class CachePort
    class ClientInterface
    class BatchClientInterface
    class DynamicBaseUrlClientInterface
    class ConnectionResolverInterface
    class EventDispatcherInterface
    class AbstractAction
    class AbstractMapper
    class ResponseInterface

    IntegrationEngine --> ConfigPort : resolves action
    IntegrationEngine --> ClientInterface : sends
    IntegrationEngine --> CachePort : dynamic auth/cache middleware
    IntegrationEngine --> ConnectionResolverInterface : optional runtime connection
    IntegrationEngine --> EventDispatcherInterface : lifecycle events
    IntegrationEngine --> AbstractAction
    AbstractAction ..> AbstractMapper : mapper()
    AbstractMapper ..> ResponseInterface : map()
    BatchClientInterface --|> ClientInterface : optional capability in implementations
    DynamicBaseUrlClientInterface ..> ClientInterface : optional capability in implementations
```

`BatchClientInterface` and `DynamicBaseUrlClientInterface` are capabilities detected at runtime; they do not replace `ClientInterface`.

## Built-in clients

```mermaid
classDiagram
    class ClientAdapterInterface
    class BatchClientInterface
    class DynamicBaseUrlClientInterface
    class SymfonyHttpClientAdapter
    class GraphQLClientAdapter
    class FormEncodedClientAdapter
    class RequestMiddlewareInterface

    SymfonyHttpClientAdapter ..|> ClientAdapterInterface
    SymfonyHttpClientAdapter ..|> BatchClientInterface
    SymfonyHttpClientAdapter ..|> DynamicBaseUrlClientInterface

    GraphQLClientAdapter ..|> ClientAdapterInterface
    GraphQLClientAdapter ..|> BatchClientInterface
    GraphQLClientAdapter ..|> DynamicBaseUrlClientInterface

    FormEncodedClientAdapter ..|> ClientAdapterInterface
    FormEncodedClientAdapter ..|> BatchClientInterface
    FormEncodedClientAdapter ..|> DynamicBaseUrlClientInterface
    FormEncodedClientAdapter --> SymfonyHttpClientAdapter : delegates transport

    SymfonyHttpClientAdapter --> RequestMiddlewareInterface : optional chain
    GraphQLClientAdapter --> RequestMiddlewareInterface : optional chain
```

The form adapter delegates to `SymfonyHttpClientAdapter`, so its request middleware and batch behavior follow the same transport path.

## Symfony bundle wiring

`IntegrationCompilerPass` creates one engine service per configured integration. The bundle wires the config adapter, selected client, cache adapter, optional connection resolver, middleware chains and lifecycle dispatcher dependencies. Protocol adapters are discovered through the `integration_engine.client_adapter` tag; request middleware uses its own bundle tag.

For exact configuration keys, use [configuration.md](../../configuration.md), not this graph.
