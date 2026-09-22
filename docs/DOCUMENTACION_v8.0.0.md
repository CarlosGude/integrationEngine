# IntegrationEngine v8.0.0 - Documentación Técnica Completa

## Tabla de Contenidos

1. [Introducción y Resumen Ejecutivo](#introducción-y-resumen-ejecutivo)
2. [Cambios que Rompen Compatibilidad](#cambios-que-rompen-compatibilidad)
3. [Nuevas Características](#nuevas-características)
4. [Arquitectura Reorganizada](#arquitectura-reorganizada)
5. [Guía de Migración Detallada](#guía-de-migración-detallada)
6. [Patrones y Ejemplos de Uso](#patrones-y-ejemplos-de-uso)
7. [Webhooks - Nueva Arquitectura](#webhooks---nueva-arquitectura)
8. [Seguridad en v8.0.0](#seguridad-en-v800)
9. [Cambios Internos y de Bajo Nivel](#cambios-internos-y-de-bajo-nivel)
10. [Troubleshooting y Casos Comunes](#troubleshooting-y-casos-comunes)

---

## Introducción y Resumen Ejecutivo

### ¿Qué es IntegrationEngine?

IntegrationEngine es un bundle de Symfony que proporciona una arquitectura estandarizada y predecible para integrar APIs externas. Cada integración se divide en exactamente dos responsabilidades:

- **Request (Entrada)**: Qué datos se envían al API externa
- **Response (Salida)**: Qué datos devuelve el API y cómo se mapean
- **Mapper (Puente)**: Transforma la respuesta HTTP bruta en un DTO fuertemente tipado

### Cambios Principales en v8.0.0

La versión 8.0.0 introduce una reorganización fundamental del bundle con foco en:

1. **Ciclo de vida de eventos más seguro**: Los eventos del ciclo de vida ahora contienen solo datos escalares, nunca objetos complejos
2. **Webhooks basados en YAML**: Migración de código a configuración declarativa
3. **Codificación de formularios mejorada**: Soporte explícito para `application/x-www-form-urlencoded`
4. **Protección SSRF**: Prevención de Server-Side Request Forgery
5. **Middleware de solicitud**: Nuevo nivel de extensibilidad para esquemas de firma complejos (ej. OAuth 1.0a)
6. **Reintentos configurables**: Mejor control sobre políticas de reintento y timeouts

---

## Cambios que Rompen Compatibilidad

### 1. Eventos del Ciclo de Vida - Solo Datos Escalares

**Antes (v7.x):**
```php
// Los eventos contenían objetos completos
class ActionCompleted {
    public function __construct(
        public readonly ResponseInterface $response,  // DTO de respuesta
        public readonly int $statusCode,
    ) {}
}

// Uso en listeners
$listener = function (ActionCompleted $event) {
    $data = $event->response->toArray();  // Acceso a objeto
};
```

**Ahora (v8.0.0):**
```php
// Los eventos SOLO contienen datos escalares
class ActionCompleted {
    public function __construct(
        public readonly string $integrationName,
        public readonly string $actionName,
        public readonly int $statusCode,
        public readonly int $durationMs,  // Duración en milisegundos
    ) {}
}

// El listener NUNCA obtiene acceso a ResponseInterface
// Debes mantener el estado en tu aplicación
```

**Razón del cambio:**
- Clases readonly (PHP 8.1+) no pueden serializarse
- Aumenta la seguridad evitando que eventos expongan objetos internos
- Fuerza el patrón de separación de capas

**Cómo migrar:**
```php
// Antes: Listener con acceso al response
public function onActionCompleted(ActionCompleted $event): void {
    $movieId = $event->response->movieId;  // ❌ Ya no funciona
}

// Después: Mantener estado en tu aplicación
private ?int $lastMovieId = null;

public function send(MovieGetAction $action, /* ... */): MovieResponse {
    $response = $this->engine->send(/* ... */);
    $this->lastMovieId = $response->movieId;  // Guardar en la app
    return $response;
}

public function onActionCompleted(ActionCompleted $event): void {
    // Usar el estado guardado en la app, no en el evento
    $movieId = $this->lastMovieId;
}
```

### 2. Webhooks - De Código a YAML

**Antes (v7.x):**
```php
// Definir parser en código
class StripePaymentIntentParser extends IntegrationWebhookRequestParser {
    public function __construct(WebhookDefinition $definition) {
        // Configuración en constructor
    }
}

// Definir mapper en código
class StripePaymentIntentMapper extends AbstractWebhookMapper {
    public function getDefinition(): string {
        return 'payment_intent.succeeded';
    }
    
    public function map(array $payload, array $headers): WebhookEventInterface {
        return new StripePaymentIntentEvent(/* ... */);
    }
}

// Registrar en framework.yaml
webhook:
    routing:
        stripe:
            service: StripePaymentIntentParser
            secret: '%env(STRIPE_WEBHOOK_SECRET)%'
```

**Ahora (v8.0.0):**
```php
// Mapper con métodos estáticos
class StripePaymentIntentMapper extends AbstractWebhookMapper {
    public static function eventType(): string {
        return 'payment_intent.succeeded';
    }
    
    protected static function transform(array $payload, array $headers): WebhookEventInterface {
        return new StripePaymentIntentEvent(
            eventId: $payload['id'],
            eventType: $payload['type'],
            paymentIntentId: $payload['data']['object']['id'],
            // ...
        );
    }
}

// Consumer con trait
#[AsRemoteEventConsumer('stripe')]
final class StripePaymentIntentConsumer implements ConsumerInterface {
    use ConsumesWebhookEvents;
    
    protected function mapper(): AbstractWebhookMapper {
        return new StripePaymentIntentMapper();
    }
}

// Configuración en YAML (Stripe.yaml)
webhooks:
    type_field: type
    id_field: id
    signature:
        type: timestamped_hmac
        header: stripe-signature
        secret: '%env(STRIPE_WEBHOOK_SECRET)%'
        tolerance: 300  # segundos
    unknown_events: ignore  # o 'reject'
    events:
        payment_intent.succeeded:
            mapper: 'App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper'
        payment_intent.payment_failed:
            mapper: 'App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentFailedMapper'

# framework.yaml
webhook:
    routing:
        stripe:
            service: integration_engine.webhook_parser.stripe
            secret: 'unused'  # IntegrationEngine usa su propio secret de YAML
```

**Razón del cambio:**
- YAML es más declarativo y fácil de mantener
- Separación clara entre qué eventos procesar y cómo procesarlos
- El bundle genera automáticamente el parser para cada integración

**Cómo migrar:**
1. Crear archivo `MyApi.yaml` con sección `webhooks:`
2. Cambiar mappers a métodos estáticos `eventType()` y `transform()`
3. Crear consumer con trait `ConsumesWebhookEvents`
4. Actualizar `framework.yaml` para usar el parser del bundle

### 3. Codificación de Formularios Explícita

**Antes (v7.x):**
```php
// Comportamiento automático ambiguo
public function createPayment(CreatePaymentIntent $request): CreatePaymentIntentResponse {
    // ¿JSON o form-encoded? Undefined
    $response = $this->engine->send(
        CreatePaymentIntentAction::class,
        new DefaultActionContext(),
        $request
    );
}
```

**Ahora (v8.0.0):**
```php
// Ser explícito sobre la codificación
use IntegrationEngine\Core\Contract\RequestEncoding;

$response = $this->engine->send(
    CreatePaymentIntentAction::class,
    new DefaultActionContext(),
    $request,
    encoding: RequestEncoding::FormEncoded  // Explícito
);

// O usar el cliente FormEncodedClientAdapter
// en lugar de SymfonyHttpClientAdapter
```

**Configurar en `services.yaml`:**
```yaml
# Para APIs que requieren form-encoded
app.client.stripe:
    class: IntegrationEngine\Infrastructure\Adapter\FormEncodedClientAdapter
    arguments:
        $httpClient: '@http_client'
        $baseUrl: 'https://api.stripe.com'
        $defaultHeaders:
            Authorization: 'Bearer %env(STRIPE_SECRET_KEY)%'
```

**Razón del cambio:**
- Elimina ambigüedad en la codificación de datos
- Algunos APIs requieren form-encoded explícitamente
- Mejor control y debugging

### 4. Clases de Respuesta y Eventos Webhook - Deben ser `final readonly`

**Antes (v7.x):**
```php
final class CreatePaymentIntentResponse implements ResponseInterface {
    public function __construct(
        private readonly string $id,
        private readonly string $status,
    ) {}
}
```

**Ahora (v8.0.0):**
```php
final readonly class CreatePaymentIntentResponse implements ResponseInterface {
    public function __construct(
        private readonly string $id,
        private readonly string $status,
    ) {}
}
```

**Razón del cambio:**
- PHPStan regla v8 exige `readonly` en todas las Response y WebhookEvent
- Garantiza inmutabilidad completa
- Mejora la seguridad y previene mutaciones accidentales

**Verificación:**
```bash
# PHPStan detectará violaciones
make stan

# Solución: Agregar readonly a todas las Response classes
final readonly class MyResponse implements ResponseInterface { }
final readonly class MyEvent implements WebhookEventInterface { }
```

### 5. Reintentos y Timeouts - Comportamiento Cambiado

**Antes (v7.x):**
```php
// Reintentos simplificados
// Máximo 3 reintentos automáticos, sin control fino
```

**Ahora (v8.0.0):**
```php
// Control fino sobre reintentos
class RetryMiddleware extends AbstractClientMiddleware {
    public function process(
        PreparedRequest $request,
        callable $next,
    ): Response {
        $retries = 0;
        while ($retries < 3) {
            try {
                return $next($request);
            } catch (TransportException $e) {
                if (!$this->isTransient($e)) {
                    throw;
                }
                $retries++;
                // Esperar con backoff exponencial
                usleep($this->calculateDelay($retries));
            }
        }
        throw new MaxRetriesExceededException();
    }
}

// Configurar por integración
integration_engine:
    integrations:
        stripe:
            config_path: 'src/Integrations/Stripe.yaml'
            middlewares:
                - app.middleware.retry
                - app.middleware.rate_limit
```

**Razón del cambio:**
- Mejor control sobre políticas de reintento
- Diferenciación clara entre errores transitorios y permanentes
- Reducción de carga en APIs externas

---

## Nuevas Características

### 1. Middleware de Solicitud (Request Middleware)

Ejecuta **después** de que se resuelven path y body, justo antes del transporte HTTP. Ideal para esquemas de firma complejos como OAuth 1.0a.

```php
use IntegrationEngine\Core\Contract\RequestMiddleware\RequestMiddlewareInterface;
use IntegrationEngine\Core\Contract\RequestMiddleware\Request;

class OAuth1SigningMiddleware implements RequestMiddlewareInterface {
    public function __construct(private OAuthSigner $signer) {}
    
    public function process(
        Request $request,
        callable $next
    ): Response {
        // $request es: {method, url, headers, body}
        // Todo ya está resuelto (rutas, placeholders)
        
        // Firmar el request
        $signedHeaders = $this->signer->sign(
            method: $request->method,
            url: $request->url,
            body: $request->body,
            headers: $request->headers,
        );
        
        // Crear request con headers firmados
        $signedRequest = $request->withHeaders($signedHeaders);
        
        // Continuar al siguiente middleware
        $response = $next($signedRequest);
        
        return $response;
    }
}

// Registrar en services.yaml
services:
    app.middleware.oauth1:
        class: App\Middleware\OAuth1SigningMiddleware
        tags:
            - integration_engine.request_middleware

# Declarar en config de integración
integration_engine:
    integrations:
        twitter:
            config_path: 'src/Integrations/Twitter.yaml'
            request_middlewares:
                - app.middleware.oauth1
```

**Importante:** Cuando se usan request middlewares, los lotes (batch requests) se ejecutan secuencialmente, no en paralelo.

### 2. Reintentos Inteligentes en Lotes con Token Compartido

```php
// Cuando un token está cacheado
$results = $this->engine->sendMany([
    'request1' => new EngineRequest(GetEmployeeAction::class, /* ... */),
    'request2' => new EngineRequest(GetEmployeeAction::class, /* ... */),
]);

// Si ambas requieren autenticación dinámica:
// 1. Se obtiene el token UNA VEZ
// 2. Se usan en ambas requests
// 3. Si AMBAS devuelven 401: una sola vez se obtiene token fresco
// 4. Se reintentan las dos con el token nuevo
```

### 3. Resolución de Conexión en Tiempo de Ejecución

Para APIs multi-tenant que varían por URL y/o credenciales:

```php
interface ConnectionResolverInterface {
    public function resolve(mixed $connection): ConnectionCredentials;
}

// Credenciales resueltas
class ConnectionCredentials {
    public function __construct(
        public ?string $baseUrl = null,
        public ?AuthorizationConfig $authorization = null,
        public ?string $connectionId = null,  // Discriminador para cache de tokens
    ) {}
}

// Implementar en tu app
class TenantConnectionResolver implements ConnectionResolverInterface {
    public function resolve(mixed $connection): ConnectionCredentials {
        $tenant = $connection;  // ej: int $tenantId
        
        return new ConnectionCredentials(
            baseUrl: "https://api.tenant{$tenant}.example.com",
            authorization: new AuthorizationConfig\StaticConfig(
                token: "tenant_{$tenant}_key"
            ),
            connectionId: "tenant_{$tenant}",  // Para cache tokens
        );
    }
}

// Usar en send()
$response = $this->engine->send(
    GetEmployeeAction::class,
    new DefaultActionContext(['id' => 123]),
    connection: $tenantId  // Pasar opaque connection
);
```

---

## Arquitectura Reorganizada

### Flujo de Datos en v8.0.0

```
┌─────────────────────────────────────────────────────────────────┐
│ Aplicación (Servicio)                                           │
└────────────────────┬────────────────────────────────────────────┘
                     │ engine->send(action, context, body, headers, baseUrl, connection)
                     ▼
┌──────────────────────────────────────────────────────────────────┐
│ IntegrationEngine                                                │
│ 1. ConfigPort::getAction() → Resolver placeholders del body     │
│ 2. ConnectionResolver::resolve() → Credenciales (si connection) │
│ 3. AuthenticationHandler → Obtener/cachear token dinámico       │
└────────────────┬─────────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────────┐
│ MiddlewareClient (capa de middlewares)                          │
│ 1. CachingMiddleware (¿hit? → devolver cached)                  │
│ 2. User Middlewares en orden declarado                          │
│ 3. TracingMiddleware (dev/test)                                 │
│ 4. HTTP Adapter                                                 │
└────────────────┬─────────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────────┐
│ SymfonyHttpClientAdapter o GraphQLClientAdapter                │
│ 1. Resolver path/body en Request (method, url, headers, body) │
│ 2. Request Middlewares (ej. OAuth signing)                      │
│ 3. Transporte HTTP (client->request())                          │
│ 4. Procesar respuesta {body, headers}                          │
└────────────────┬─────────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────────┐
│ ResponseBuilder                                                  │
│ 1. Invocar mapper correspondiente                              │
│ 2. Validar que mapper.getAction() == action class              │
│ 3. Devolver ResponseInterface (DTO tipado)                     │
└────────────────┬─────────────────────────────────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────────────────────────────────┐
│ Aplicación obtiene ResponseInterface                            │
│ → Traducir a objetos de dominio                                │
└──────────────────────────────────────────────────────────────────┘
```

### Estructura de Directorios Recomendada

```
src/Integrations/
├── Stripe/
│   ├── Stripe.yaml                          # Configuración (actions + webhooks)
│   ├── CreatePaymentIntent/
│   │   ├── CreatePaymentIntentAction.php    # Declara HTTP method/path
│   │   ├── CreatePaymentIntentRequest.php   # Body/Query parameters
│   │   └── CreatePaymentIntentResponse.php  # DTO tipado
│   ├── Mappers/
│   │   └── CreatePaymentIntentMapper.php    # array → ResponseInterface
│   └── Webhook/
│       ├── StripePaymentIntentConsumer.php  # Procesa evento remoto
│       ├── StripePaymentIntentEvent.php     # DTO de evento
│       └── Mapper/
│           ├── StripePaymentIntentMapper.php
│           └── StripePaymentIntentFailedMapper.php
│
└── Countries/
    ├── Countries.yaml
    └── GetCountries/
        ├── GetCountriesAction.php
        └── GetCountriesResponse.php
```

### Contrato de Servicios Base

```php
// Cada acción implementa exactamente este contrato
abstract class AbstractAction {
    // Obligatorio
    protected function getMethod(): string;        // GET, POST, etc
    protected function getPath(): string;          // /v1/payment_intents/{id}
    
    // Opcional
    protected function getAuthorization(): ?AuthorizationConfig;
    protected function getCacheTtl(): ?int;
    protected function getMapper(): ?string;       // class-string<AbstractMapper>
}

// Cada mapper implementa exactamente este
abstract class AbstractMapper {
    abstract public static function getAction(): string;  // Qué acción maneja
    abstract protected static function transform(array $body, array $headers): ResponseInterface;
}

// Cada respuesta es
final readonly class MyResponse implements ResponseInterface {
    public function toArray(): array;  // Obligatorio
}
```

---

## Guía de Migración Detallada

### Paso 1: Actualizar Composer

```bash
composer require carlosgude/integration-engine:^8.0.0
composer install
```

### Paso 2: Cambiar Eventos del Ciclo de Vida

```php
// Antes
use IntegrationEngine\Core\Event\ActionCompleted;

class MyObserver {
    public function onActionCompleted(ActionCompleted $event): void {
        $response = $event->response;  // ❌ Ya no existe
    }
}

// Después
use IntegrationEngine\Core\Event\ActionCompleted;

class MyObserver {
    public function onActionCompleted(ActionCompleted $event): void {
        // Solo puedes acceder a datos escalares
        $durationMs = $event->durationMs;
        $statusCode = $event->statusCode;
        $actionName = $event->actionName;
    }
}
```

### Paso 3: Migrar Webhooks (Si los usas)

#### Paso 3a: Crear archivo YAML

```yaml
# src/Integrations/Stripe/Stripe.yaml
create_payment_intent:
    action: 'App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentAction'
    method: POST
    path: /v1/payment_intents
    body: 'App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentRequest'
    mapper: 'App\Integrations\Stripe\Mappers\CreatePaymentIntentMapper'

webhooks:
    type_field: type              # Dónde está el tipo en el payload
    id_field: id                  # Dónde está el ID en el payload
    signature:
        type: timestamped_hmac    # O: hmac_sha256 / base64_hmac
        header: stripe-signature  # Header HTTP donde va la firma
        secret: '%env(STRIPE_WEBHOOK_SECRET)%'
        tolerance: 300            # segundos (solo si timestamped_hmac)
    unknown_events: ignore        # O: reject
    events:
        payment_intent.succeeded:
            mapper: 'App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper'
        charge.refunded:
            mapper: 'App\Integrations\Stripe\Webhook\Mapper\StripeChargeRefundedMapper'
```

#### Paso 3b: Actualizar Mappers

```php
// Antes
class StripePaymentIntentMapper extends AbstractWebhookMapper {
    public function getDefinition(): string {
        return 'payment_intent.succeeded';
    }
    
    public function map(array $payload, array $headers): WebhookEventInterface {
        return new StripePaymentIntentEvent(
            eventId: $payload['id'],
            paymentIntentId: $payload['data']['object']['id'],
        );
    }
}

// Después
class StripePaymentIntentMapper extends AbstractWebhookMapper {
    public static function eventType(): string {
        return 'payment_intent.succeeded';
    }
    
    protected static function transform(array $payload, array $headers): WebhookEventInterface {
        return new StripePaymentIntentEvent(
            eventId: $payload['id'],
            paymentIntentId: $payload['data']['object']['id'],
        );
    }
}
```

#### Paso 3c: Crear Consumer

```php
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use IntegrationEngine\Infrastructure\Webhook\ConsumesWebhookEvents;

#[AsRemoteEventConsumer('stripe')]
final class StripePaymentIntentConsumer implements ConsumerInterface {
    use ConsumesWebhookEvents;
    
    protected function mapper(): AbstractWebhookMapper {
        return new StripePaymentIntentMapper();
    }
}
```

#### Paso 3d: Registrar en framework.yaml

```yaml
# config/packages/framework.yaml
framework:
    webhook:
        routing:
            stripe:
                service: integration_engine.webhook_parser.stripe
                secret: 'unused'  # El verdadero secret viene de Stripe.yaml
```

#### Paso 3e: Registrar WebhookEventDispatcher

```yaml
# config/services.yaml
services:
    IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher:
        arguments:
            - '@event_dispatcher'
```

### Paso 4: Hacer Todas las Response/Event Classes `final readonly`

```php
// Antes
final class MyResponse implements ResponseInterface {
    public function __construct(
        private readonly string $id,
    ) {}
}

// Después
final readonly class MyResponse implements ResponseInterface {
    public function __construct(
        private readonly string $id,
    ) {}
}
```

Ejecutar verificación:
```bash
make stan
```

### Paso 5: Actualizar Uso de Encoding (si usas form-encoded)

```php
// Antes
$response = $this->engine->send(CreatePaymentIntentAction::class, $context, $request);

// Después - Ser explícito
use IntegrationEngine\Core\Contract\RequestEncoding;

$response = $this->engine->send(
    CreatePaymentIntentAction::class,
    $context,
    $request,
    encoding: RequestEncoding::FormEncoded  // Si el API requiere form-encoded
);
```

### Paso 6: Ejecutar Tests

```bash
# Tests unitarios
make test

# Static Analysis
make stan

# Deptrac (arquitectura)
make deptrac

# Todo junto
make qa
```

---

## Patrones y Ejemplos de Uso

### Patrón 1: Action Simple (GET)

```php
// Action
class GetEmployeeAction extends AbstractAction {
    protected function getMethod(): string {
        return 'GET';
    }
    
    protected function getPath(): string {
        return '/api/employees/{id}';  // {id} será resuelto de context
    }
}

// Response
final readonly class GetEmployeeResponse implements ResponseInterface {
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
    ) {}
    
    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// Mapper
class GetEmployeeMapper extends AbstractMapper {
    public static function getAction(): string {
        return GetEmployeeAction::class;
    }
    
    protected static function transform(array $body, array $headers): ResponseInterface {
        return new GetEmployeeResponse(
            id: $body['id'] ?? '',
            name: $body['name'] ?? '',
            email: $body['email'] ?? '',
        );
    }
}

// YAML
get_employee:
    action: 'App\Integrations\Employees\GetEmployee\GetEmployeeAction'
    method: GET
    path: /api/employees/{id}
    mapper: 'App\Integrations\Employees\GetEmployee\GetEmployeeMapper'

// Usar
$context = new DefaultActionContext(['id' => 123]);
$employee = $this->engine->send(GetEmployeeAction::class, $context);
echo $employee->name;  // "John Doe"
```

### Patrón 2: Action con Body (POST)

```php
// Request
class CreatePaymentIntentRequest {
    public function __construct(
        public string $amount,
        public string $currency = 'USD',
        public ?string $description = null,
    ) {}
}

// Action
class CreatePaymentIntentAction extends AbstractAction {
    protected function getMethod(): string {
        return 'POST';
    }
    
    protected function getPath(): string {
        return '/v1/payment_intents';
    }
}

// Response
final readonly class CreatePaymentIntentResponse implements ResponseInterface {
    public function __construct(
        public string $id,
        public string $status,
        public int $amount,
        public string $currency,
        public ?string $clientSecret,
    ) {}
    
    public function toArray(): array {
        return get_object_vars($this);
    }
}

// Mapper
class CreatePaymentIntentMapper extends AbstractMapper {
    public static function getAction(): string {
        return CreatePaymentIntentAction::class;
    }
    
    protected static function transform(array $body, array $headers): ResponseInterface {
        return new CreatePaymentIntentResponse(
            id: $body['id'],
            status: $body['status'],
            amount: (int) $body['amount'],
            currency: $body['currency'],
            clientSecret: $body['client_secret'] ?? null,
        );
    }
}

// YAML
create_payment_intent:
    action: 'App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentAction'
    method: POST
    path: /v1/payment_intents
    body: 'App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentRequest'
    mapper: 'App\Integrations\Stripe\Mappers\CreatePaymentIntentMapper'

// Usar
$request = new CreatePaymentIntentRequest(
    amount: '2000',  // En centavos
    currency: 'USD',
    description: 'Compra de película',
);
$response = $this->engine->send(
    CreatePaymentIntentAction::class,
    new DefaultActionContext(),
    $request,
);
echo $response->clientSecret;
```

---

## Troubleshooting y Casos Comunes

### Error: "Cannot autowire service WebhookEventDispatcher"

```
Causa: WebhookEventDispatcher necesita EventDispatcher pero no está registrado

Solución:
# config/services.yaml
services:
    IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher:
        arguments:
            - '@event_dispatcher'
```

### Error: "Mapper declaration does not match event type"

```php
// Causa: mapper::eventType() != remoteEvent->getName()

class StripePaymentIntentMapper extends AbstractWebhookMapper {
    public static function eventType(): string {
        return 'payment_intent.succeeded';  // ⚠️ Debe coincidir con evento
    }
}

// YAML
events:
    payment_intent.succeeded:  # ⚠️ Debe coincidir con eventType()
        mapper: StripePaymentIntentMapper
```

### Error: "Response class must be final and readonly"

```
Causa: PHPStan regla exige final readonly

Solución:
// ❌ Antes
final class MyResponse implements ResponseInterface {}

// ✅ Después
final readonly class MyResponse implements ResponseInterface {}
```

### Webhook rechazado: "header_missing (406)"

```
Causa: Header de firma no encontrado o nombre incorrecto

Solución 1: Verificar nombre de header en YAML
webhooks:
    signature:
        header: stripe-signature  # ⚠️ Verificar que es correcto

Solución 2: Verificar que cliente envia el header
// Stripe envía: Stripe-Signature → Symfony convierte a HTTP_STRIPE_SIGNATURE
// YAML busca: stripe-signature → busca HTTP_STRIPE_SIGNATURE (OK)
```

### Webhook rechazado: "timestamp_expired (406)"

```
Causa: Header timestamp es antiguo (> tolerance)

Solución 1: Aumentar tolerance (cuidado con seguridad)
webhooks:
    signature:
        type: timestamped_hmac
        tolerance: 600  # 10 minutos en lugar de 5

Solución 2: Sincronizar tiempo del servidor
# Verificar que NTP esté sincronizado
timedatectl status
systemctl restart systemd-timesyncd
```

---

## Anexo A: Comandos Útiles

```bash
# Tests
make test
make test -- --filter=testName

# Static Analysis
make stan
make stan PATHS='src/Integrations/Stripe.yaml'

# Arquitectura
make deptrac

# Code Style
make cs         # Dry-run
make cs-fix     # Aplicar

# Todo
make qa

# CI Local
make pre-commit
```

---

## Anexo B: Checklist de Migración

- [ ] Actualizar `composer.require` a v8.0.0
- [ ] Cambiar eventos del ciclo de vida (solo datos escalares)
- [ ] Hacer todas las Response classes `final readonly`
- [ ] Hacer todos los Event classes `final readonly`
- [ ] Si usas webhooks:
  - [ ] Crear `.yaml` con sección `webhooks:`
  - [ ] Cambiar mappers a métodos estáticos
  - [ ] Crear consumers con trait `ConsumesWebhookEvents`
  - [ ] Actualizar `framework.yaml`
  - [ ] Registrar `WebhookEventDispatcher` en `services.yaml`
- [ ] Verificar encoding (form-encoded vs JSON)
- [ ] Ejecutar `make qa`
- [ ] Test end-to-end de webhooks (si aplica)
- [ ] Deploya staging primero

---

**Documentación actualizada a v8.0.0 - Septiembre 2026**
