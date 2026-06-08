# IntegrationEngine Bundle — Documentación

> **Guía de lectura**
>
> - **¿Empiezas desde cero?** Ve al [Quick start](#quick-start) y a [Cómo encaja todo](#1-cómo-encaja-todo). Con eso es suficiente para tener una integración funcionando.
> - **¿Configurando una integración?** Ve a [Configuración YAML](#5-configuración-yaml) o a [Autorización](#9-sistema-de-autorización).
> - **¿Buscas algo concreto?** Usa la [tabla de contenidos](#tabla-de-contenidos).
> - **¿Extendiendo o modificando el bundle?** Ve directamente a [Internals del bundle](#14-internals-del-bundle) — está escrita para otra audiencia y asume que conoces el resto.

---

## Tabla de contenidos

| Sección | Qué cubre | Para quién |
|---------|-----------|------------|
| [Quick start](#quick-start) | Instalar, generar, llamar | Todo el mundo |
| [1. Cómo encaja todo](#1-cómo-encaja-todo) | El stack completo y el patrón ACL | Todo el mundo |
| [2. Filosofía](#2-filosofía) | Principios de diseño y arquitectura | Cuando quieres entender el porqué |
| [3. Scaffolding](#3-scaffolding) | Referencia de `make:integration` | Al añadir una integración o acción |
| [4. Estructura de directorios](#4-estructura-de-directorios) | Layout generado y convenciones | Al navegar código desconocido |
| [5. Configuración YAML](#5-configuración-yaml) | Los dos ficheros de config explicados | Al configurar transporte, auth, headers |
| [6. Actions](#6-actions) | Qué declara una Action y por qué | Al escribir o depurar una acción |
| [7. Sistema de contexto](#7-sistema-de-contexto) | Parámetros dinámicos de ruta | Al usar `{placeholders}` en paths |
| [8. Sistema de body](#8-sistema-de-body) | Payloads y bodies GraphQL | Al enviar POST/PUT/PATCH o GraphQL |
| [9. Sistema de autorización](#9-sistema-de-autorización) | Auth estática y dinámica | Al configurar autenticación |
| [10. Sistema de headers](#10-sistema-de-headers) | Precedencia de headers en tres capas | Al depurar problemas de headers |
| [11. API del engine](#11-api-del-engine) | Firma de `send()` y tipo de retorno | Referencia rápida |
| [12. La frontera de respuesta](#12-la-frontera-de-respuesta) | Qué significa `ResponseInterface` | Al diseñar mappers y DTOs |
| [13. Extensibilidad](#13-extensibilidad) | Adaptadores, cache y config personalizados | Al reemplazar infraestructura |
| [14. Internals del bundle](#14-internals-del-bundle) | Boot sequence, DI, puntos de extensión | Contribuidores y autores de librerías |
| [15. Referencia de errores](#15-referencia-de-errores) | Cada excepción y qué hacer | Cuando algo falla |

---

## Quick start

### 1. Instalar

```bash
composer require carlosgude/integration-engine
```

Si Symfony Flex no registra el bundle automáticamente, añádelo manualmente en
`config/bundles.php`:

```php
return [
    // ...
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

### 2. Generar tu primera integración

Un solo comando crea todo — config, clases y YAML:

```bash
php bin/console make:integration DummyRestApi GetEmployees
```

El comando pregunta en la primera ejecución:

1. **Base URL**: `https://dummy.restapiexample.com`
2. **Path**: `/api/v1/employees`
3. **Método HTTP**: `GET`

Archivos generados:

```
config/packages/integration_engine.yaml
src/Infrastructure/Integrations/DummyRestApi/
    DummyRestApiIntegration.php
    DummyRestApi.yaml
    GetEmployees/
        Request/GetEmployeesAction.php
        Response/GetEmployeesMapper.php
        Response/GetEmployeesResponse.php
```

Ejecuta el mismo comando para añadir más acciones — detecta lo que ya existe:

```bash
php bin/console make:integration DummyRestApi GetEmployee
# > Path: /api/v1/employee/{id}
# > Method: GET
# → crea los ficheros de GetEmployee, añade entrada a DummyRestApi.yaml
# → omite DummyRestApiIntegration.php (ya existe)
```

### 3. Llamarlo

```php
$registry->get(DummyRestApiIntegration::NAME)->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 1]),
);
```

Eso es todo lo que el bundle requiere. El resto de este documento es profundidad opcional.

### 4. Verlo en acción

Una aplicación Symfony funcionando contra la [Dummy REST API](https://dummy.restapiexample.com) pública:

**[github.com/CarlosGude/integrationEngine-use-example](https://github.com/CarlosGude/integrationEngine-use-example)**

Clónala, ejecuta `composer install` y `symfony server:start` — sin base de datos, sin variables de entorno. Es el punto de partida recomendado antes de seguir leyendo.

---

## 1. Cómo encaja todo

El bundle genera la capa de integración. Esta sección muestra qué construyes encima
— y más importante, qué tienes que mantener separado.

### El stack completo

```
API externa
  → Action          declara la petición (método, path, mapper)
  → Mapper          transforma la respuesta cruda en un DTO de integración
  → Fachada de integración   expone métodos tipados, oculta el engine
  → Servicio de aplicación   traduce el DTO de integración a un objeto de dominio
  → Controlador / Comando / Procesador de cola / ...
```

Cada capa conoce solo la capa inmediatamente inferior. **El dominio nunca importa
nada de `IntegrationEngine\` ni de tus clases de integración.**

### Qué tienes que escribir realmente

La mayoría de las integraciones solo requieren tres ficheros — el scaffolding los genera todos:

| Cuándo | Qué |
|--------|-----|
| Siempre | Action, Mapper, Response DTO |
| A veces | Context (params de ruta dinámicos), Body (payload de la petición) |
| Raramente | Auth personalizada, cache personalizada, adaptador HTTP personalizado |

### La fachada de integración

La clase `DummyRestApiIntegration` generada resuelve el engine una vez y expone
métodos tipados — uno por acción:

```php
final class DummyRestApiIntegration
{
    public const string NAME = 'dummy_rest_api';

    private IntegrationEngine $engine;

    public function __construct(IntegrationRegistry $registry)
    {
        $this->engine = $registry->get(self::NAME);
    }

    public function getEmployee(int $id): GetEmployeeResponse
    {
        $response = $this->engine->send(
            actionName: GetEmployeeAction::getName(),
            context: DefaultActionContext::create(['id' => $id]),
        );

        \assert($response instanceof GetEmployeeResponse);
        return $response;
    }
}
```

`GetEmployeeResponse` es un DTO de integración — refleja la API externa, no tu dominio.

### El servicio de aplicación

La traducción del DTO de integración al objeto de dominio pertenece a un servicio de
aplicación. No al controlador, no al dominio:

```php
final class EmployeeService
{
    public function __construct(
        private readonly DummyRestApiIntegration $integration,
    ) {}

    public function getEmployee(int $id): Employee
    {
        $dto = $this->integration->getEmployee($id);

        return new Employee(
            id:     $dto->id,
            name:   $dto->employeeName,
            salary: $dto->employeeSalary,
        );
    }
}
```

El controlador depende solo de `EmployeeService` y trabaja exclusivamente con objetos
de dominio:

```php
#[Route('/employees/{id}')]
public function show(int $id): JsonResponse
{
    return $this->json($this->employeeService->getEmployee($id));
}
```

### Qué no hacer

```php
// ❌ El dominio ahora depende de un DTO de infraestructura
return Employee::fromDummyEmployee($dummyEmployee);
```

Si `Employee` sabe qué es un `GetEmployeeResponse`, el dominio tiene una dependencia
en la capa de integración. Cuando la API externa cambia, el cambio se propaga al
dominio. Este es el patrón **Anti-Corruption Layer** — el bundle garantiza el lado
de la integración; mantener el DTO fuera del dominio es tu responsabilidad.

---

![IntegrationEngine — visión general](./docs/diagrams/01-overview.svg)

---

## 2. Filosofía

### El bundle propone, no impone

IntegrationEngine define contratos. Lo que construyes encima es completamente tuyo.

- Usa `DefaultActionContext` para params simples, o implementa `ActionContextInterface` para validación y lógica de dominio.
- Declara auth en YAML para casos simples, o centralízala en una clase base de acción.
- Usa el scaffold generado tal cual, o extiéndelo con value objects y colecciones tipadas.
- Reemplaza cualquier componente de infraestructura — cliente, cache, fuente de config — con una sola clave de config.

El bundle nunca ve más allá de `AbstractAction`, `ActionContextInterface` y `ResponseInterface`. Todo lo demás es tu dominio.

### Principios de diseño

- Sin magia fuera del engine
- Las actions son inmutables y sin estado
- El contexto es explícito y se valida en tiempo de resolución
- Los bodies son objetos tipados
- El mapping es explícito mediante mappers
- Los headers tienen una precedencia definida: YAML → auth → caller
- El call site es uniforme independientemente de la complejidad de la integración
- La frontera de respuesta es una Anti-Corruption Layer

### Visión general de la arquitectura

```
Core             contratos + lógica del engine    sin dependencias de framework
Infrastructure   adaptadores HTTP, YAML y cache   implementa los puertos de Core
Bundle           cableado Symfony                 DI, compiler pass, scaffolding
```

### Clases base de integración

Si un grupo de acciones comparte auth, un prefijo de ruta o headers comunes, extráelo
en una clase abstracta entre `AbstractAction` y tus acciones concretas:

```php
abstract class StripeAction extends AbstractAction
{
    public static function create(
        string $method,
        string $path,
        ?ActionBodyInterface $body = null,
    ): static {
        return parent::create(
            method: $method,
            path: '/v1'.$path,
            body: $body,
            authorization: new StaticAuthorizationConfig(
                type: 'bearer',
                params: ['token' => '%env(STRIPE_SECRET_KEY)%'],
            ),
        );
    }
}
```

Cada acción concreta solo declara lo que la hace única:

```php
final class CreateChargeAction extends StripeAction
{
    public static function getName(): string   { return 'CreateCharge'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string    { return CreateChargeMapper::class; }
}
```

| Nivel | Clase | Responsabilidad |
|-------|-------|----------------|
| Bundle | `AbstractAction` | Contrato: método, path, auth, mapper |
| Integración | `StripeAction` | Config compartida: auth, prefijo, defaults |
| Operación | `CreateChargeAction` | Identidad: nombre, respuesta, mapper |

Usa uno, dos o los tres niveles — el bundle funciona igual de cualquier manera.

---

## 3. Scaffolding

```bash
php bin/console make:integration {NombreIntegración} {NombreAcción}
```

El argumento `NombreAcción` es opcional — el comando lo pregunta de forma interactiva si se omite.

### Qué hace el comando

| Paso | REST | GraphQL |
|------|------|---------|
| Primera ejecución | Pide base URL + tipo de cliente | Pide base URL + tipo de cliente |
| Primera ejecución | Pide nombre de la primera acción | Pide nombre de la primera acción |
| Solo REST | Pide path y método HTTP | — siempre `POST /graphql` |
| Siempre | Crea `{Nombre}Integration.php` | Crea `{Nombre}Integration.php` |
| Siempre | Crea Action, Mapper, Response | Crea Action, Mapper, Response |
| Siempre | Añade entrada a `{Nombre}.yaml` | Añade entrada a `{Nombre}.yaml` |

> `DELETE` no genera Mapper ni Response — `hasResponse` se establece a `false`.
> Las acciones GraphQL siempre tienen `hasResponse: true`.
> `HEAD` y `OPTIONS` no están soportados por el scaffolding.

### Crear integraciones manualmente

Si creas una clase de integración a mano, debes sobrescribir la constante `NAME`:

```php
final class MyApiIntegration implements IntegrationName
{
    public const string NAME = 'my_api'; // debe declararse explícitamente
}
```

La interfaz declara `NAME = '__MUST_OVERRIDE__'` como valor centinela. PHP no obliga
a sobrescribir constantes — si no se declara `NAME`, la integración se registra bajo
`'__MUST_OVERRIDE__'` y no resolverá correctamente.

---

## 4. Estructura de directorios

Todas las integraciones siguen el mismo layout. Entenderlo una vez significa poder
navegar cualquier integración sin abrir un fichero.

### Estructura generada

```
config/
└── packages/
    └── integration_engine.yaml          ← config de transporte (base_url, cache, headers)

src/Infrastructure/Integrations/
└── DummyRestApi/
    ├── DummyRestApiIntegration.php      ← fachada: expone métodos tipados, oculta el engine
    ├── DummyRestApi.yaml                ← registro de acciones: mapea nombres a clases
    └── GetEmployee/
        ├── Request/
        │   └── GetEmployeeAction.php    ← declara la petición (método, path, mapper)
        └── Response/
            ├── GetEmployeeMapper.php    ← transforma array crudo → DTO de integración
            └── GetEmployeeResponse.php  ← DTO de integración (refleja la API externa)
```

### Por qué este layout

| Nivel | Directorio | Contiene |
|-------|------------|----------|
| Integración | `DummyRestApi/` | Todo lo de un proveedor externo |
| Fachada | `DummyRestApiIntegration.php` | API pública tipada — un método por acción |
| Acción | `GetEmployee/` | Todo lo de una operación |
| Request | `Request/` | La clase action — qué enviar |
| Response | `Response/` | El mapper y el DTO — qué hacer con la respuesta |

La separación `Request/Response` refleja el contrato HTTP y facilita la depuración:
mapping roto → ve a `Response/`. Path incorrecto → ve a `Request/`.

### DELETE — sin capa de respuesta

```
└── DeleteEmployee/
    └── Request/
        └── DeleteEmployeeAction.php    ← hasResponse(): false, mapper(): null
```

El engine devuelve `EmptyResponse` y el caller lo descarta.

### GraphQL — misma estructura, body diferente

```
└── GetUser/
    ├── Request/
    │   ├── GetUserAction.php
    │   └── GetUserBody.php             ← implementa GraphQLBodyInterface
    └── Response/
        ├── GetUserMapper.php
        └── GetUserResponse.php
```

La clase body no se genera automáticamente — el query string es específico de la
implementación. Escríbelo una vez y no cambia.

### Convenciones de nombres

| Fichero | Patrón | Ejemplo |
|---------|--------|---------|
| Action | `{NombreAcción}Action.php` | `GetEmployeeAction.php` |
| Mapper | `{NombreAcción}Mapper.php` | `GetEmployeeMapper.php` |
| Response | `{NombreAcción}Response.php` | `GetEmployeeResponse.php` |
| Body | `{NombreAcción}Body.php` | `CreateEmployeeBody.php` |
| Fachada | `{NombreIntegración}Integration.php` | `DummyRestApiIntegration.php` |
| Registro YAML | `{NombreIntegración}.yaml` | `DummyRestApi.yaml` |

El nombre de acción en el YAML y en `getName()` deben coincidir exactamente — una
discrepancia provoca `ActionNotFoundException` en tiempo de ejecución.

---

## 5. Configuración YAML

Hay dos ficheros de config separados con responsabilidades distintas.

### Config del bundle (`config/packages/integration_engine.yaml`)

Registra las integraciones en el contenedor de Symfony y configura su capa de
transporte. Lo crea automáticamente `make:integration` en la primera ejecución.

```yaml
integration_engine:
  integrations:
    my_api:
      base_url: '%env(MY_API_BASE_URL)%'
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
      headers:
        X-Api-Version: '2'
      client: rest           # "rest" (por defecto), "graphql", o cualquier tipo personalizado registrado
      cache_service: ~       # por defecto Psr6CacheAdapter envolviendo cache.app
      client_service: ~      # ID de servicio ClientInterface personalizado — sobreescribe client
```

Cada integración requiere `base_url` o `client_service`.

> **`config_path`** se valida en **tiempo de compilación**. Una clave ausente lanza una
> excepción durante la compilación del contenedor. Un path que apunta a un fichero
> inexistente se detecta en **tiempo de ejecución** en la primera petición. Verifica
> todos los paths tras cada despliegue.

> **`cache_service`** usa por defecto `cache.app` via PSR-6. Sobreescríbelo con un pool
> dedicado si necesitas control independiente del TTL para tokens de auth dinámica.

### Registro de acciones (`src/Infrastructure/Integrations/MyApi/MyApi.yaml`)

Declara las operaciones disponibles para una integración concreta. Generado y
actualizado por `make:integration`.

```yaml
GetUsers:
  action: App\Infrastructure\Integrations\MyApi\GetUsers\Request\GetUsersAction
  method: GET
  path: /users

GetUser:
  action: App\Infrastructure\Integrations\MyApi\GetUser\Request\GetUserAction
  method: GET
  path: /users/{id}

CreateUser:
  action: App\Infrastructure\Integrations\MyApi\CreateUser\Request\CreateUserAction
  method: POST
  path: /users
```

Ninguna lógica vive en el YAML — el YAML declara intención; las Actions y Mappers
implementan comportamiento.

---

## 6. Actions

### YAML vs Action — por qué existen ambos

- **YAML** es la fuente de verdad en tiempo de arranque: qué clase instanciar, método, path.
- **La clase Action** porta comportamiento que el YAML no puede expresar: qué mapper usar, si se espera respuesta, resolución de path personalizada, auth compartida.

El YAML declara intención. La Action implementa comportamiento. Ninguno reemplaza al otro.

### Las actions son sin estado

Las actions son inmutables — todas las propiedades del constructor son `readonly`. El
contexto se pasa directamente a `getPath()` en tiempo de llamada y nunca se almacena.
La misma instancia puede llamarse con contextos distintos en peticiones sucesivas sin
mutación.

### Ejemplo completo

`make:integration DummyRestApi GetEmployee` genera tres ficheros. Aquí está una
implementación completa tras rellenarlos:

```php
// Request/GetEmployeeAction.php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string   { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string    { return GetEmployeeMapper::class; }
}
```

```php
// Response/GetEmployeeResponse.php
// DTO de integración — refleja la API externa, no tu dominio.
final class GetEmployeeResponse implements ResponseInterface
{
    public function __construct(
        public readonly int    $id,
        public readonly string $employeeName,
        public readonly string $employeeSalary,
        public readonly string $employeeAge,
    ) {}

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'employee_name'   => $this->employeeName,
            'employee_salary' => $this->employeeSalary,
            'employee_age'    => $this->employeeAge,
        ];
    }
}
```

```php
// Response/GetEmployeeMapper.php
// Recibe el array crudo del servidor y construye el DTO de integración.
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string { return GetEmployeeAction::class; }

    protected static function transform(
        AbstractAction $action,
        array $response,
    ): ResponseInterface {
        $data = $response['data'];

        return new GetEmployeeResponse(
            id:             (int) $data['id'],
            employeeName:   (string) $data['employee_name'],
            employeeSalary: (string) $data['employee_salary'],
            employeeAge:    (string) $data['employee_age'],
        );
    }
}
```

---

## 7. Sistema de contexto

El contexto resuelve segmentos dinámicos de la URL en tiempo de llamada:

```
/orders/{id}  →  /orders/42
```

### DefaultActionContext

Cubre la gran mayoría de casos:

```php
->send(
    actionName: GetOrderAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
)
```

### Clases de contexto personalizadas

Para contextos con validación o semántica de dominio, implementa `ActionContextInterface`:

```php
final readonly class GetOrderContext implements ActionContextInterface
{
    private function __construct(
        private int $orderId,
        private string $warehouseId,
    ) {}

    public static function create(array $data): self
    {
        return new self(
            orderId: (int) $data['id'],
            warehouseId: (string) $data['warehouse'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->orderId,
            'warehouse' => $this->warehouseId,
        ];
    }
}
```

### Resolución de path

Los parámetros ausentes lanzan `PathResolutionException` en tiempo de resolución, no
en tiempo de HTTP.

> **Placeholders con guión** — el resolvedor por defecto usa `\w+` (`[a-zA-Z0-9_]`).
> Los nombres de parámetro con guiones (p.ej. `{user-id}`) no se resolverán y el
> placeholder queda literal en el path, causando un 404 silencioso. Usa
> `resolvePathCallback()` para estos casos:
>
> ```php
> protected function resolvePathCallback(): ?callable
> {
>     return function (string $path, ?ActionContextInterface $context): string {
>         $data = $context?->toArray() ?? [];
>         return preg_replace_callback('/\{([^}]+)\}/', static function (array $m) use ($data, $path): string {
>             if (!array_key_exists($m[1], $data)) {
>                 throw PathResolutionException::missingParameter($m[1], $path);
>             }
>             return (string) $data[$m[1]];
>         }, $path) ?? $path;
>     };
> }
> ```

---

## 8. Sistema de body

Los bodies son objetos explícitos que implementan `ActionBodyInterface`:

```php
final class CreateOrderBody implements ActionBodyInterface
{
    public static function create(array $data): self { ... }
    public function toArray(): array { ... }
}
```

Los bodies se serializan como JSON para peticiones `POST`, `PUT` y `PATCH`.

> Si se pasa un body a `send()` pero la action no declara una clase `body` en el YAML,
> el engine lanza `InvalidArgumentException` para evitar descartar payloads silenciosamente.

### Bodies GraphQL

Implementa `GraphQLBodyInterface` — añade `getQuery()` y `getVariables()`:

```php
final class GetUserBody implements GraphQLBodyInterface
{
    public function __construct(private readonly string $login) {}

    public function getQuery(): string
    {
        return file_get_contents(__DIR__ . '/../queries/get_user.graphql');
    }

    public function getVariables(): array
    {
        return ['login' => $this->login];
    }

    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }

    public static function create(array $data): self
    {
        return new self((string) $data['login']);
    }
}
```

El `GraphQLClientAdapter` lo serializa como `{ "query": "...", "variables": {...} }`,
lo envía como `POST` al endpoint configurado, y pasa solo la clave `data` al mapper.
Los errores GraphQL en el body de la respuesta se detectan automáticamente y se lanzan
como `RequestResponseException`.

---

## 9. Sistema de autorización

### Autorización estática

Configúrala en el YAML de la action o directamente en una clase base de acción:

| Tipo | Header producido |
|------|-----------------|
| `bearer` | `Authorization: Bearer {token}` (`prefix` configurable) |
| `basic` | `Authorization: Basic {b64(usuario:contraseña)}` |
| `api_key` | `{header}: {token}` (nombre de header personalizado) |

### Autorización dinámica

Para APIs que requieren una petición de token previa (OAuth, tokens de sesión,
intercambios de API key), declara la action de token como una action normal y
referencíala:

**Paso 1 — Declarar la action de token:**

```php
// FetchTokenAction.php
final class FetchTokenAction extends AbstractAction
{
    public static function getName(): string   { return 'FetchToken'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string    { return FetchTokenMapper::class; }
}
```

**Paso 2 — Referenciarla en el YAML de la action protegida:**

```yaml
FetchToken:
  action: App\...\FetchTokenAction
  method: POST
  path: /oauth/token

GetOrders:
  action: App\...\GetOrdersAction
  method: GET
  path: /orders
  authorization:
    type: dynamic
    action: FetchToken
    token_field: access_token
    ttl: 3600
    # prefix: Token   # opcional — por defecto "Bearer"
```

**Qué ocurre en tiempo de ejecución:**

1. Se llama a `send('GetOrders')`.
2. El engine detecta `type: dynamic`.
3. Búsqueda en cache del token. Si hay hit → paso 6.
4. Cache miss → ejecuta `FetchToken`, mapea la respuesta, extrae `access_token`.
5. Almacena el token en cache durante 3600 segundos.
6. Reconstruye `GetOrdersAction` con un header bearer estático.
7. Ejecuta la petición real.

El autor de la integración no escribe ninguna lógica de cache.

> **Limitación**: La auth dinámica solo soporta `bearer` y `api_key`. El tipo `basic`
> requiere usuario y contraseña en lugar de un solo token — usa un `ClientInterface`
> personalizado para APIs con credenciales Basic dinámicas.

---

## 10. Sistema de headers

Los headers se resuelven en tres capas. Cada una sobreescribe a la anterior:

```
Defaults YAML  →  Headers de auth  →  Headers del caller
```

- **Defaults YAML**: headers fijos para la integración (`X-Api-Version`, `X-Client-Id`, etc.)
- **Headers de auth**: resueltos desde el `AuthorizationConfig` de la action. Siempre sobreescriben el YAML.
- **Headers del caller**: por petición, pasados en tiempo de llamada. Implementa `RequestHeadersInterface`:

```php
final class CorrelationHeaders implements RequestHeadersInterface
{
    public function __construct(private readonly string $requestId) {}

    public function toArray(): array
    {
        return ['X-Correlation-ID' => $this->requestId];
    }
}
```

---

## 11. API del engine

```php
send(
    string $actionName,
    ?ActionContextInterface $context = null,
    ?ActionBodyInterface $body = null,
    ?RequestHeadersInterface $headers = null,
): ResponseInterface
```

**Flujo**: cargar action → resolver auth → ejecutar HTTP → mapear respuesta → devolver `ResponseInterface`.

Usa `assert()` para estrechar el tipo de retorno para análisis estático sin coste en tiempo de ejecución:

```php
$response = $this->engine->send(...);
\assert($response instanceof GetEmployeeResponse);
return $response;
```

---

## 12. La frontera de respuesta

`ResponseInterface` solo requiere `toArray()`. Es intencional — es el punto donde
termina la responsabilidad del bundle y empieza la tuya.

`toArray()` existe por una razón interna: el engine lo usa para extraer tokens en
flujos de auth dinámica. No es la API pública de tu DTO de integración. Expón campos
tipados en la clase concreta; el dominio consume esos campos y construye sus propios
objetos.

El Mapper es la garantía estructural: recibe un array crudo, debe devolver un
`ResponseInterface`, y el engine verifica en tiempo de ejecución que el mapper
corresponde a la action correcta.

---

## 13. Extensibilidad

Cada componente de infraestructura es reemplazable:

| Contrato | Por defecto | Sobreescribir via |
|----------|------------|-------------------|
| `ClientInterface` | `SymfonyHttpClientAdapter` | `client_service` o `client` |
| `CachePort` | `Psr6CacheAdapter` (envuelve `cache.app`) | `cache_service` |
| `ConfigPort` | `YamlConfigAdapter` | CompilerPass personalizado |

### Adaptador HTTP personalizado

```php
final readonly class SoapClientAdapter implements ClientAdapterInterface
{
    public static function getClientType(): string { return 'soap'; }
    public static function requiresPath(): bool    { return false; }
    public static function requiresMethod(): bool  { return false; }

    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        // tu implementación
    }
}
```

Etiquétalo en `services.yaml`:

```yaml
App\Infrastructure\Http\SoapClientAdapter:
  tags:
    - { name: integration_engine.client_adapter }
```

Úsalo:

```yaml
integration_engine:
  integrations:
    my_soap_api:
      base_url: 'https://api.example.com/soap'
      client: soap
```

Los adaptadores de proyecto registrados después de los del bundle los sobreescriben
para el mismo tipo.

---

## 14. Internals del bundle

> **Esta sección es para contribuidores del bundle y autores de librerías.** Si estás
> usando IntegrationEngine en una aplicación, no necesitas esto. Empieza en el
> [Quick start](#quick-start).

### Mapa de capas

```
Bundle/
├── IntegrationEngineBundle.php
├── DependencyInjection/
│   ├── IntegrationEngineExtension.php   ← carga services.yaml, expone config como parámetros
│   ├── Configuration.php                ← define y valida el árbol de config
│   └── Compiler/
│       └── IntegrationCompilerPass.php  ← construye un IntegrationEngine por integración
├── Command/
│   └── MakeIntegrationCommand.php
└── Generator/
    ├── IntegrationContext.php
    ├── IntegrationFileGenerator.php
    └── TemplateRenderer.php

Core/
├── IntegrationEngine.php                ← orquesta una llamada send() de principio a fin
├── Contract/                            ← todos los contratos públicos
├── Port/                                ← CachePort, ConfigPort
├── Registry/                            ← IntegrationRegistry, IntegrationName
├── Response/EmptyResponse.php
└── Exception/

Infrastructure/
├── Adapter/YamlConfigAdapter.php
├── Cache/Psr6CacheAdapter.php
└── Http/
    ├── ClientAdapterResolver.php
    ├── SymfonyHttpClientAdapter.php
    └── GraphQLClientAdapter.php
```

### Secuencia de arranque — dos fases

**Fase 1 — Compilación del contenedor** (`IntegrationCompilerPass::process()`):

```
IntegrationEngineExtension::load()
  → lee integration_engine.yaml, valida árbol de config
  → almacena integraciones como parámetro del contenedor

IntegrationCompilerPass::process()
  → escanea servicios etiquetados integration_engine.client_adapter
      → construye mapa tipo → clase (registros posteriores sobreescriben anteriores)
  → por cada integración:
      → Definition para YamlConfigAdapter          [integration_engine.config.{nombre}]
      → Definition para adaptador HTTP             [integration_engine.http_client.{nombre}]
      → Definition para IntegrationEngine          [integration_engine.integration.{nombre}]
      → IntegrationRegistry::register(nombre, engine)
```

**Fase 2 — Tiempo de ejecución** (`IntegrationEngine::send()`):

```
send(actionName, context, body, headers)
  → ConfigPort::getAction(nombre, body)
  → applyAuthorization(action)
      → si DynamicAuthorizationConfig:
          → búsqueda en cache
          → cache miss: ejecutar auth action, mapear, extraer token, cachear
          → reconstruir action con StaticAuthorizationConfig
  → ClientInterface::send(action, context, headers)
  → si !hasResponse(): devolver EmptyResponse
  → applyMapper(action, rawResponse)
      → guardia: mapper::getAction() === action::class
      → mapper::map(action, rawResponse)
      → devolver ResponseInterface
```

### Puntos de extensión

**1. Nuevo tipo de adaptador de cliente** — implementa `ClientAdapterInterface`, etiquétalo, úsalo via `client:`. Ver [sección 13](#13-extensibilidad).

**2. Reemplazar infraestructura por integración** — pasa un ID de servicio via `client_service` o `cache_service`.

**3. Reemplazar `ConfigPort`** (p.ej. cargar actions desde una base de datos):

```php
final class DatabaseConfigAdapter implements ConfigPort
{
    public function getAction(string $name, ?ActionBodyInterface $body): AbstractAction
    {
        // cargar de BD, instanciar action
    }
}
```

Cablearlo en un compiler pass personalizado que se ejecute después de `IntegrationCompilerPass`:

```php
$container->getDefinition('integration_engine.config.my_api')
    ->setClass(DatabaseConfigAdapter::class)
    ->setArguments([new Reference('doctrine.dbal.default_connection')]);
```

**4. Inyectar config via `PrependExtensionInterface`** (para autores de librerías):

```php
public function prepend(ContainerBuilder $container): void
{
    $container->prependExtensionConfig('integration_engine', [
        'integrations' => [
            'my_api' => [
                'base_url'    => '%env(MY_API_URL)%',
                'config_path' => __DIR__.'/Resources/my_api.yaml',
            ],
        ],
    ]);
}
```

### Qué no sobreescribir

- `IntegrationEngine` es `final readonly` — reemplaza sus colaboradores, no el engine.
- `AbstractAction::create()` usa `new static()` — no cambies la firma del constructor.
- `AbstractMapper::map()` es `final` — sobreescribe `transform()` en su lugar.

### Referencia de IDs de servicio

| ID de servicio | Clase |
|---------------|-------|
| `integration_engine.registry` | `IntegrationRegistry` |
| `integration_engine.cache.default` | `Psr6CacheAdapter` |
| `integration_engine.resolver` | `ClientAdapterResolver` |
| `integration_engine.config.{nombre}` | `YamlConfigAdapter` |
| `integration_engine.http_client.{nombre}` | clase adaptador |
| `integration_engine.integration.{nombre}` | `IntegrationEngine` |

---

## 15. Referencia de errores

| Excepción | Cuándo | Qué hacer |
|-----------|--------|-----------|
| `ActionNotFoundException` | Nombre de action no está en el YAML | Verifica que el nombre coincide exactamente con la clave YAML |
| `NotMappedActionException` | `hasResponse(): true` pero `mapper(): null` | Declara un mapper o establece `hasResponse: false` |
| `MapperActionMismatchException` | `getAction()` del mapper no coincide con la action | Asegúrate de que cada mapper declara la clase Action correcta |
| `RequestResponseException` | HTTP 4xx/5xx o error de red | Inspecciona `$e->statusCode` y `$e->context` |
| `PathResolutionException` | Placeholder de path sin clave de contexto | Asegúrate de que todos los `{param}` están cubiertos |
| `DynamicAuthException` | Campo de token ausente o no escalar en respuesta de auth | Verifica que la respuesta de la action de auth coincide con `token_field` |
| `InvalidArgumentException` | YAML de integración vacío o inválido | Comprueba la estructura del fichero YAML |
| `InvalidArgumentException` | Clase de action en YAML no existe | Verifica el FQCN y ejecuta `composer dump-autoload` |
| `InvalidArgumentException` | Valor de `client` no registrado | Etiqueta el adaptador con `integration_engine.client_adapter` |
| `RequestResponseException` | `errors` de GraphQL en el body de la respuesta (HTTP 200) | Inspecciona `$e->context` para el mensaje de error GraphQL |