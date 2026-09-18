# Limpieza SonarCloud

Estado a 2026-09-18, análisis de `b6bc34b`, sacado de la API pública de SonarCloud
(proyecto `CarlosGude_integrationEngine`).

## Quality gate: falla por 3 condiciones

| Condición (código nuevo) | Actual | Umbral |
|---|---|---|
| Security rating | **D** | A |
| Reliability rating | **B** | A |
| Duplicated lines | **4,8 %** | ≤ 3 % |
| Maintainability rating | A | A ✅ |
| Security hotspots revisados | 100 % | 100 % ✅ |

"Código nuevo" es todo lo cambiado desde la versión anterior (31-05-2026), o sea casi todo.
Hay **107 issues abiertos**: 35 vulnerabilidades, 1 bug y 71 code smells. El listado del panel
filtrado por seguridad solo enseña los 35 primeros.

---

## 0. Causa raíz: SonarCloud ignora `sonar-project.properties`

No hay scanner en CI, así que el proyecto usa el **análisis automático** de SonarCloud, que solo
lee `.sonarcloud.properties`. Por eso `sonar.cpd.exclusions=landing/src/i18n/**,tests/**` no se
aplica. De las 1221 líneas duplicadas, 430 son `landing/src/i18n/{en,es}.js` (la misma estructura
en dos idiomas) y unas 740 son tests.

- [ ] Renombrar `sonar-project.properties` → `.sonarcloud.properties` (mismo contenido).

Efecto esperado: la duplicación baja a ~28 líneas (ver 1.5), y `tests/` pasa a analizarse como
código de test.

---

## 1. Bloquean el quality gate

### 1.1 Seguridad: hash débil (S4790, 24 issues)

Es `sha1()` para construir claves de caché, sin uso criptográfico:

- `src/Core/Contract/Auth/DynamicAuthorizationConfig.php:38`: clave del token cacheado
- `src/Infrastructure/Cache/CachingMiddleware.php:131`: clave de respuesta cacheada
- 22 en tests que recalculan esas mismas claves:
  - `tests/Core/BatchSendSadPathTest.php`: 208, 236, 237
  - `tests/Core/ConnectionResolutionTest.php`: 143, 147, 188, 192, 224, 227, 253, 318
  - `tests/Core/DynamicAuthSadPathTest.php`: 78, 129, 154
  - `tests/Core/DynamicAuthTest.php`: 98, 101, 257, 322, 326
  - `tests/Core/LoggingTest.php`: 57, 78, 103

- [ ] Cambiar a `hash('xxh128', …)` en los 2 de `src/`, que es un hash no criptográfico pensado
  para esto, y actualizar los tests.
- [ ] Anotarlo en el CHANGELOG: cambia el formato de las claves, así que tras actualizar hay un
  fallo de caché por token o respuesta. Es inofensivo: se vuelven a pedir una vez.

### 1.2 Seguridad: PRNG predecible (S2245, 8 issues)

`src/Infrastructure/Webhook/Handler/ProcessWebhookHandler.php:64-71`: `generateFailureId()` arma
un UUID v4 con `mt_rand()`.

- [ ] Generarlo con `random_bytes(16)`, ajustando los bits de versión y variante. No hacen falta
  dependencias nuevas.

### 1.3 Seguridad: workflows (3 issues)

- [ ] `.github/workflows/contract.yml:28` (S7637): fijar `shivammathur/setup-php` al mismo SHA
  que `php.yml` (`7c071dfe9dc99bdf297fa79cb49ea005b9fcadbc` # 2.37.1).
- [ ] `.github/workflows/contract.yml:40` y `.github/workflows/php.yml:101` (S8546,
  `composer update`): son intencionados. El test de contrato instala la demo contra este
  checkout, y la matriz prueba varias versiones de Symfony con `--prefer-lowest`. Marcarlos como
  **Accepted** en SonarCloud con esa justificación.

### 1.4 Fiabilidad (S2003, 1 issue)

- [ ] `tests/Bundle/Generator/WebhookFileGeneratorTest.php:146`: cambiar `require` por
  `require_once` en el autoloader del test.

### 1.5 Duplicación

- [ ] Se resuelve casi entera con el punto 0.
- [ ] Lo que queda en `src/` es `writeFile()`, idéntico en `MakeWebhookCommand` (101-128) y
  `MakeIntegrationCommand` (214-240). Extraerlo a un helper compartido en
  `src/Bundle/Generator/`.

---

## 2. Bug visible en el landing (no bloquea el gate, pero se ve en la web)

`landing/src/html.js`: dentro de los template literals, las barras de los namespaces PHP son
escapes inválidos y desaparecen al renderizar (`\C` → `C`). Comprobado con `getHTML()`: la web
muestra, en EN y en ES:

- `AppIntegrationShopifyShopifyObservabilitySetup` (línea 532)
- `'@IntegrationEngineCoreLifecycleLifecycleEventDispatcher'` (línea 534)
- `// SentrycaptureException(...)` (línea 519)

- [ ] Escribir las barras como `\\`. Son parte de los 38 avisos S6535; el resto son `\"`
  innecesarios, que solo son cosméticos.
- [ ] Añadir un guard en `landing/test/content-guards.test.js` que falle si el HTML renderizado
  contiene un namespace sin barras.

---

## 3. Mantenibilidad en `src/` (no bloquea el gate)

- [ ] **S108 ×7, `catch` vacíos**: el commit `6b6798a` borró los comentarios que los
  justificaban. Hay que restaurarlos:
  - 6 mappers (`Shopify{Customer,Inventory,Order,Product}…Mapper`,
    `WooCommerce{Order,Product}…Mapper`): una fecha no parseable se queda en `null`
  - `src/Infrastructure/Webhook/WebhookPlatformRegistry.php:72`: una cabecera `X-Platform`
    inválida cae a la detección por ruta
- [ ] **S1068 ×2**: `IntegrationEngine::$logger` y `AuthenticationHandler::$integrationName` son
  propiedades promovidas que solo se usan en el constructor. Pasarlas a parámetros normales; la
  firma pública no cambia.
- [ ] **S1481 ×2**: `src/Bundle/Generator/WebhookFileGenerator.php:66-67` tiene dos variables sin
  usar (`$headerName`, `$verifierType`).
- [ ] **S1144**: `MiddlewareClient::dispatchBatch` es un **falso positivo**, porque se usa como
  first-class callable (`$this->dispatchBatch(...)`). Marcarlo así en SonarCloud.
- [ ] **S1172**: `MultiPlatformWebhookController::ingest()` no usa `$platform`. Quitarlo o
  aceptarlo, ya que el controller está deprecated.
- [ ] **S107**: `IntegrationCompilerPass::wireIntegration()` tiene 8 parámetros. Agrupar lo
  resuelto (mapa de adapters y middlewares registrados) o aceptarlo.
- [ ] **S1142 ×3** (más de 3 `return`): `TimestampedHmacSignatureVerifier::verify`,
  `MakeObservabilityCommand::guessNamespace` y `MultiPlatformWebhookController::ingest`. Son
  guardas tempranas legítimas: aceptarlo, o refactorizar cuando se toquen.

---

## 4. Mantenibilidad en tests (no bloquea el gate)

- [ ] S1481 ×5: `tests/Infrastructure/YamlConfigAdapterTest.php` 44, 55, 66, 77, 92, `$_` sin
  usar.
- [ ] S1172 ×2: `tests/Infrastructure/Cache/CachingMiddlewareTest.php` 311, 316, parámetro
  `$reqs` sin usar.
- [ ] S3415: `tests/Infrastructure/Cache/CachingMiddlewareTest.php:237`, argumentos del assert
  invertidos (expected/actual).
- [ ] S1185: `tests/Infrastructure/YamlConfigAdapterWebhooksTest.php:15`, `tearDown()` que solo
  llama al padre.
- [ ] S112 ×2: `tests/Fake/FakeLogger.php:47` y `tests/Fake/WebhookMapperResolverAdapter.php:96`
  lanzan una `\Exception` genérica.

---

## 5. Landing: tests sin aserciones (S2699 ×5)

`landing/test/content-guards.test.js` (líneas 23, 39, 65, 73, 85) verifica lanzando `Error` dentro
de un bucle, y Sonar no lo reconoce como aserción.

- [ ] Pasarlos a `assert.ok(...)` o `assert.doesNotMatch(...)` de `node:assert`.

---

## Orden sugerido

1. **Punto 0** (`.sonarcloud.properties`): el mayor efecto con cero riesgo.
2. **1.1 – 1.5**: dejan el quality gate en verde.
3. **Punto 2**: el bug se ve en la web pública.
4. **Puntos 3 – 5**: cuando se toquen esos ficheros.
