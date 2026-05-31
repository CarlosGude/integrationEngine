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
cd integration-engine
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

No existe directorio `tests/` aún. Créalo con la siguiente estructura:

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
```

A continuación los tests más importantes, listos para copiar.

---

### `tests/Unit/Core/Contract/AbstractActionTest.php`

Verifica que `AbstractAction::create()` valida el método HTTP y que los getters funcionan.

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Core\Contract;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Exception\InvalidMethodException;
use PHPUnit\Framework\TestCase;

final class AbstractActionTest extends TestCase
{
    public function test_create_builds_action_with_valid_method(): void
    {
        $action = StubAction::create(method: 'GET', path: '/orders');

        $this->assertSame('GET', $action->getMethod());
        $this->assertSame('/orders', $action->getPath());
        $this->assertNull($action->getBody());
        $this->assertNull($action->getAuthorization());
    }

    public function test_create_throws_on_invalid_method(): void
    {
        $this->expectException(InvalidMethodException::class);

        StubAction::create(method: 'INVALID', path: '/orders');
    }

    /** @dataProvider validMethods */
    public function test_all_valid_methods_are_accepted(string $method): void
    {
        $action = StubAction::create(method: $method, path: '/test');
        $this->assertSame($method, $action->getMethod());
    }

    public static function validMethods(): array
    {
        return [['GET'], ['POST'], ['PUT'], ['DELETE']];
    }
}

// Stub mínimo dentro del mismo archivo de test
final readonly class StubAction extends AbstractAction
{
    public static function getName(): string    { return 'stub'; }
    public static function getMapper(): string  { return StubMapper::class; }
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
        $action   = CorrectAction::create('GET', '/test');
        $response = CorrectMapper::map($action, ['value' => 42]);

        $this->assertInstanceOf(StubResponse::class, $response);
        $this->assertSame(['value' => 42], $response->toArray());
    }

    public function test_map_throws_on_action_mismatch(): void
    {
        $this->expectException(MapperActionMismatchException::class);

        $wrongAction = WrongAction::create('GET', '/other');
        CorrectMapper::map($wrongAction, []);
    }
}

// Stubs

final readonly class CorrectAction extends AbstractAction
{
    public static function getName(): string   { return 'correct'; }
    public static function getMapper(): string { return CorrectMapper::class; }
}

final readonly class WrongAction extends AbstractAction
{
    public static function getName(): string   { return 'wrong'; }
    public static function getMapper(): string { return CorrectMapper::class; }
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

Este es el test más importante: verifica el flujo completo de `Integration::send()` usando dobles de test, sin hacer ninguna llamada HTTP real.

```php
<?php

declare(strict_types=1);

namespace IntegrationEngine\Tests\Unit\Core;

use IntegrationEngine\Core\Contract\AbstractAction;
use IntegrationEngine\Core\Contract\AbstractMapper;
use IntegrationEngine\Core\Contract\ActionBodyInterface;
use IntegrationEngine\Core\Contract\ClientInterface;
use IntegrationEngine\Core\Contract\DynamicAuthorizationConfig;
use IntegrationEngine\Core\Contract\ResponseInterface;
use IntegrationEngine\Core\Contract\StaticAuthorizationConfig;
use IntegrationEngine\Core\Exception\InvalidMapperException;
use IntegrationEngine\Core\Integration;
use IntegrationEngine\Core\Port\CachePort;
use IntegrationEngine\Core\Port\ConfigPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IntegrationTest extends TestCase
{
    private ConfigPort&MockObject   $config;
    private ClientInterface&MockObject $client;
    private CachePort&MockObject    $cache;
    private Integration             $integration;

    protected function setUp(): void
    {
        $this->config      = $this->createMock(ConfigPort::class);
        $this->client      = $this->createMock(ClientInterface::class);
        $this->cache       = $this->createMock(CachePort::class);
        $this->integration = new Integration($this->config, $this->client, $this->cache);
    }

    public function test_send_returns_typed_response(): void
    {
        $action = TestAction::create('GET', '/orders');

        $this->config->expects($this->once())
            ->method('getAction')
            ->with('getOrders')
            ->willReturn($action);

        $this->client->expects($this->once())
            ->method('send')
            ->with($action)
            ->willReturn(['id' => 99]);

        $response = $this->integration->send('getOrders');

        $this->assertInstanceOf(TestResponse::class, $response);
        $this->assertSame(['id' => 99], $response->toArray());
    }

    public function test_send_throws_if_mapper_class_is_invalid(): void
    {
        $this->expectException(InvalidMapperException::class);

        $action = BadMapperAction::create('GET', '/bad');

        $this->config->method('getAction')->willReturn($action);
        $this->client->method('send')->willReturn([]);

        $this->integration->send('bad');
    }

    public function test_dynamic_auth_uses_cached_token_on_second_call(): void
    {
        $dynAuth = new DynamicAuthorizationConfig(
            action: 'login',
            tokenField: 'token',
            ttl: 3600,
        );

        $actionWithDynAuth = TestAction::create('GET', '/orders', authorization: $dynAuth);

        $this->config->method('getAction')
            ->willReturnCallback(fn(string $name) => match ($name) {
                'getOrders' => $actionWithDynAuth,
                'login'     => LoginAction::create('POST', '/auth'),
                default     => throw new \InvalidArgumentException("Unknown: $name"),
            });

        // First call: cache miss → login is called
        $this->cache->expects($this->exactly(2))
            ->method('has')
            ->willReturnOnConsecutiveCalls(false, true);

        $this->cache->expects($this->once())
            ->method('set');

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn('my-jwt-token');

        $this->client->expects($this->exactly(3)) // login + getOrders + getOrders(cached)
            ->method('send')
            ->willReturnOnConsecutiveCalls(
                ['token' => 'my-jwt-token'],  // login response
                ['id' => 1],                   // first getOrders
                ['id' => 2],                   // second getOrders (cached token)
            );

        $this->integration->send('getOrders');
        $this->integration->send('getOrders');
    }

    public function test_dynamic_auth_throws_if_token_field_missing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('token');

        $dynAuth = new DynamicAuthorizationConfig(
            action: 'login',
            tokenField: 'token',
            ttl: 3600,
        );

        $this->config->method('getAction')
            ->willReturnCallback(fn(string $name) => match ($name) {
                'getOrders' => TestAction::create('GET', '/orders', authorization: $dynAuth),
                'login'     => LoginAction::create('POST', '/auth'),
                default     => throw new \InvalidArgumentException(),
            });

        $this->cache->method('has')->willReturn(false);
        $this->client->method('send')->willReturn(['wrong_key' => 'value']);

        $this->integration->send('getOrders');
    }
}

// Stubs

final readonly class TestAction extends AbstractAction
{
    public static function getName(): string   { return 'getOrders'; }
    public static function getMapper(): string { return TestMapper::class; }
}

final readonly class LoginAction extends AbstractAction
{
    public static function getName(): string   { return 'login'; }
    public static function getMapper(): string { return TokenMapper::class; }
}

final readonly class BadMapperAction extends AbstractAction
{
    public static function getName(): string   { return 'bad'; }
    public static function getMapper(): string { return \stdClass::class; } // Not an AbstractMapper
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

> **Nota:** este test sólo funciona correctamente si `InMemoryCacheAdapter` almacena la hora de expiración y la comprueba en `has()`. Si el adaptador actual ignora el TTL por ser in-memory, el test `test_expired_entry_is_not_available` fallará — y ese fallo es informativo: te está diciendo que el adaptador no respeta el TTL ni en memoria.

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
        $action  = $adapter->getAction('getOrders');

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
        $adapter->getAction('getOrders');
    }
}
```

Crea los ficheros de fixture en `tests/Fixtures/yaml/`:

**`tests/Fixtures/yaml/valid.yaml`:**
```yaml
getOrders:
    action: IntegrationEngine\Tests\Unit\Core\TestAction
    method: GET
    path: /orders
```

**`tests/Fixtures/yaml/missing_method.yaml`:**
```yaml
getOrders:
    action: IntegrationEngine\Tests\Unit\Core\TestAction
    path: /orders
```

---

## 3. Ejecutar los tests

Antes de ejecutar, crea `phpunit.xml` en la raíz del proyecto:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

Ejecuta:

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

Si usas el `Makefile` del proyecto:

```bash
make test    # phpunit
make stan    # phpstan
make cs      # cs-fixer
make qa      # los tres en secuencia
```

---

## 4. Preparar el repositorio Git

### 4.1 `.gitignore`

Crea `.gitignore` en la raíz:

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
integration-engine/
    src/
    tests/
        Unit/
        Fixtures/
    .gitignore
    .github/
        workflows/
            ci.yml          ← lo crearemos en el paso 6
    composer.json
    phpunit.xml
    README.md
    DOCUMENTATION.md
    CONTRIBUTING.md
    Makefile
    LICENSE              ← el proyecto declara MIT, crea este fichero
```

### 4.3 Fichero `LICENSE`

El `composer.json` declara `"license": "MIT"`. Crea el fichero para que sea válido:

```
MIT License

Copyright (c) 2025 TU_NOMBRE

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## 5. Publicar en GitHub

### 5.1 Crear el repositorio

1. Ve a [github.com/new](https://github.com/new)
2. Nombre: `integration-engine`
3. Visibilidad: **Public** (Packagist requiere repositorios públicos para el plan gratuito)
4. **No** inicialices con README ni .gitignore (ya los tienes)
5. Clic en **Create repository**

### 5.2 Primer push

```bash
cd integration-engine

git init
git add .
git commit -m "feat: initial release v1.0.0"

git remote add origin git@github.com:CarlosGude/integrationEngine.git
git branch -M main
git push -u origin main
```

### 5.3 Actualizar `composer.json` con la URL real

Abre `composer.json` y actualiza el campo `name` con tu vendor real de Packagist (ver paso 7) y añade los campos de soporte:

```json
{
    "name": "carlosgude/integration-engine",
    "description": "Hexagonal integration engine as a Symfony Bundle",
    "type": "symfony-bundle",
    "license": "MIT",
    "authors": [
        {
            "name": "Carlos Gude",
            "email": "tu@email.com"
        }
    ],
    "keywords": ["symfony", "bundle", "integration", "api", "hexagonal"],
    "homepage": "https://github.com/CarlosGude/integrationEngine",
    "support": {
        "issues": "https://github.com/CarlosGude/integrationEngine/issues",
        "source": "https://github.com/CarlosGude/integrationEngine"
    },
    "require": {
        "php": ">=8.2",
        "symfony/yaml": "^6.4|^7.0",
        "symfony/http-client": "^6.4|^7.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.0",
        "friendsofphp/php-cs-fixer": "^3.0",
        "symfony/framework-bundle": "^6.4|^7.0"
    },
    "autoload": {
        "psr-4": {
            "IntegrationEngine\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "IntegrationEngine\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true
    }
}
```

```bash
git add composer.json
git commit -m "chore: update composer.json with author and support links"
git push
```

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
        symfony: ['6.4', '7.2']

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

### 7.1 Elegir el nombre del vendor

El nombre en Packagist es `vendor/package`. El vendor normalmente es tu username de GitHub en minúsculas. Ejemplos:

- `carlosgude/integration-engine`
- `carlosgude/integration-engine`

Una vez elegido, actualiza `"name"` en `composer.json` y haz push antes de registrar en Packagist. **El nombre no se puede cambiar fácilmente después.**

### 7.2 Crear la primera tag de versión

Packagist trabaja con tags de Git como versiones. El formato es `MAJOR.MINOR.PATCH` con prefijo `v`:

```bash
git tag v1.0.0
git push origin v1.0.0
```

---

## 8. Publicar en Packagist

### 8.1 Registrar el paquete

1. Ve a [packagist.org](https://packagist.org) e inicia sesión (puedes usar tu cuenta de GitHub)
2. Clic en **Submit**
3. En el campo URL introduce la URL de tu repositorio de GitHub:
   ```
   https://github.com/CarlosGude/integrationEngine
   ```
4. Clic en **Check** — Packagist leerá el `composer.json` y mostrará el nombre y descripción detectados
5. Si todo es correcto, clic en **Submit**

El paquete ya es instalable:

```bash
composer require carlosgude/integration-engine
```

### 8.2 Configurar el webhook para actualizaciones automáticas

Sin el webhook, Packagist no se entera de los nuevos tags hasta que lo actualices manualmente. Configúralo así:

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

Desde ese momento, cada `git push` y cada nuevo tag se reflejan en Packagist automáticamente en segundos.

---

## 9. Versionado y releases

### Flujo para publicar una nueva versión

```bash
# 1. Asegúrate de que los tests pasan
make qa

# 2. Actualiza CHANGELOG.md si lo tienes
# 3. Haz commit de los cambios
git add .
git commit -m "feat: descripción del cambio"
git push

# 4. Crea la tag
git tag v1.1.0
git push origin v1.1.0
```

Packagist detectará el nuevo tag y expondrá `v1.1.0` como versión instalable.

### Cuándo subir MAJOR / MINOR / PATCH

| Tipo de cambio | Versión |
|---|---|
| Rompe compatibilidad hacia atrás (BC break) | MAJOR: `v2.0.0` |
| Nueva funcionalidad compatible hacia atrás | MINOR: `v1.1.0` |
| Bugfix o corrección sin nueva funcionalidad | PATCH: `v1.0.1` |

### BC breaks que requieren MAJOR

- Renombrar o eliminar una interfaz, clase abstracta o método público del Core
- Cambiar la firma de `Integration::send()`, `AbstractAction::create()`, `AbstractMapper::map()`
- Modificar los campos requeridos en el YAML de configuración

---

## 10. Checklist completa

Antes de publicar, verifica que tienes todo:

**Código:**
- [ ] `src/` completo y sin `final` en `SymfonyHttpClientAdapter`
- [ ] `tests/` con cobertura de los flujos principales
- [ ] `phpunit.xml` configurado
- [ ] Tests en verde localmente

**Repositorio:**
- [ ] `.gitignore` correcto (no hay `vendor/` en el repo)
- [ ] `LICENSE` presente (MIT)
- [ ] `README.md` con Quick Start funcional
- [ ] `DOCUMENTATION.md` completa
- [ ] `composer.json` con `name`, `authors`, `homepage`, `support`, `keywords`
- [ ] Primera tag `v1.0.0` creada y pusheada

**GitHub:**
- [ ] Repositorio público
- [ ] GitHub Actions en verde en la pestaña Actions
- [ ] Webhook de Packagist configurado

**Packagist:**
- [ ] Paquete registrado y visible en packagist.org
- [ ] `composer require carlosgude/integration-engine` funciona en un proyecto de prueba

