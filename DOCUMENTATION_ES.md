# IntegrationEngine — Documentación

## Modelo mental

Una integración es un directorio. Un endpoint es un subdirectorio. Cada endpoint contiene
exactamente dos cosas: un lado de entrada y un lado de salida. Nada más puede dispersarse.

El engine impone esta estructura a nivel de framework — no es una convención de la que
puedas desviarte, sino el contrato.

---

## Ciclo de vida de una integración

1. Definir la integración (clase facade + constante `NAME`)
2. Configurarla (`integration_engine.yaml` + YAML del mapa de acciones)
3. Implementar cada acción (Action + Mapper + Response + DTO)
4. Usarla desde un servicio de aplicación a través del facade

---

## El pipeline del engine

Cuando llamas a `$engine->send(actionName, context, body, headers)`:

1. **Resolución de configuración.** `ConfigPort::getAction()` lee el YAML, encuentra la
   entrada por nombre e instancia la clase de acción con método, path, body y autorización.

2. **Autorización.** Si la acción lleva un `DynamicAuthorizationConfig`, el engine obtiene
   el token (desde caché o llamando a la acción de auth) y reconstruye la acción con un
   `StaticAuthorizationConfig` en su lugar.

3. **Ejecución HTTP.** `ClientInterface::send()` resuelve el path desde el contexto,
   construye las cabeceras (por defecto + auth + por petición), serializa el body y
   ejecuta la petición.

4. **Mapping.** El engine valida que `$mapper::getAction() === $action::class` y llama a
   `$mapper::transform()` para producir un `ResponseInterface` tipado.

Si `hasResponse()` devuelve `false`, los pasos 3 y 4 siguen ejecutándose, pero el paso 4
devuelve `EmptyResponse` sin invocar el mapper.

---

## Acciones

Las acciones son **stateless e inmutables**. Todas sus propiedades son `readonly`. El engine
las crea a través de `Action::create()` — nunca instancies una acción directamente.

```php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): ?string   { return GetEmployeeMapper::class; }
}
```

Los valores en runtime (parámetros de path, filtros, IDs de correlación) nunca van en el
constructor de la acción. Viajan a través de `ActionContextInterface`, `ActionBodyInterface`
o `RequestHeadersInterface`.

---

## Contexto

`ActionContextInterface::toArray()` devuelve un mapa clave-valor que el engine usa para
resolver los placeholders `{param}` del path.

**`DefaultActionContext`** es un wrapper transparente — úsalo en la gran mayoría de casos:

```php
DefaultActionContext::create(['id' => 42, 'page' => 2])
```

**Un contexto personalizado** tiene sentido cuando necesitas validación en el momento de
construcción, o cuando quieres aceptar objetos de dominio directamente en lugar de arrays:

```php
final readonly class GetEmployeeContext implements ActionContextInterface
{
    private function __construct(private int $id) {}

    public static function create(array $data): self
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new \InvalidArgumentException('El id del empleado debe ser un entero positivo.');
        }
        return new self(id: $id);
    }

    public function toArray(): array { return ['id' => $this->id]; }
}
```

Si te encuentras validando o casteando valores antes de pasárselos a
`DefaultActionContext::create()`, esa lógica debería estar en una clase de contexto personalizada.

---

## Resolución de paths

El engine resuelve el path en `AbstractAction::getPath()` usando una de tres estrategias:

### 1. Resolver por defecto — `{placeholder}` en YAML

`defaultResolvePath` aplica la regex `/\{(\w+)\}/` al string completo del path, incluida
cualquier porción de query string. Cada placeholder debe estar presente en el contexto o
lanzará `PathResolutionException::missingParameter`.

```yaml
GetEmployee:
    path: /employees/{id}          # siempre obligatorio

FilterByDepartment:
    path: /employees?dept={dept}   # solo cuando el parámetro es siempre obligatorio
```

### 2. `resolvePathCallback` — parámetros opcionales o computados

Sobreescribe este método cuando algún parámetro es opcional. Construyes el string del path
completamente dentro del callback:

```php
protected function resolvePathCallback(): ?callable
{
    return static function (string $path, ?ActionContextInterface $context): string {
        $data    = $context?->toArray() ?? [];
        $allowed = ['status', 'department', 'page'];
        $params  = array_filter(
            array_intersect_key($data, array_flip($allowed)),
            static fn(mixed $v): bool => '' !== (string) $v,
        );
        return empty($params) ? '/employees' : '/employees?' . http_build_query($params);
    };
}
```

### 3. Sin contexto

Si el path no tiene placeholders, no pases contexto o usa `DefaultActionContext::create([])`.

### Tabla de decisión

| Escenario | Estrategia |
|---|---|
| Segmento de path — siempre obligatorio | YAML `{placeholder}` |
| Query params — todos obligatorios | YAML `{placeholder}` en query string |
| Query params — alguno opcional | `resolvePathCallback` + `http_build_query` |
| Sin valores dinámicos | Sin contexto |

---

## Mappers

Un mapper transforma el array de respuesta crudo en un `ResponseInterface` tipado. El engine
valida que `$mapper::getAction() === $action::class` antes de llamar a `transform()` — esta
es una invariante estricta aplicada tanto en el engine como en `AbstractMapper::map()`.

```php
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return GetEmployeeResponse::create(
            employee: Employee::create($response),
        );
    }
}
```

**Un mapper por acción.** No puedes compartir un mapper entre dos clases de acción — el
engine lanzará `MapperActionMismatchException`. Cuando dos acciones devuelven la misma
forma de respuesta, extrae la lógica de transformación a una clase dedicada y delega desde
cada mapper:

```php
// Lógica compartida
final class EmployeeCollectionTransformer
{
    public static function transform(array $response): GetEmployeesResponse { ... }
}

// Cada mapper mantiene su propio getAction()
final class GetEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}

final class FilterEmployeesMapper extends AbstractMapper
{
    public static function getAction(): string { return FilterEmployeesAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return EmployeeCollectionTransformer::transform($response);
    }
}
```

---

## DTOs de respuesta

Los DTOs son clases `final readonly`. Reflejan la API externa — nombres de campos,
nullabilidad y tipos coinciden con lo que devuelve la API, no con lo que necesita tu dominio.

```php
final readonly class Employee
{
    private function __construct(
        public int    $id,
        public string $name,
        public string $department,
        public ?string $email,     // nullable cuando la API puede omitirlo
    ) {}

    public static function create(array $data): self
    {
        return new self(
            id:         (int)    ($data['id']         ?? 0),
            name:       (string) ($data['name']       ?? ''),
            department: (string) ($data['department'] ?? ''),
            email:      isset($data['email']) && is_string($data['email']) ? $data['email'] : null,
        );
    }

    public function toArray(): array { ... }
}
```

**Mapeo de tipos desde respuestas de la API:**

| Tipo en la API | Tipo PHP | Cast en `create()` |
|---|---|---|
| integer | `int` | `(int) ($data['x'] ?? 0)` |
| number | `float` | `(float) ($data['x'] ?? 0.0)` |
| boolean | `bool` | `(bool) ($data['x'] ?? false)` |
| string | `string` | `(string) ($data['x'] ?? '')` |
| array | `array` | `$data['x'] ?? []` |
| string nullable | `?string` | `is_string($data['x'] ?? null) ? $data['x'] : null` |
| objeto anidado (simple) | `array` | `$data['x'] ?? []` |
| objeto anidado (reutilizado) | `DtoClass` | `DtoClass::create($data['x'] ?? [])` |

Crea una clase DTO dedicada para objetos anidados que aparezcan en más de una respuesta,
o que tengan más de tres o cuatro campos. Usa `array` para objetos simples de un solo uso.

**`toArray()` es un contrato del engine, no tu API pública.** El engine lo usa internamente
para extraer tokens de auth dinámica. Los consumidores de tu facade deben acceder a las
propiedades tipadas directamente — nunca les pidas que llamen a `toArray()`.

---

## Autorización en detalle

### Estática

Se declara en la entrada YAML de la acción. El trait `ResolvesAuthHeaders` en los adaptadores
HTTP traduce la configuración a cabeceras:

| Tipo | Parámetros requeridos | Cabecera producida |
|---|---|---|
| `bearer` | `token`, `prefix` opcional (por defecto: `Bearer`) | `Authorization: Bearer {token}` |
| `basic` | `username`, `password` | `Authorization: Basic {base64}` |
| `api_key` | `token`, `header` (por defecto: `X-Api-Key`) | `{header}: {token}` |

### Dinámica

El engine ejecuta la acción de auth, extrae `token_field` de la respuesta mediante
`ResponseInterface::toArray()`, y cachea el resultado bajo la clave:

```
integration_engine.token.{integrationName}.{authActionName}
```

En llamadas posteriores dentro del TTL, el token cacheado se usa directamente. La acción
de auth no se vuelve a llamar hasta que la entrada de caché expire.

La acción de token es una acción normal. Requiere su propia `Action`, `Mapper` y
`Response`. El `toArray()` de la respuesta debe incluir el campo indicado en `token_field`:

```php
final readonly class FetchTokenResponse implements ResponseInterface
{
    public function __construct(public readonly string $accessToken) {}

    // 'access_token' debe coincidir con token_field en el bloque authorization del YAML
    public function toArray(): array { return ['access_token' => $this->accessToken]; }
}
```

---

## Comportamiento del caché

El `Psr6CacheAdapter` integrado envuelve cualquier `CacheItemPoolInterface`. Por defecto
usa `cache.app`.

Bajo PHP-FPM, `cache.app` es local al proceso (filesystem o APCu). Cada worker mantiene
su propio estado de caché. Con N workers, el endpoint de tokens se llamará hasta N veces
por ventana de TTL — una vez por worker en el primer arranque. Para la mayoría de APIs
esto es aceptable.

Para APIs con límites estrictos de tasa en el endpoint de tokens, configura un pool Redis
compartido:

```yaml
# config/packages/integration_engine.yaml
integration_engine:
    integrations:
        my_api:
            cache_service: 'cache.my_api_tokens'

# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.my_api_tokens:
                adapter: cache.adapter.redis
                provider: 'redis://localhost'
```

---

## Capa Anti-Corrupción

Los DTOs de integración nunca deben llegar a la capa de dominio. La traducción ocurre en
un servicio de aplicación que se sitúa entre el facade y el dominio:

```
Controller → ApplicationService → IntegrationFacade → Engine
                ↓
           DomainObject ← (la traducción ocurre aquí)
```

Si la API externa cambia un nombre de campo o tipo, solo el DTO, su mapper y el código de
traducción del servicio de aplicación necesitan cambiar. Los objetos y la lógica de dominio
no se ven afectados.

---

## Cliente HTTP personalizado

Usa `client_service` cuando necesites control total sobre la capa HTTP para una integración
concreta — lógica de reintentos, circuit breaking, logging personalizado o test doubles:

```yaml
my_api:
    client_service: 'App\Infrastructure\Http\RetryingHttpClient'
```

```php
final class RetryingHttpClient implements ClientInterface
{
    public function send(AbstractAction $action, ?ActionContextInterface $context = null, ...): array
    {
        // reintentos en 429, circuit break en 503, etc.
    }
}
```

`client:` selecciona un adaptador de protocolo registrado (rest, graphql, soap) — el bundle
gestiona el cableado. `client_service:` bypasea el sistema de adaptadores por completo e
inyecta tu servicio directamente como `ClientInterface`. Ambas opciones son mutuamente
excluyentes.