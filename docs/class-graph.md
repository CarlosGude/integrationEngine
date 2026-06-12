# Class Relationship Graph

Generated overview of the bundle's class relationships. Render with any
Mermaid-compatible viewer (GitHub, PhpStorm, mermaid.live).

## Core: contracts and engine

```mermaid
classDiagram
    direction TB

    class IntegrationEngine {
        -ConfigPort config
        -ClientInterface client
        -CachePort cache
        -string integrationName
        +send(actionName, context?, body?, headers?) ResponseInterface
        -sendWithDynamicAuth(action, auth, context, headers) ResponseInterface
        -withStaticToken(action, auth) AbstractAction
        -resolveToken(authConfig) string
        -applyMapper(action, rawResponse) ResponseInterface
    }

    class IntegrationRegistry {
        -array~string,IntegrationEngine~ integrations
        +register(name, integration) void
        +get(name) IntegrationEngine
        +has(name) bool
    }

    class IntegrationName {
        <<interface>>
        +NAME const
    }

    class AbstractAction {
        <<abstract>>
        -string method
        -string path
        -ActionBodyInterface body
        -AuthorizationConfig authorization
        +create(method, path, body?, authorization?)$ static
        +getPath(context?) string
        +getRawPath() string
        +getName()$ string*
        +hasResponse()$ bool*
        +mapper()$ string*
    }

    class AbstractMapper {
        <<abstract>>
        +getAction()$ string*
        +map(action, response)$ ResponseInterface
        #transform(action, response)$ ResponseInterface*
    }

    class ResponseInterface {
        <<interface>>
        +toArray() array
    }

    class EmptyResponse

    class ActionContextInterface {
        <<interface>>
        +create(data)$ self
        +toArray() array
    }

    class PathResolvableContextInterface {
        <<interface>>
        +resolvePath(path) string|null
    }

    class DefaultActionContext

    class ActionBodyInterface {
        <<interface>>
        +create(data)$ self
        +toArray() array
    }

    class GraphQLBodyInterface {
        <<interface>>
        +getQuery() string
        +getVariables() array
    }

    class RequestHeadersInterface {
        <<interface>>
        +toArray() array
    }

    class AuthorizationConfig {
        <<abstract>>
        +string type
        +fromArray(config)$ self
    }

    class StaticAuthorizationConfig {
        +array params
    }

    class DynamicAuthorizationConfig {
        +string action
        +string tokenField
        +int ttl
        +string header
        +string prefix
    }

    class ConfigPort {
        <<interface>>
        +getAction(name, bodyData) AbstractAction
    }

    class CachePort {
        <<interface>>
        +get(key) mixed
        +set(key, value, ttl) void
        +delete(key) void
    }

    class ClientInterface {
        <<interface>>
        +send(action, context?, headers?) array
    }

    class ClientAdapterInterface {
        <<interface>>
        +getClientType()$ string
        +requiresPath()$ bool
        +requiresMethod()$ bool
    }

    IntegrationRegistry o-- IntegrationEngine : registers by name
    IntegrationEngine --> ConfigPort : getAction()
    IntegrationEngine --> ClientInterface : send()
    IntegrationEngine --> CachePort : token cache
    IntegrationEngine --> AbstractMapper : applyMapper()
    IntegrationEngine --> EmptyResponse : when hasResponse() = false
    IntegrationEngine ..> DynamicAuthorizationConfig : detects
    IntegrationEngine ..> StaticAuthorizationConfig : rebuilds action with

    AbstractAction --> AuthorizationConfig : authorization
    AbstractAction --> ActionBodyInterface : body
    AbstractAction ..> ActionContextInterface : getPath(context)
    AbstractAction ..> PathResolvableContextInterface : custom path resolution
    AbstractAction ..> AbstractMapper : mapper() class-string

    AbstractMapper ..> AbstractAction : validates pairing
    AbstractMapper ..> ResponseInterface : returns

    EmptyResponse ..|> ResponseInterface
    DefaultActionContext ..|> ActionContextInterface
    PathResolvableContextInterface --|> ActionContextInterface
    GraphQLBodyInterface --|> ActionBodyInterface
    StaticAuthorizationConfig --|> AuthorizationConfig
    DynamicAuthorizationConfig --|> AuthorizationConfig
    ClientAdapterInterface --|> ClientInterface
```

## Infrastructure: adapters

```mermaid
classDiagram
    direction TB

    class ClientInterface {
        <<interface>>
    }
    class ClientAdapterInterface {
        <<interface>>
    }
    class ConfigPort {
        <<interface>>
    }
    class CachePort {
        <<interface>>
    }

    class SymfonyHttpClientAdapter {
        -HttpClientInterface httpClient
        -string baseUrl
        -array defaultHeaders
        +getClientType()$ "rest"
        +requiresPath()$ true
        +requiresMethod()$ true
    }

    class GraphQLClientAdapter {
        -HttpClientInterface httpClient
        -string endpointUrl
        -array defaultHeaders
        +getClientType()$ "graphql"
        +requiresPath()$ false
        +requiresMethod()$ false
    }

    class ResolvesAuthHeaders {
        <<trait>>
        #defaultAuthHeaders() array
        -resolveHeaders(action) array
    }

    class ClientAdapterResolver {
        -array~string,class-string~ adapters
        +register(clientType, adapterClass) void
        +resolve(clientType) string
        +all() array
    }

    class YamlConfigAdapter {
        -array config
        +getAction(name, bodyData) AbstractAction
    }

    class Psr6CacheAdapter {
        -CacheItemPoolInterface pool
        +get(key) mixed
        +set(key, value, ttl) void
        +delete(key) void
        -sanitizeKey(key) string
    }

    class RequestResponseException {
        +int statusCode
        +string context
    }

    SymfonyHttpClientAdapter ..|> ClientAdapterInterface
    GraphQLClientAdapter ..|> ClientAdapterInterface
    ClientAdapterInterface --|> ClientInterface
    SymfonyHttpClientAdapter --* ResolvesAuthHeaders : uses
    GraphQLClientAdapter --* ResolvesAuthHeaders : uses
    SymfonyHttpClientAdapter ..> RequestResponseException : throws
    GraphQLClientAdapter ..> RequestResponseException : throws
    ClientAdapterResolver o-- ClientAdapterInterface : type → class map
    YamlConfigAdapter ..|> ConfigPort
    Psr6CacheAdapter ..|> CachePort
```

## Bundle: wiring and generator

```mermaid
classDiagram
    direction TB

    class IntegrationEngineBundle {
        +build(container) void
    }

    class IntegrationEngineExtension {
        +load(configs, container) void
    }

    class Configuration {
        +getConfigTreeBuilder() TreeBuilder
    }

    class IntegrationCompilerPass {
        +process(container) void
    }

    class MakeIntegrationCommand {
        -string projectDir
        -IntegrationFileGenerator generator
        -ClientAdapterResolver adapterResolver
    }

    class IntegrationFileGenerator
    class IntegrationContext
    class TemplateRenderer
    class IntegrationConfigurationException

    IntegrationEngineBundle --> IntegrationCompilerPass : addCompilerPass()
    IntegrationEngineExtension --> Configuration : processConfiguration()
    IntegrationCompilerPass --> ClientAdapterResolver : register adapters
    IntegrationCompilerPass --> YamlConfigAdapter : one per integration
    IntegrationCompilerPass --> IntegrationEngine : one per integration
    IntegrationCompilerPass --> IntegrationRegistry : register(name, engine)
    IntegrationCompilerPass ..> IntegrationConfigurationException : throws
    MakeIntegrationCommand --> IntegrationFileGenerator : generates files
    MakeIntegrationCommand --> ClientAdapterResolver : resolve client type
    IntegrationFileGenerator --> TemplateRenderer : renders stubs
    IntegrationFileGenerator ..> IntegrationContext : consumes
```

## Exception hierarchy

```mermaid
classDiagram
    direction TB

    class InvalidArgumentException
    class RuntimeException
    class LogicException

    InvalidArgumentException <|-- ActionNotFoundException
    InvalidArgumentException <|-- IntegrationNotFoundException
    RuntimeException <|-- DynamicAuthException
    RuntimeException <|-- MapperActionMismatchException
    RuntimeException <|-- NotMappedActionException
    RuntimeException <|-- PathResolutionException
    RuntimeException <|-- RequestResponseException
    LogicException <|-- IntegrationConfigurationException
```

## Runtime data flow

```mermaid
flowchart LR
    A[Application service] --> B["IntegrationRegistry::get(NAME)"]
    B --> C[IntegrationEngine]
    C --> D["ConfigPort::getAction()"]
    D --> E{Dynamic auth?}
    E -- yes --> F["CachePort: token hit?"]
    F -- miss --> G[Token action via ClientInterface + Mapper]
    G --> H[Cache token + rebuild action as static auth]
    F -- hit --> H
    E -- no --> I["ClientInterface::send()"]
    H --> I
    I --> J{"hasResponse()?"}
    J -- no --> K[EmptyResponse]
    J -- yes --> L["AbstractMapper::map()"]
    L --> M[Typed ResponseInterface DTO]
```
