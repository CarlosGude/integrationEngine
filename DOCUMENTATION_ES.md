# IntegrationEngine Bundle — Documentación

## Quick start

### 1. Instalación

```bash
composer require carlosgude/integration-engine
```

Si Symfony Flex no registra el bundle automáticamente, añádelo manualmente
en `config/bundles.php`:

```php
return [
    // ...
    IntegrationEngine\Bundle\IntegrationEngineBundle::class => ['all' => true],
];
```

### 2. Genera tu primera integración

```bash
php bin/console make:integration DummyRestApi GetEmployees
```

El comando hace tres preguntas en el primer uso:

1. **Base URL**: `https://dummy.restapiexample.com`
2. **Path de la acción**: `/api/v1/employees`
3. **Método HTTP**: `GET`

Genera todo — configuración, clases y YAML:

```
config/packages/integration_engine.yaml          ← se crea en el primer uso
src/Infrastructure/Integrations/DummyRestApi/
    DummyRestApiIntegration.php
    DummyRestApi.yaml
    GetEmployees/
        Request/GetEmployeesAction.php
        Response/GetEmployeesMapper.php
        Response/GetEmployeesResponse.php
```

El mismo comando añade nuevas acciones a una integración existente. Detecta
lo que ya existe y solo genera lo que falta:

```bash
php bin/console make:integration DummyRestApi GetEmployee
# > Path: /api/v1/employee/{id}
# > Método: GET
# → crea los ficheros de GetEmployee/ y añade la entrada a DummyRestApi.yaml
# → omite DummyRestApiIntegration.php (ya existe)
```

`make:integration` no es solo para empezar — es el comando que usas cada vez
que añades una operación. Consulta la sección 3 para la referencia completa.

### 3. Úsalo

```php
$registry->get(DummyRestApiIntegration::NAME)->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 1]),
);
```

Eso es todo lo que el bundle requiere. El resto de este documento es
profundidad opcional.

### 4. Demo

Una aplicación Symfony funcional que demuestra el stack completo contra la
[Dummy REST API](https://dummy.restapiexample.com) pública está disponible en:

**[github.com/CarlosGude/integrationEngine-use-example](https://github.com/CarlosGude/integrationEngine-use-example)**

Es el punto de partida recomendado antes de seguir leyendo.

---

## 1. Uso ideal

El bundle genera la capa de integración. Lo que construyes encima sigue un
patrón que mantiene la API externa aislada de tu dominio.

### El stack completo

```
API externa
    → Action (declara la petición)
        → Mapper (transforma la respuesta cruda en un DTO de integración)
            → Facade de integración (expone métodos con nombre, oculta el engine)
                → Servicio de aplicación (traduce el DTO al objeto de dominio)
                    → Controller / Command / Queue processor / ...
```

Cada capa conoce solo la capa inmediatamente inferior. El dominio nunca
importa nada de `IntegrationEngine\` ni de tus clases de integración.

### Qué necesitas tocar realmente

La mayoría de integraciones solo requieren tres cosas:

| Cuándo | Qué |
|--------|-----|
| Siempre | Action, Mapper, Response DTO |
| A veces | Context (path params dinámicos), Body (payload de la petición) |
| Raramente | Auth personalizada, caché personalizada, cliente HTTP, fuente de config |

El scaffolding genera los tres ficheros que siempre son necesarios. Para la
mayoría de integraciones, eso es todo lo que escribirás.

### La facade de integración

La clase `DummyRestApiIntegration` generada por el scaffolding es la facade.
Resuelve el engine una vez y expone métodos tipados:

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

`GetEmployeeResponse` es un DTO de integración — refleja la API externa,
no tu dominio.

### El servicio de aplicación

La traducción del DTO de integración al objeto de dominio ocurre en un
servicio inyectable, ni en el controlador, ni en el dominio. El servicio no
sabe cómo se va a consumir su resultado — controller, command, event listener,
queue processor — por lo que permanece desacoplado del mecanismo de entrega:

```php
final class EmployeeService
{
    public function __construct(
        private readonly DummyRestApiIntegration $integration,
    ) {}

    public function getEmployee(int $id): Employee
    {
        $dummyEmployee = $this->integration->getEmployee($id);

        // El servicio traduce. No el dominio, no el controller.
        return new Employee(
            id:     $dummyEmployee->id,
            name:   $dummyEmployee->employeeName,
            salary: $dummyEmployee->employeeSalary,
        );
    }
}
```

Cualquier consumidor depende solo de `EmployeeService` y trabaja
exclusivamente con objetos de dominio:

```php
final class EmployeeController extends AbstractController
{
    public function __construct(
        private readonly EmployeeService $employeeService,
    ) {}

    #[Route('/employees/{id}')]
    public function show(int $id): JsonResponse
    {
        return $this->json(
            $this->employeeService->getEmployee($id)
        );
    }
}
```

### Lo que no hay que hacer

```php
// ❌ Incorrecto: el dominio ahora depende de un DTO de infraestructura
return Employee::fromDummyEmployee($dummyEmployee);
```

Si `Employee` sabe qué es un `GetEmployeeResponse`, el dominio tiene una
dependencia de la capa de integración. Cuando la API externa cambia, el
cambio se propaga al dominio. La separación colapsa.

### Por qué importa

Este es el patrón Anti-Corruption Layer aplicado en la frontera de
integración. El bundle garantiza el lado izquierdo: el mapper debe producir
un `ResponseInterface` y debe corresponder a la acción correcta. El lado
derecho — mantener el DTO de integración fuera del dominio — es tu
responsabilidad. Es la convención más importante que el bundle te pide.

---

## 2. Filosofía

### El bundle propone, no impone

IntegrationEngine define contratos. Lo que construyes encima es completamente
decisión tuya.

Cada pieza del bundle es una sugerencia, no un requisito:

- Usa `DefaultActionContext` para path params simples, o implementa
  `ActionContextInterface` para validación y lógica de dominio.
- Declara la auth en YAML para casos simples, usa `DynamicAuthorizationConfig`
  para flujos de tokens, o centralízala en una clase base de acción.
- Usa el scaffold generado tal cual, o extiéndelo con value objects,
  colecciones tipadas y facades de dominio.
- Reemplaza cualquier componente de infraestructura — cliente, caché, fuente
  de configuración — con una sola línea en YAML.

El bundle nunca ve más allá de `AbstractAction`, `ActionContextInterface` y
`ResponseInterface`. Todo lo demás es tu dominio.

### Principios de diseño

- Sin magia fuera del engine
- Las Actions son inmutables
- El contexto es explícito y se valida en tiempo de resolución
- Los Bodies son objetos tipados
- El mapping es explícito mediante Mappers
- Los headers tienen una precedencia definida: YAML → auth → caller
- El call site es uniforme independientemente de la complejidad de la integración
- La frontera de respuesta es un Anti-Corruption Layer: los Mappers producen
  DTOs de integración, nunca objetos de dominio. La transformación al dominio
  ocurre fuera del bundle

### Visión general de la arquitectura

- **Core**: contratos + lógica del engine. Sin dependencias de framework.
- **Infrastructure**: adaptadores HTTP, YAML y caché. Implementa los puertos del Core.
- **Bundle**: wiring de Symfony. DI, compiler pass, comando de scaffolding.

### Modelo de ejecución

```text
Registry
  -> IntegrationEngine
      -> ConfigPort (YAML / fuente personalizada)
      -> Action (inmutable)
      -> Resolución de contexto (path resolution)
      -> Autorización (estática o dinámica con caché)
      -> Cliente HTTP (headers YAML + headers auth + headers caller)
      -> Mapper
      -> Response DTO
```

### Clases base de integración

Si un grupo de acciones comparte configuración — auth, prefijo de path,
headers comunes — extráela en una clase abstracta que extienda `AbstractAction`:

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
    public static function getName(): string { return 'CreateCharge'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return CreateChargeMapper::class; }
}
```

| Nivel | Clase | Responsabilidad |
|-------|-------|-----------------|
| Bundle | `AbstractAction` | Contrato: método, path, auth, mapper |
| Integración | `StripeAction` | Config compartida: auth, prefijo, defaults |
| Operación | `CreateChargeAction` | Identidad: nombre, respuesta, mapper |

Usa uno, dos o los tres niveles. El bundle funciona igual en todos los casos.

---

## 3. Scaffolding

```bash
php bin/console make:integration {NombreIntegración} {NombreAcción}
```

### Qué hace el comando

| Paso | Qué ocurre |
|------|-----------|
| Solo primer uso | Pregunta la base URL y crea `config/packages/integration_engine.yaml` |
| Siempre | Pregunta el path y el método HTTP de la acción |
| Solo primer uso | Crea `{Nombre}Integration.php` |
| Siempre | Crea `{Acción}Action.php`, `{Acción}Mapper.php`, `{Acción}Response.php` |
| Siempre | Añade la entrada de la acción a `{Nombre}.yaml` (lo crea si no existe) |

### Crear integraciones manualmente

Si creas una clase de integración a mano sin usar el comando, debes
sobreescribir la constante `NAME`:

```php
final class MyApiIntegration implements IntegrationName
{
    public const string NAME = 'my_api'; // debe declararse explícitamente
}
```

La interfaz declara `NAME = '__MUST_OVERRIDE__'` como valor centinela. PHP
no obliga a sobreescribir constantes a nivel de lenguaje. Si `NAME` no se
declara, la integración se registrará bajo `'__MUST_OVERRIDE__'` y no
resolverá correctamente desde el registry.

El scaffolding lo gestiona automáticamente; la creación manual lo requiere
de forma explícita.

---

## 4. Configuración YAML

Existen dos ficheros separados con responsabilidades distintas.

### Configuración del bundle (`config/packages/integration_engine.yaml`)

Registra las integraciones en el container de Symfony y configura su capa
de transporte. Se crea automáticamente por `make:integration` en el primer
uso.

```yaml
integration_engine:
  integrations:
    my_api:
      base_url: '%env(MY_API_BASE_URL)%'
      config_path: '%kernel.project_dir%/src/Infrastructure/Integrations/MyApi/MyApi.yaml'
      headers:
        X-Api-Version: '2'
      cache_service: ~       # por defecto InMemoryCacheAdapter (solo dev)
      client_service: ~      # ID de servicio ClientInterface personalizado
```

Se requiere `base_url` o `client_service` por integración.

> **Aviso**: El `InMemoryCacheAdapter` por defecto tiene alcance de proceso y
> no persiste entre requests bajo PHP-FPM. Configura un `cache_service`
> respaldado por Redis o APCu para auth dinámica en producción.

### Configuración de integración (`src/Infrastructure/Integrations/MyApi/MyApi.yaml`)

Declara las operaciones disponibles para una integración específica. Se
genera y actualiza automáticamente por `make:integration`.

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

La lógica no vive en YAML — YAML declara la intención; las Actions y Mappers
implementan el comportamiento.

---

## 5. Actions

### YAML vs Action — por qué existen los dos

El fichero YAML y la clase Action mencionan ambos método y path. Tienen
propósitos distintos:

- **YAML** es la fuente de verdad en tiempo de boot. El engine lo lee para
  saber qué clase Action instanciar y con qué método y path.
- **La clase Action** es el objeto en memoria en tiempo de ejecución. Lleva
  comportamiento que YAML no puede expresar: qué mapper usar, si se espera
  respuesta, lógica de resolución de path personalizada, configuración de
  auth compartida.

YAML declara la intención. La Action implementa el comportamiento. Ninguno
reemplaza al otro.

### Ciclo de vida

```
Config YAML
    → el engine lee método, path, clase Action, autorización
        → instancia la Action vía Action::create(method, path, ...)
            → la pasa al cliente HTTP y al mapper
```

El desarrollador nunca instancia una Action directamente. Lo hace el engine.

### Qué declara una Action

Una Action define el método HTTP, la plantilla de path, un body opcional y
autorización opcional. Las Actions son inmutables y sin estado — todas las
propiedades del constructor son `readonly`. El engine pasa el contexto
directamente a `getPath()` en tiempo de llamada. La acción nunca almacena
estado de ejecución. La misma instancia puede llamarse con contextos distintos
en peticiones sucesivas sin mutación.

### Clases generadas — ejemplo completo

`make:integration DummyRestApi GetEmployee` produce tres ficheros. Así es
como queda una implementación completa una vez rellenada:

```php
// Request/GetEmployeeAction.php
final class GetEmployeeAction extends AbstractAction
{
    public static function getName(): string { return 'GetEmployee'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return GetEmployeeMapper::class; }
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
// El engine verifica en tiempo de ejecución que este mapper pertenece a GetEmployeeAction.
final class GetEmployeeMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetEmployeeAction::class;
    }

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

## 6. Contexto

El contexto resuelve segmentos dinámicos del path en tiempo de llamada:

```php
/orders/{id}  →  /orders/42
```

### DefaultActionContext

Implementación de propósito general que cubre la gran mayoría de casos:

```php
->send(
    actionName: GetOrderAction::getName(),
    context: DefaultActionContext::create(['id' => 42]),
)
```

### Clases de contexto personalizadas

Para contextos con validación, semántica de dominio o lógica de resolución
compleja, implementa `ActionContextInterface` directamente:

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

Los parámetros faltantes lanzan un `RuntimeException` en tiempo de
resolución, no en tiempo de HTTP. Se puede proporcionar un resolver
personalizado sobreescribiendo `resolvePathCallback()` en la Action:

```php
protected function resolvePathCallback(): ?callable
{
    return function (string $path, ?ActionContextInterface $context): string {
        return $resolvedPath;
    };
}
```

## 7. Body

Los bodies son objetos explícitos que implementan `ActionBodyInterface`:

```php
final class CreateOrderBody implements ActionBodyInterface
{
    public static function create(array $data): self {}
    public function toArray(): array {}
}
```

Los bodies se serializan como JSON y se envían en peticiones `POST`, `PUT`,
`PATCH` y `DELETE`.

## 8. Autorización

### Autorización estática

| Tipo      | Header generado                     |
|-----------|-------------------------------------|
| `bearer`  | `Authorization: Bearer {token}`     |
| `basic`   | `Authorization: Basic {b64}`        |
| `api_key` | `{header}: {token}` (header custom) |

### Autorización dinámica — ejemplo completo

Para APIs que requieren una petición de token previa (OAuth, session tokens,
intercambio de API keys), la acción de auth es una acción normal como
cualquier otra. El engine la ejecuta, extrae el token, lo cachea y sustituye
una auth estática de forma transparente antes de la petición real.

**Paso 1 — Declara la acción de token y su respuesta:**

```php
// FetchTokenAction.php
final class FetchTokenAction extends AbstractAction
{
    public static function getName(): string { return 'FetchToken'; }
    public static function hasResponse(): bool { return true; }
    public static function mapper(): string { return FetchTokenMapper::class; }
}

// FetchTokenResponse.php
final class FetchTokenResponse implements ResponseInterface
{
    public function __construct(
        public readonly string $accessToken,
    ) {}

    public function toArray(): array
    {
        return ['access_token' => $this->accessToken];
    }
}

// FetchTokenMapper.php
final class FetchTokenMapper extends AbstractMapper
{
    public static function getAction(): string { return FetchTokenAction::class; }

    protected static function transform(
        AbstractAction $action,
        array $response,
    ): ResponseInterface {
        return new FetchTokenResponse(
            accessToken: (string) $response['access_token'],
        );
    }
}
```

**Paso 2 — Registra ambas acciones en el YAML de la integración:**

```yaml
FetchToken:
  action: App\Infrastructure\Integrations\MyApi\FetchToken\Request\FetchTokenAction
  method: POST
  path: /oauth/token

GetOrders:
  action: App\Infrastructure\Integrations\MyApi\GetOrders\Request\GetOrdersAction
  method: GET
  path: /orders
  authorization:
    type: dynamic
    action: FetchToken
    token_field: access_token
    ttl: 3600
```

**Qué ocurre en tiempo de ejecución:**

1. Se llama a `engine->send('GetOrders')`.
2. El engine detecta `authorization.type: dynamic`.
3. Comprueba la caché para `integration_engine.token.FetchToken`.
4. Cache miss → ejecuta `FetchTokenAction`, mapea la respuesta con
   `FetchTokenMapper`, extrae `access_token`.
5. Almacena el token en caché durante 3600 segundos.
6. Reconstruye `GetOrdersAction` con un `StaticAuthorizationConfig`
   que lleva el token como header Bearer.
7. Ejecuta la petición real.

En llamadas posteriores dentro del TTL, el paso 4 se omite completamente.
El desarrollador no escribe ninguna lógica de caché.

## 9. Headers

Los headers se resuelven en tres capas. Cada capa sobreescribe la anterior:

```
YAML defaults  →  Headers de auth  →  Headers del caller
```

**Capa 1 — YAML defaults**: headers fijos para la integración, declarados en
`integration_engine.yaml`. Úsalos para versionado de API, identificación
del cliente.

**Capa 2 — Headers de auth**: resueltos a partir del `AuthorizationConfig`
de la Action. Siempre sobreescriben los YAML defaults.

**Capa 3 — Headers del caller**: headers por petición pasados en tiempo de
llamada. Implementa `RequestHeadersInterface`:

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

## 10. Engine API

```php
send(
    string $actionName,
    ?ActionContextInterface $context = null,
    ?ActionBodyInterface $body = null,
    ?RequestHeadersInterface $headers = null,
): ResponseInterface
```

**Flujo**: carga la acción → resuelve contexto → resuelve auth → ejecuta
HTTP → mapea respuesta → devuelve `ResponseInterface`.

Usa `assert()` para acotar el tipo de retorno para PHPStan sin coste en
tiempo de ejecución:

```php
$response = $this->engine->send(...);
\assert($response instanceof GetEmployeeResponse);
return $response;
```

## 11. Frontera de respuesta

`ResponseInterface` solo requiere `toArray()`. Esto es intencionado — es el
punto donde la responsabilidad del bundle termina y empieza la tuya. Consulta
la sección 1 para el patrón de uso completo.

`toArray()` existe por una razón interna: el engine lo usa para extraer
tokens en flujos de auth dinámica. No es la API pública de tu DTO de
integración. Expón campos tipados en la clase concreta; el dominio consume
esos campos y construye sus propios objetos.

El Mapper es la garantía estructural: recibe un array crudo, debe devolver
un `ResponseInterface`, y el engine verifica en tiempo de ejecución que el
mapper corresponde a la acción correcta. Lo que el objeto de respuesta
contiene más allá de eso es completamente decisión tuya.

## 12. Extensibilidad

Cada componente de infraestructura es reemplazable con una sola clave de
configuración:

| Contrato            | Implementación por defecto     | Sobreescribir con     |
|---------------------|--------------------------------|-----------------------|
| `ClientInterface`   | `SymfonyHttpClientAdapter`     | `client_service`      |
| `CachePort`         | `InMemoryCacheAdapter`         | `cache_service`       |
| `ConfigPort`        | `YamlConfigAdapter`            | CompilerPass custom   |

## 13. Errores

| Excepción                        | Cuándo                                                                            | Acción                                                          |
|----------------------------------|-----------------------------------------------------------------------------------|-----------------------------------------------------------------|
| `ActionNotFoundException`        | `send()` llamado con un nombre de acción no declarado en la config YAML           | Verifica que el nombre coincide exactamente con la clave YAML   |
| `NotMappedActionException`       | `mapper()` devuelve `null` pero `hasResponse()` es `true`                        | Declara una clase mapper o establece `hasResponse: false`       |
| `InvalidMapperException`         | `mapper()` devuelve una clase que no extiende `AbstractMapper`                    | Comprueba la declaración de la clase mapper                     |
| `MapperActionMismatchException`  | El `getAction()` del mapper no coincide con la acción que se está ejecutando      | Asegúrate de que cada mapper declara la clase Action correcta   |
| `RequestResponseException`       | HTTP 4xx/5xx o error de red                                                       | Inspecciona `getStatusCode()` y `getContext()`                  |
| `RuntimeException`               | Un parámetro de path no está en el contexto                                       | Asegúrate de que todos los `{param}` están cubiertos            |
| `RuntimeException`               | La respuesta de auth dinámica no contiene el `token_field` esperado               | Verifica que la estructura de la acción de auth coincide        |