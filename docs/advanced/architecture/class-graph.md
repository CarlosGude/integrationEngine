# Class relationship graph

This is a navigation aid, not a second architecture specification. The invariants and dependency rules live in [ARCHITECTURE.md](../../ARCHITECTURE.md); this page shows the current major runtime relationships.

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

## Single request flow

```mermaid
flowchart LR
    A[send] --> B[ConfigPort / action]
    B --> C[resolve connection]
    C --> D[RequestSent]
    D --> E[host policy]
    E --> F[dynamic auth if configured]
    F --> G[client / middleware chain]
    G --> H[raw body + headers + status]
    H --> I[mapper]
    I --> J[ResponseMapped]
    J --> K[ResponseInterface]
    G -. failure .-> L[RequestFailed]
    I -. failure .-> L
```

`RequestSent` marks the prepared engine dispatch, not proof that bytes already reached the network. `RequestFailed` is emitted for failed execution/mapping paths handled by the engine.

## Batch flow

```mermaid
flowchart LR
    A[sendMany] --> B[prepare each EngineRequest]
    B --> C[group by resolved base URL]
    C --> D{BatchClientInterface?}
    D -- yes --> E[client sendMany]
    D -- no --> F[sequential client send]
    E --> G[map each result independently]
    F --> G
    G --> H[BatchResultCollection]
```

The three built-in clients take the batch path. Configured request middleware causes their adapters to use their own sequential fallback before returning results.

## Webhook path

```mermaid
flowchart LR
    A[raw HTTP request] --> B[IntegrationWebhookRequestParser]
    B --> C[verify signature on raw bytes]
    C --> D[decode JSON]
    D --> E[extract event type + id]
    E --> F[AbstractWebhookMapper]
    F --> G[MappedRemoteEvent]
    G --> H[application / remote-event transport]
```

IntegrationEngine v8 exposes the verified event ID and typed event but deliberately does not provide durable idempotency. That boundary belongs to the consuming application.

## Symfony bundle wiring

`IntegrationCompilerPass` creates one engine service per configured integration. The bundle wires the config adapter, selected client, cache adapter, optional connection resolver, middleware chains and lifecycle dispatcher dependencies. Protocol adapters are discovered through the `integration_engine.client_adapter` tag; request middleware uses its own bundle tag.

For exact configuration keys, use [HTTP clients](clients.md) and [DOCUMENTATION.md](../../DOCUMENTATION.md), not this graph.
