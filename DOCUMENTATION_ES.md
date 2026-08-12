# IntegrationEngine — Documentación

## Modelo mental

Una integración es un directorio. Un endpoint es un subdirectorio. Cada endpoint contiene
exactamente dos cosas: un lado de entrada y un lado de salida. Nada más puede dispersarse.

El engine impone esta estructura a nivel de framework — no es una convención de la que
puedas desviarte, sino el contrato.

---

## Ciclo de vida de una integración

El punto de partida recomendado es el comando de scaffolding — genera el facade, el YAML
de acciones, el `Action`, el `Mapper` y el `Response` con la estructura y los namespaces
correctos, dejando solo `transform()` y los campos del DTO por rellenar:

```bash
php bin/console make:integration MyApi GetEmployee
```

1. **Scaffold** — ejecutar `make:integration` para generar el esqueleto
2. **Configurar** — establecer `base_url` y `config_path` en `integration_engine.yaml`
3. **Implementar** — rellenar `transform()` en el mapper y los campos del DTO en la response
4. **Usar** — llamar al facade desde un servicio de aplicación

---

## El pipeline del engine

Cuando llamas a `$engine->send(actionName, context, body, headers, baseUrl, connection)`:

1. **Resolución de configuración** — lee el YAML, encuentra la entrada por nombre,
   resuelve los `{placeholder}` que el body pueda aportar, e instancia la clase de
   acción con método, path, body y autorización.
2. **Resolución de conexión** — si se pasó `connection`, la resuelve a
   `ConnectionCredentials` y aplica el override de `base_url`/autorización si lo hay.
3. **Autorización** — si es auth dinámica, obtiene y cachea el token (namespaced por
   conexión), luego reconstruye la acción con auth estática.
4. **Ejecución HTTP** — resuelve los placeholders de path restantes desde el contexto,
   construye las cabeceras, serializa el body, ejecuta los request middlewares (si hay
   configurados), ejecuta la petición.
5. **Mapping** — valida `$mapper::getAction() === $action::class`, llama a `transform()`
   con el body y las cabeceras de la respuesta, devuelve un `ResponseInterface` tipado.

---

## Acciones

Una acción declara un endpoint: método HTTP, path, mapper. Sin lógica, sin estado.

```php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return GetEmployeeMapper::class; }
}
```

```yaml
GetEmployee:
    action: App\...\GetEmployeeAction
    method: GET
    path:   /employees/{id}
```

→ [Acciones en profundidad](docs/actions.md) — todas las opciones YAML, `hasResponse: false`,
la invariante de statelessness.

---

## Contexto y parámetros de path

Los tokens `{placeholder}` del path se resuelven desde dos fuentes, en orden de
prioridad: el **body** de la acción primero (declara `body:` en la acción — sin clase
extra), luego el **contexto** para lo que el body no aporte. Para query params
opcionales, implementa `PathResolvableContextInterface`.

```php
// Desde el body — sin contexto necesario:
$engine->send('UpdateEmployee', body: UpdateEmployeeBody::create(['id' => 42, 'name' => 'Ada']));

// Desde el contexto — para valores que no forman parte del body:
DefaultActionContext::create(['id' => 42]) // → /employees/42
```

→ [Contexto y resolución de path](docs/context-and-path.md) — placeholders resueltos
desde el body, params requeridos vs. opcionales, contexto personalizado con
validación, tabla de decisión.

---

## Mappers y responses

Un mapper transforma el array raw de la respuesta HTTP en un DTO tipado. Un mapper por acción.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return GetEmployeeResponse::create($response);
    }
}
```

```php
final readonly class GetEmployeeResponse implements ResponseInterface
{
    public function __construct(public int $id, public string $name) {}
    public static function create(array $data): self { ... }
    public function toArray(): array { ... }
}
```

→ [Mappers y responses](docs/mappers-and-responses.md) — tabla de tipos, DTOs anidados,
lógica de mapper compartida, el contrato `toArray()`.

---

## Autorización

Declara la auth en la entrada YAML de cada acción. El engine gestiona la inyección de
cabeceras, la obtención del token, el caché y los reintentos en 401 de forma automática.

```yaml
GetOrders:
    authorization:
        type:  bearer
        token: '%env(MY_API_TOKEN)%'
```

Para OAuth 2.0 o tokens de sesión, usa `type: dynamic` — el engine llama a la acción de
token, cachea el resultado y lo inyecta de forma transparente:

```yaml
GetOrders:
    authorization:
        type:        dynamic
        action:      FetchToken
        token_field: access_token
        ttl:         3600
```

→ [Autorización](docs/authorization.md) — todos los tipos estáticos (bearer, basic,
api\_key), configuración de auth dinámica, acción de token, caché (incluido el
aislamiento por conexión en integraciones multi-conexión), reintento 401, Redis.

---

## Peticiones en batch / paralelo

Usa `sendMany()` cuando necesitas N resultados antes de poder continuar. Devuelve una
`BatchResultCollection` — un `BatchResult` por clave, éxitos y fallos independientes.

```php
$results = $engine->sendMany([
    'alice' => new EngineRequest(GetEmployeeAction::getName(), context: DefaultActionContext::create(['id' => 1])),
    'bob'   => new EngineRequest(GetEmployeeAction::getName(), context: DefaultActionContext::create(['id' => 2])),
]);

$results['alice']->isSuccess();  // bool
$results['alice']->response();   // ResponseInterface
$results['alice']->error();      // \Throwable|null
```

La concurrencia real es independiente del protocolo — depende de si el cliente implementa
`BatchClientInterface`. El cliente REST por defecto lo implementa.

→ [Peticiones en batch / paralelo](docs/batch-requests.md) — estrategias de fallo,
`sendManyOrFail()`, concurrencia por tipo de cliente, `AbstractBatchMapper` para batches
homogéneos, batches de acciones mixtas.

---

## Clientes HTTP

El cliente `rest` por defecto gestiona APIs REST estándar sin configuración. Usa
`client: graphql` para GraphQL. Para control total — reintentos, circuit breaking,
protocolos personalizados — usa `client_service:`. Todo cliente devuelve
`array{body, headers}` — el body decodificado más las cabeceras de la respuesta,
propagadas hasta el mapper.

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

→ [Clientes HTTP](docs/clients.md) — interfaz de body GraphQL, `client:` vs
`client_service:`, adaptadores de protocolo personalizados, `BatchClientInterface`.

---

## Resolución de conexión en runtime

Para una integración que sirve varias conexiones en runtime (multi-tenant: una
tienda/cuenta por cliente) con distinto `base_url` y/o credenciales, configura un
`connection_resolver` en vez de construir una `IntegrationEngine` por conexión:

```yaml
my_api:
    connection_resolver: App\Infrastructure\Integrations\MyApi\MyApiConnectionResolver
```

```php
$engine->send('get_orders', connection: $tenantId);
```

→ [Clientes HTTP — resolución de conexión en runtime](docs/clients.md#runtime-connection-resolution--connectionresolverinterface) —
`ConnectionResolverInterface`, `ConnectionCredentials`, el discriminador de cache de
tokens dinámicos para conexiones que comparten un `base_url`.

---

## Request middleware — firma de la request completa

Para proveedores que firman la request completa (OAuth 1.0a, AWS SigV4) en vez de una
credencial estática, implementa `RequestMiddlewareInterface` — se ejecuta sobre la
request ya resuelta, justo antes de la llamada HTTP:

```yaml
my_api:
    request_middlewares:
        - App\Infrastructure\Integrations\MyApi\OAuth1SigningMiddleware
```

→ [Clientes HTTP — request middleware](docs/clients.md#request-middleware--full-request-signing) —
el value object `Request`, la semántica de la cadena, por qué `sendMany()` pasa a
despacho secuencial cuando hay middlewares configurados.

---

## Capa Anti-Corrupción

Los DTOs de integración nunca deben llegar a la capa de dominio. La traducción ocurre en
un servicio de aplicación:

```
Controller → ApplicationService → IntegrationFacade → Engine
                ↓
           DomainObject ← (la traducción ocurre aquí)
```

Si la API externa cambia un nombre de campo o tipo, solo el DTO, su mapper y el código de
traducción del servicio de aplicación necesitan cambiar. Los objetos y la lógica de dominio
no se ven afectados.
