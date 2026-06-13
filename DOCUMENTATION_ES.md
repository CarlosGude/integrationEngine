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

Cuando llamas a `$engine->send(actionName, context, body, headers)`:

1. **Resolución de configuración** — lee el YAML, encuentra la entrada por nombre e
   instancia la clase de acción con método, path, body y autorización.
2. **Autorización** — si es auth dinámica, obtiene y cachea el token, luego reconstruye
   la acción con auth estática.
3. **Ejecución HTTP** — resuelve el path desde el contexto, construye las cabeceras,
   serializa el body, ejecuta la petición.
4. **Mapping** — valida `$mapper::getAction() === $action::class`, llama a `transform()`,
   devuelve un `ResponseInterface` tipado.

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

`DefaultActionContext` resuelve los tokens `{placeholder}` del path. Para query params
opcionales, implementa `PathResolvableContextInterface`.

```php
DefaultActionContext::create(['id' => 42]) // → /employees/42
```

→ [Contexto y resolución de path](docs/context-and-path.md) — params requeridos vs.
opcionales, contexto personalizado con validación, tabla de decisión.

---

## Mappers y responses

Un mapper transforma el array raw de la respuesta HTTP en un DTO tipado. Un mapper por acción.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
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
api\_key), configuración de auth dinámica, acción de token, caché, reintento 401, Redis.

---

## Peticiones en batch / paralelo

Usa `sendMany()` cuando necesitas N resultados antes de poder continuar. Devuelve una
`BatchResultCollection` — un `BatchResult` por clave, éxitos y fallos independientes.

```php
$results = $engine->sendMany([
    'alice' => EngineRequest::create(GetEmployeeAction::getName(), DefaultActionContext::create(['id' => 1])),
    'bob'   => EngineRequest::create(GetEmployeeAction::getName(), DefaultActionContext::create(['id' => 2])),
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
protocolos personalizados — usa `client_service:`.

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

→ [Clientes HTTP](docs/clients.md) — interfaz de body GraphQL, `client:` vs
`client_service:`, adaptadores de protocolo personalizados, `BatchClientInterface`.

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
