# Manual: Probar, publicar en Git y publicar en Packagist

---

## Índice

1. [Preparar el entorno local](#1-preparar-el-entorno-local)
2. [Escribir los tests](#2-escribir-los-tests)
3. [Ejecutar los tests](#3-ejecutar-los-tests)
4. [Preparar el repositorio Git](#4-preparar-el-repositorio-git)
5. [Publicar en GitHub](#5-publicar-en-github)
6. [Configurar CI con GitHub Actions](#6-configurar-ci-con-github-actions)
7. [Preparar el package para Composer](#7-preparar-el-package-para-composer)
8. [Publicar en Packagist](#8-publicar-en-packagist)
9. [Versionado y releases](#9-versionado-y-releases)
10. [Checklist completa](#10-checklist-completa)

---

## 1. Preparar el entorno local

El proyecto ya tiene `composer.json` con las dependencias de desarrollo. Instálalas:

```bash
cd integrationEngine
composer install
```

Verifica que los tres binarios de desarrollo están disponibles:

```bash
vendor/bin/phpunit --version
vendor/bin/phpstan --version
vendor/bin/php-cs-fixer --version
```

Si alguno falla, revisa que `require-dev` en `composer.json` esté correcto y vuelve a ejecutar `composer install`.

---

## 2. Escribir los tests

Crea la estructura de tests:

```
tests/
    Unit/
        Core/
            IntegrationTest.php
            Contract/
                AbstractMapperTest.php
                AbstractActionTest.php
        Infrastructure/
            Adapter/
                YamlConfigAdapterTest.php
            Cache/
                InMemoryCacheAdapterTest.php
    Fixtures/
        yaml/
            valid.yaml
            missing_method.yaml
```

A continuación los tests más importantes, listos para copiar.

---

### `tests/Unit/Core/Contract/AbstractActionTest.php`

Verifica que `AbstractAction::create()` construye la acción correctamente y que los getters funcionan.

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Core\Contract;

use IntegrationEngine\Core\Contract\AbstractAction;
use PHPUnit\Framework\TestCase;

final class AbstractActionTest extends TestCase
{
    public function test_create_builds_action_with_valid_method(): void
    {
        $action = StubAction::create(method: 'GET', path: '/orders', body: null, authorization: null);

        $this->assertSame('GET', $action->getMethod());
        $this->assertSame('/orders', $action->getPath());
        $this->assertNull($action->getBody());
        $this->assertNull($action->getAuthorization());
    }

    public function test_has_body_and_has_response_flags(): void
    {
        $this->assertFalse(StubAction::hasBody());
        $this->assertTrue(StubAction::hasResponse());
        $this->assertSame(StubMapper::class, StubAction::mapper());
    }
}

// Stub mínimo dentro del mismo archivo de tests
final readonly class StubAction extends AbstractAction
{
    public static function getName(): string    { return 'stub'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return StubMapper::class; }
}
```

---

### `tests/Unit/Core/Contract/AbstractMapperTest.php`

Verifica que `AbstractMapper::map()` lanza excepción si el mapper no corresponde a la action.

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Core\Contract;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Exception\MapperActionMismatchException;
use PHPUnit\Framework\TestCase;

final class AbstractMapperTest extends TestCase
{
    public function test_map_returns_response_for_correct_action(): void
    {
        $action   = CorrectAction::create('GET', '/tests', null, null);
        $response = CorrectMapper::map($action, ['value' => 42]);

        $this->assertInstanceOf(StubResponse::class, $response);
        $this->assertSame(['value' => 42], $response->toArray());
    }

    public function test_map_throws_on_action_mismatch(): void
    {
        $this->expectException(MapperActionMismatchException::class);

        $wrongAction = WrongAction::create('GET', '/other', null, null);
        CorrectMapper::map($wrongAction, []);
    }
}

// Stubs

final readonly class CorrectAction extends AbstractAction
{
    public static function getName(): string    { return 'correct'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return CorrectMapper::class; }
}

final readonly class WrongAction extends AbstractAction
{
    public static function getName(): string    { return 'wrong'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return CorrectMapper::class; }
}

final class CorrectMapper extends AbstractMapper
{
    public static function getAction(): string { return CorrectAction::class; }

    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new StubResponse($response);
    }
}

final readonly class StubResponse implements ResponseInterface
{
    public function __construct(private array $data) {}
    public function toArray(): array { return $this->data; }
}
```

---

### `tests/Unit/Core/IntegrationTest.php`

Verifica el flujo completo de `Integration::send()` usando dobles de test, sin hacer ninguna llamada HTTP real.

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Exception\InvalidMapperException;
use IntegrationEngine\Core\Integration;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    private ConfigPort&MockObject    $config;
    private ClientInterface&MockObject $client;
    private CachePort&MockObject     $cache;
    private Integration              $integration;

    protected function setUp(): void
    {
        $this->config      = $this->createMock(ConfigPort::class);
        $this->client      = $this->createMock(ClientInterface::class);
        $this->cache       = $this->createMock(CachePort::class);
        $this->integration = new Integration($this->config, $this->client, $this->cache);
    }

    public function test_send_returns_typed_response(): void
    {
        $action = TestAction::create('GET', '/orders', null, null);

        $this->config->expects($this->once())
            ->method('getAction')
            ->with('GetOrders')
            ->willReturn($action);

        $this->client->expects($this->once())
            ->method('send')
            ->with($action)
            ->willReturn(['id' => 99]);

        $response = $this->integration->send('GetOrders');

        $this->assertInstanceOf(TestResponse::class, $response);
        $this->assertSame(['id' => 99], $response->toArray());
    }

    public function test_send_throws_if_mapper_class_is_invalid(): void
    {
        $this->expectException(InvalidMapperException::class);

        $action = BadMapperAction::create('GET', '/bad', null, null);

        $this->config->method('getAction')->willReturn($action);
        $this->client->method('send')->willReturn([]);

        $this->integration->send('bad');
    }

    public function test_delete_action_returns_empty_response_without_mapper(): void
    {
        $action = DeleteAction::create('DELETE', '/orders/1', null, null);

        $this->config->method('getAction')->willReturn($action);
        $this->client->expects($this->once())->method('send')->willReturn([]);

        $response = $this->integration->send('DeleteOrders');

        $this->assertSame([], $response->toArray());
    }

    public function test_dynamic_auth_uses_cached_token_on_second_call(): void
    {
        $dynAuth = new DynamicAuthorizationConfig(
            action: 'PostLogin',
            tokenField: 'token',
            ttl: 3600,
        );

        $actionWithDynAuth = TestAction::create('GET', '/orders', null, $dynAuth);

        $this->config->method('getAction')
            ->willReturnCallback(fn(string $name) => match ($name) {
                'GetOrders' => $actionWithDynAuth,
                'PostLogin' => LoginAction::create('POST', '/auth', null, null),
                default     => throw new \InvalidArgumentException("Unknown: $name"),
            });

        $this->cache->expects($this->exactly(2))
            ->method('has')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->cache->expects($this->once())->method('set');
        $this->cache->expects($this->once())->method('get')->willReturn('my-jwt-token');

        $this->client->expects($this->exactly(3))
            ->method('send')
            ->willReturnOnConsecutiveCalls(
                ['token' => 'my-jwt-token'],
                ['id' => 1],
                ['id' => 2],
            );

        $this->integration->send('GetOrders');
        $this->integration->send('GetOrders');
    }
}

// Stubs

final readonly class TestAction extends AbstractAction
{
    public static function getName(): string    { return 'GetOrders'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return TestMapper::class; }
}

final readonly class LoginAction extends AbstractAction
{
    public static function getName(): string    { return 'PostLogin'; }
    public static function hasBody(): bool      { return true; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return TokenMapper::class; }
}

final readonly class DeleteAction extends AbstractAction
{
    public static function getName(): string    { return 'DeleteOrders'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return false; }
    public static function mapper(): ?string    { return null; }
}

final readonly class BadMapperAction extends AbstractAction
{
    public static function getName(): string    { return 'bad'; }
    public static function hasBody(): bool      { return false; }
    public static function hasResponse(): bool  { return true; }
    public static function mapper(): ?string    { return \stdClass::class; } // Not an AbstractMapper
}

final class TestMapper extends AbstractMapper
{
    public static function getAction(): string { return TestAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new TestResponse($response);
    }
}

final class TokenMapper extends AbstractMapper
{
    public static function getAction(): string { return LoginAction::class; }
    protected static function transform(AbstractAction $action, array $response): ResponseInterface
    {
        return new TestResponse($response);
    }
}

final readonly class TestResponse implements ResponseInterface
{
    public function __construct(private array $data) {}
    public function toArray(): array { return $this->data; }
}
```

---

### `tests/Unit/Infrastructure/Cache/InMemoryCacheAdapterTest.php`

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Infrastructure\Cache;

use IntegrationEngine\Infrastructure\Cache\InMemoryCacheAdapter;
use PHPUnit\Framework\TestCase;

final class InMemoryCacheAdapterTest extends TestCase
{
    private InMemoryCacheAdapter $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCacheAdapter();
    }

    public function test_has_returns_false_for_unknown_key(): void
    {
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function test_set_and_get_return_stored_value(): void
    {
        $this->cache->set('my_token', 'abc123', 3600);

        $this->assertTrue($this->cache->has('my_token'));
        $this->assertSame('abc123', $this->cache->get('my_token'));
    }

    public function test_expired_entry_is_not_available(): void
    {
        $this->cache->set('expired_token', 'value', -1); // TTL en el pasado

        $this->assertFalse($this->cache->has('expired_token'));
    }
}
```

---

### `tests/Unit/Infrastructure/Adapter/YamlConfigAdapterTest.php`

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Infrastructure\Adapter;

use IntegrationEngine\Infrastructure\Adapter\YamlConfigAdapter;
use PHPUnit\Framework\TestCase;

final class YamlConfigAdapterTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../../../Fixtures/yaml/';
    }

    public function test_get_action_returns_action_for_valid_config(): void
    {
        $adapter = new YamlConfigAdapter($this->fixturesPath . 'valid.yaml');
        $action  = $adapter->getAction('GetOrders');

        $this->assertSame('GET', $action->getMethod());
        $this->assertSame('/orders', $action->getPath());
    }

    public function test_get_action_throws_for_unknown_action(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $adapter = new YamlConfigAdapter($this->fixturesPath . 'valid.yaml');
        $adapter->getAction('nonexistent');
    }

    public function test_get_action_throws_if_method_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $adapter = new YamlConfigAdapter($this->fixturesPath . 'missing_method.yaml');
        $adapter->getAction('GetOrders');
    }
}
```

Crea los ficheros de fixture en `tests/Fixtures/yaml/`.

**`tests/Fixtures/yaml/valid.yaml`:**
```yaml
GetOrders:
    action: IntegrationEngine\Tests\Unit\Core\TestAction
    method: GET
    path: /orders
```

**`tests/Fixtures/yaml/missing_method.yaml`:**
```yaml
GetOrders:
    action: IntegrationEngine\Tests\Unit\Core\TestAction
    path: /orders
```

---

## 3. Ejecutar los tests

El proyecto ya incluye `phpunit.xml.dist` configurado. Ejecuta:

```bash
# Tests con salida descriptiva
vendor/bin/phpunit --testdox

# Con cobertura (requiere Xdebug o pcov)
vendor/bin/phpunit --coverage-text

# PHPStan nivel 8
vendor/bin/phpstan analyse src tests --level=8

# CS Fixer (modo dry-run, no modifica)
vendor/bin/php-cs-fixer fix --dry-run --diff
```

O usando el `Makefile`:

```bash
make tests   # phpunit
make stan    # phpstan
make cs      # cs-fixer
make qa      # los tres en secuencia
```

---

## 4. Preparar el repositorio Git

### 4.1 `.gitignore`

El proyecto ya incluye `.gitignore`. Verifica que cubre:

```gitignore
/vendor/
/.php-cs-fixer.cache
/.phpunit.cache
/.phpunit.result.cache
/build/
*.log
.env.local
```

### 4.2 Estructura final del repositorio

Antes de hacer el primer commit, verifica que tienes:

```
integrationEngine/
    src/
    tests/
        Unit/
        Fixtures/
    .gitignore
    .github/
        workflows/
            ci.yml
    composer.json
    phpunit.xml.dist
    README.md
    DOCUMENTATION.md
    CONTRIBUTING.md
    MANUAL_PUBLICACION.md
    Makefile
    LICENSE
```

---

## 5. Publicar en GitHub

### 5.1 Crear el repositorio

1. Ve a [github.com/new](https://github.com/new)
2. Nombre: `integrationEngine`
3. Visibilidad: **Public** (Packagist requiere repositorios públicos para el plan gratuito)
4. **No** inicialices con README ni .gitignore (ya los tienes)
5. Clic en **Create repository**

### 5.2 Primer push

```bash
cd integrationEngine

git init
git add .
git commit -m "feat: initial release v1.0.0"

git remote add origin git@github.com:CarlosGude/integrationEngine.git
git branch -M main
git push -u origin main
```

### 5.3 Estado actual del `composer.json`

El `composer.json` ya está configurado con el nombre correcto de Packagist y las URLs reales:

```json
{
    "name": "carlosgude/integration-engine",
    "homepage": "https://github.com/CarlosGude/integrationEngine",
    "support": {
        "issues": "https://github.com/CarlosGude/integrationEngine/issues",
        "source": "https://github.com/CarlosGude/integrationEngine"
    }
}
```

No necesitas modificarlo antes de publicar.

---

## 6. Configurar CI con GitHub Actions

Crea `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    name: PHP ${{ matrix.php }} / Symfony ${{ matrix.symfony }}
    runs-on: ubuntu-latest

    strategy:
      matrix:
        php: ['8.2', '8.3']
        symfony: ['6.4', '7.2', '8.1']

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - name: Install dependencies
        run: |
          composer require "symfony/framework-bundle:^${{ matrix.symfony }}" --no-update
          composer install --prefer-dist --no-progress

      - name: PHPUnit
        run: vendor/bin/phpunit --testdox

      - name: PHPStan
        run: vendor/bin/phpstan analyse src tests --level=8

      - name: CS Fixer
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
```

```bash
git add .github/
git commit -m "ci: add GitHub Actions workflow"
git push
```

Ve a la pestaña **Actions** de tu repositorio en GitHub y verifica que el workflow pasa en verde.

---

## 7. Preparar el package para Composer

### 7.1 Nombre del vendor en Packagist

El nombre ya está definido como `carlosgude/integration-engine` en `composer.json`. **No lo cambies** — una vez publicado en Packagist, el nombre no se puede modificar fácilmente.

### 7.2 Crear la primera tag de versión

Packagist trabaja con tags de Git como versiones:

```bash
git tag v1.0.0
git push origin v1.0.0
```

---

## 8. Publicar en Packagist

### 8.1 Registrar el paquete

1. Ve a [packagist.org](https://packagist.org) e inicia sesión (puedes usar tu cuenta de GitHub)
2. Clic en **Submit**
3. En el campo URL introduce:
   ```
   https://github.com/CarlosGude/integrationEngine
   ```
4. Clic en **Check** — Packagist leerá el `composer.json` y mostrará `carlosgude/integration-engine`
5. Clic en **Submit**

El paquete ya es instalable:

```bash
composer require carlosgude/integration-engine
```

### 8.2 Configurar el webhook para actualizaciones automáticas

**En Packagist:**
1. Ve a la página de tu paquete
2. Haz clic en tu perfil → **Profile** → **Show API Token**
3. Copia el token

**En GitHub:**
1. Ve a tu repositorio → **Settings** → **Webhooks** → **Add webhook**
2. Payload URL: `https://packagist.org/api/github?username=CarlosGude`
3. Content type: `application/json`
4. Secret: el API token de Packagist
5. Events: **Just the push event**
6. Clic en **Add webhook**

Desde ese momento, cada `git push` y cada nuevo tag se reflejan en Packagist automáticamente.

---

## 9. Versionado y releases

### Flujo para publicar una nueva versión

```bash
# 1. Asegúrate de que los tests pasan
make qa

# 2. Haz commit de los cambios
git add .
git commit -m "feat: descripción del cambio"
git push

# 3. Crea la tag
git tag v1.1.0
git push origin v1.1.0
```

### Cuándo subir MAJOR / MINOR / PATCH

| Tipo de cambio | Versión |
|---|---|
| Rompe compatibilidad hacia atrás (BC break) | MAJOR: `v2.0.0` |
| Nueva funcionalidad compatible hacia atrás | MINOR: `v1.1.0` |
| Bugfix o corrección sin nueva funcionalidad | PATCH: `v1.0.1` |

### BC breaks que requieren MAJOR

- Renombrar o eliminar una interfaz, clase abstracta o método público del Core
- Cambiar la firma de `Integration::send()`, `AbstractAction::create()`, `AbstractMapper::map()`
- Añadir métodos abstractos a `AbstractAction` (actualmente: `getName`, `hasBody`, `hasResponse`, `mapper`)
- Modificar los campos requeridos en el YAML de configuración

---

## 10. Checklist completa

**Código:**
- [ ] `src/` completo y sin errores de PHPStan nivel 8
- [ ] `tests/` con cobertura de los flujos principales
- [ ] Tests en verde localmente (`make qa`)

**Repositorio:**
- [ ] `.gitignore` correcto (no hay `vendor/` en el repo)
- [ ] `LICENSE` presente (MIT)
- [ ] `README.md` con Quick Start funcional
- [ ] `DOCUMENTATION.md` completa
- [ ] `composer.json` con `name: carlosgude/integration-engine`, `authors`, `homepage`, `support`, `keywords`
- [ ] Primera tag `v1.0.0` creada y pusheada

**GitHub:**
- [ ] Repositorio público en `github.com/CarlosGude/integrationEngine`
- [ ] GitHub Actions en verde en la pestaña Actions
- [ ] Webhook de Packagist configurado

**Packagist:**
- [ ] Paquete registrado y visible en packagist.org
- [ ] `composer require carlosgude/integration-engine` funciona en un proyecto de prueba