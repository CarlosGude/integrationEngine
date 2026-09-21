# Roadmap de fixes

Estado a 2026-09-19 (`main` = `3298279`, v5.4.0 publicada). Sale de una revisión completa del
proyecto: PHPUnit, PHPStan, php-cs-fixer, Infection, cobertura clover (xdebug), GitHub Actions,
SonarCloud y Packagist.

Aquí solo hay arreglos de cosas que están mal. Ninguna funcionalidad nueva.

## Foto actual

| Check | Estado |
|---|---|
| PHPUnit | ✅ 667 tests |
| PHPStan max | ✅ 0 errores |
| php-cs-fixer | ✅ limpio |
| Infection | ✅ MSI cubierto 98-99 %, mínimo 95 % |
| Cobertura de líneas (clover) | 86 % (1901/2211), aunque Infection dice 100 % (punto 4) |
| CI (`php.yml`) | ✅ reactivado; primera ejecución en el próximo push |
| Contract test (`contract.yml`) | ✅ |
| SonarCloud | ✅ gate OK, 0 issues |
| Packagist | ✅ v5.4.0 |

`make qa` y `make ci` pasan. Quedan los puntos 4, 5 y 6.

---

## 0. La suite estaba en rojo: un test con fecha fija

`tests/Infrastructure/Webhook/WebhookIdempotencyTest.php:124` (`testCleanupKeepsRecentFingerprints`)
registra la huella con la fecha fija `2026-09-18T10:00:00Z` como si fuera "ahora". Pero el
`cleanup()` del fake (`tests/Fake/WebhookIdempotencyAdapter.php:42`) calcula el corte con el reloj
real (`now - 86400 seconds`). Desde el 2026-09-19 a las 10:00 UTC la huella tiene más de 24 h, así
que se borra y el test falla siempre.

- [x] Usar en ese test una fecha relativa al reloj real (`new \DateTimeImmutable('-1 hour')`),
  como ya hacen `WebhookDlqTest` y `WebhookEventStateTransitionTest`.

---

## 1. Posible bug: el parser de webhooks mapea y descarta el resultado

`src/Infrastructure/Webhook/IntegrationWebhookRequestParser.php:138-139` llama a
`$this->getMapper()->map($payload, $headers)` y descarta el resultado. El `RemoteEvent` lleva el
payload crudo, y el mapeo real se hace después, en el consumer (`WebhookEventDispatcher::dispatch()`).

- WEBHOOK.md ("How It Fits Together") dice que el parser solo verifica la firma y decodifica el JSON.
- WEBHOOK.md:199 dice que, si el proveedor envía varios tipos de evento a la misma URL, el tipo se
  filtra en el consumer. Con esta llamada, un mapper que falle con el payload de otro tipo (una
  clave que falta, un `TypeError` al construir el DTO) ya falla en el parser. Symfony responde 500
  en vez de 406, el proveedor reintenta y el filtro del consumer nunca se ejecuta.
- Cada evento se mapea dos veces.
- Ningún test lo cubre: si se quita la línea 139, no falla nada (mutante escapado).

- [x] Decidido: la llamada no se puso a propósito. Quitadas las líneas 138-139 (`getMapper()` +
  `map()`).
- [x] Quitado también el bucle que construía `$headers`, que solo existía para esa llamada. Con eso
  desaparecen 2 de los 74 mutantes escapados.
- [x] Actualizados los docblocks de la clase y de `doParse()`, que decían que el parser mapea.
- [x] Entrada en el CHANGELOG (`Unreleased` → `Fixed`).

Queda un mutante escapado en este fichero que no depende de este arreglo: la línea 63
(`MethodRequestMatcher('POST')`), en el punto 3.3.

---

## 2. El CI principal estaba desactivado (reactivado)

`.github/workflows/php.yml` (cs, PHPStan, matriz de tests, generador en PHP 8.2 y mutation) está en
`disabled_manually` desde el 2026-06-04. Sus últimas ejecuciones fallaban.

- Desde junio no se prueba la matriz PHP 8.2/8.3/8.4 × Symfony 6.4/7.4/8 ni `--prefer-lowest`. En
  local se usa PHP 8.5.6. He buscado sintaxis de PHP 8.3/8.4 en `src/` y `tests/` y no hay, pero las
  dependencias mínimas siguen sin comprobarse.
- El badge del README apunta a este workflow, así que en GitHub y en Packagist sale roto.

- [x] Hechos antes los puntos 0 y 3: si no, el job `tests` fallaba por el test de la fecha fija y el
  job `mutation` por el umbral.
- [x] Reactivado con `gh workflow enable php.yml`. El workflow solo se dispara en push y en pull
  request, así que la primera ejecución sale con el próximo push.
- [x] `php.yml:140`: el comentario del job `generator-php82` remitía a un `PLAN.md` dentro de
  `agent/`, que no existe. Ahora apunta a la receta de `.symfony-recipes/`, que es lo que arregla
  el registro automático cuando la acepten en symfony/recipes-contrib.
- [x] Comprobadas en local las dos patas de la matriz que más riesgo tenían, en copias de trabajo
  aparte: `--prefer-lowest --prefer-stable` (Symfony 6.3/6.4) y Symfony 8.1, las dos con 666 tests
  en verde. Queda por comprobar el runtime de PHP 8.2 y 8.3, que en local no hay: `src/` y `tests/`
  no usan sintaxis posterior a 8.2 (sin constantes de clase tipadas, sin `#[\Override]`, sin
  funciones de 8.3/8.4).

---

## 3. Mutation testing por debajo del umbral (arreglado)

**Resuelto.** `infection --threads=max`: 901 mutantes y entre 9 y 11 escapados según la ejecución,
o sea un MSI cubierto del 98-99 % (el mínimo es 95 %).

Los 9 que escapan son los bits del UUID de `generateFailureId()`, que se quedan sin ignorar a
propósito (ver 3.2). Antes de empezar eran 68, repartidos en 2 exclusiones obsoletas (3.1), 25
equivalentes (3.2) y 41 huecos de test (3.3).

### 3.1 Exclusiones obsoletas (2 mutantes)

El refactor sacó esta lógica de `IntegrationEngine`, pero `infection.json5` y la tabla "Equivalent
mutants" de `docs/advanced/QUALITY.md` siguen apuntando a los métodos antiguos. Por eso los mismos
mutantes equivalentes escapan en su nueva ubicación.

- [x] `ReturnRemoval`: `IntegrationEngine::dispatchBatch` → `BatchDispatcher::dispatch::36`, fijado
  a la línea para que el `return` de verdad del método siga mutándose.
- [x] `CastString`: `IntegrationEngine::resolveConnection` → `ConnectionResolver::resolve`.
- [x] Actualizadas las dos filas en QUALITY.md. El razonamiento no cambia.

### 3.2 Equivalentes sin documentar (17 mutantes ignorados)

La regla de QUALITY.md obliga a añadir cada uno a la tabla, con su razonamiento, antes de ponerle
un `ignore`. Los 8 de abajo ya están en `infection.json5` y en la tabla, fijados por línea siempre
que el método tenga además mutantes que los tests sí matan.

- [x] `IncrementInteger`/`DecrementInteger` ×10 en `src/Core/IntegrationEngine.php` 111, 123, 125,
  137 y 148 (el `* 1000` de las duraciones de los eventos de ciclo de vida). Mismo razonamiento que
  la fila de `TracingMiddleware`, con una fila propia para `IntegrationEngine::send`.
- [x] `Throw_` en `src/Core/Dispatch/ResponseBuilder.php:41`. `AbstractMapper::map()` es `final` y
  repite la misma comprobación con la misma excepción, así que quitar el `throw` no cambia nada.
- [x] `LogicalNot` en `src/Core/Lifecycle/LifecycleEventDispatcher.php:31`.
  `$this->subscribers[$eventClass][] = …` ya crea el array, así que el `if (!isset(...))` sobra.
- [x] `LessThanOrEqualTo` en `src/Core/Webhook/HmacSha256SignatureVerifier.php:38`. Una firma igual
  al prefijo da un hash `''`, y `hash_equals` falla igual.
- [x] `LogicalOr` en `src/Core/Webhook/TimestampedHmacSignatureVerifier.php:45`. Sin hashes, el
  bucle devuelve `false`. Un timestamp no numérico se convierte en 0 y queda fuera de tolerancia,
  salvo que la tolerancia sea mayor que el epoch actual. **Es la única exclusión que cuesta algo**:
  los dos `||` están en la misma línea, así que también tapa el segundo, que los tests sí matan.
  Medido: 1 mutante muerto silenciado, ninguno más.
- [x] `CastInt` en `TimestampedHmacSignatureVerifier.php:68`. `format('U')` es un string numérico y
  la resta da lo mismo.
- [x] `CastInt` en `TimestampedHmacSignatureVerifier.php:49`. Decidido documentarlo: solo cambia
  algo con timestamps numéricos no canónicos (`"1700000000.0"`, `" 1700000000"`), que ningún
  proveedor envía. Fijado a la línea, así que el cast de la 45 se sigue mutando.

**Los bits del UUID de `ProcessWebhookHandler::generateFailureId()` (10 escapados) se quedan sin
ignorar.** `WebhookDlqTest.php:108` sí comprueba el formato v4 con una regex, y eso mata los
mutantes que mueven los índices de byte. Lo que sobrevive son los desplazamientos de máscara que
solo tocan bits que la regex deja libres, más dos que mueren o no según lo que devuelva
`random_bytes()` (por eso el MSI baila un par de mutantes entre ejecuciones).

Probé a ignorarlos de tres formas y ninguna sirve: por método tapa 10 mutantes que los tests matan,
por línea tapa 8, y `ignoreSourceCodeByRegex` no filtra nada, porque Infection compara contra el
literal ya normalizado y no contra `0x0F`. Cada máscara comparte línea con su índice de byte, y la
granularidad de Infection llega a la línea. Queda explicado en QUALITY.md, en "Not ignored: the
failure id's UUID bits".

### 3.3 Huecos de test (41 mutantes)

Todos cubiertos. Cada línea dice el test que los mata.

**Core**

- [x] `IntegrationEngine.php:149` (1): el engine emite `ActionFailed` cuando falla el envío —
  `LifecycleDurationsTest::actionFailedIsDispatchedWithTheErrorAndItsDuration`.
- [x] `IntegrationEngine.php` 111, 123, 125, 137 y 148, `Minus`/`Multiplication` (10): las
  duraciones de `HttpResponseReceived`, `ResponseMapped`, `ActionCompleted` y `ActionFailed` se
  comprueban por orden de magnitud, como ya hacía `TracingMiddlewareTest` —
  `tests/Core/Lifecycle/LifecycleDurationsTest.php`, con un cliente y un mapper que tardan 20 ms
  (`FakeSlowAction` y `FakeSlowMapper`).
- [x] `HmacSha256SignatureVerifier.php:34` (1): firma con prefijo incorrecto **de la misma
  longitud** (`sha999=` + hash válido), que sin el `return false` se aceptaría —
  `HmacSha256SignatureVerifierTest::testRejectWhenPrefixIsWrongButAsLongAsTheExpectedOne`.
- [x] `SignatureConfig.php` 32, 38 y 78 (3): las tres validaciones —
  `tests/Core/Webhook/SignatureConfigTest.php`.
- [x] `WebhookEventState.php:51` (2): los seis brazos de `label()` —
  `WebhookEventStateTransitionTest::testEveryStateHasItsOwnLabel`.

**Infrastructure**

- [x] `ProcessWebhookHandler.php:46` (1): el handler despacha el evento tipado —
  `WebhookDlqTest::testSuccessfulWebhookDispatchesTheTypedEvent`.
- [x] `WebhookFingerprinter.php:53` (1): claves anidadas en distinto orden dan la misma huella —
  `WebhookIdempotencyTest::testNestedPayloadOrderDoesntAffectDuplicate`.
- [x] `YamlConfigAdapter.php:44` (1): acciones declaradas después del bloque `webhooks:` —
  `YamlConfigAdapterTest::getActionFindsActionsDeclaredAfterTheWebhooksBlock`.
- [x] `IntegrationWebhookRequestParser.php:64` (1): se rechaza lo que no sea POST, y
  `:135` (3): id entero, id no escalar e id ausente —
  `tests/Infrastructure/Webhook/IntegrationWebhookRequestParserTest.php`.
- [x] `WebhookEventRegistry.php` 47 y 70 (2): el mensaje de error lista los tipos registrados (no
  las clases DTO) y `listEventTypes()` devuelve esos tipos —
  `ShopifyEventDiscoveryTest`.
- [x] Mappers (13): `ShopifyOrderCreatedMapper` 54-58 (10, campos y valores por defecto de
  `line_items`, con cantidades y precios como string),
  `WooCommerceProductUpdatedMapper` 47-48 (2, `stock_quantity` como string y `status` por defecto
  `publish`) y `ShopifyCustomerUpdatedMapper` 50 (1, `verified_email` ausente) —
  `ShopifyEventDiscoveryTest` y `WooCommerceWebhookIngestionsTest`.

**Reclasificado**: `ShopifyWebhookController.php:68-69` (2) no era un hueco de test. Esas cabeceras
se pasan al mapper, y ningún mapper de Shopify las lee, así que nada de lo que el controller expone
distingue el mutante. Documentado como equivalente en 3.2 y en QUALITY.md.

---

## 4. Código que ningún test ejecuta (e Infection no lo ve)

Infection reporta "Mutation Code Coverage: 100 %, Not Covered: 0", pero el clover de PHPUnit da un
85,2 % de líneas. Infection no genera ningún mutante en estas líneas, así que no cuentan para el
MSI. No he encontrado el motivo: no hay anotaciones `@infection-ignore` ni opciones en
`infection.json5` que lo expliquen.

- [ ] Averiguar por qué Infection no genera mutantes en las líneas no cubiertas.

Estas partes no tienen ningún test:

- [ ] `src/Infrastructure/Http/GraphQLClientAdapter.php:91-136` (`sendMany()`) y `:257-270`
  (`sendManySequentially()`): todo el camino de lotes de GraphQL.
- [ ] `src/Infrastructure/Lifecycle/ObservabilitySetup.php`, casi entera. Es la configuración que
  recomienda OBSERVABILITY.md.
- [ ] `src/Infrastructure/Webhook/WebhookEventDispatcher.php:38-44`: la excepción cuando el mapper
  no corresponde al evento.
- [ ] `IntegrationWebhookRequestParser.php:118-122, 126-129`: las respuestas 406 por JSON mal
  formado y por un payload que no es un objeto.
- [ ] `src/Infrastructure/Http/SymfonyHttpClientAdapter.php:246-247`: un fallo dentro del envío
  secuencial con request middlewares.
- [ ] `src/Infrastructure/Http/ResolvesAuthHeaders.php:79-83`: un parámetro de auth estática que no
  es string.
- [ ] `src/Bundle/DependencyInjection/Compiler/MiddlewareResolver.php:78-82`: un request middleware
  etiquetado con una clase que no existe.
- [ ] `src/Bundle/Command/MakeWebhookCommand.php` y `MakeObservabilityCommand.php`, casi enteros.
  `src/Bundle` está excluido de Infection, así que aquí solo lo detecta la cobertura.

Menores: los getters de los eventos de `src/Core/Lifecycle/`, `WebhookPlatform::label()`, la rama
de fecha no parseable de cada mapper de Shopify y WooCommerce, y `getSignatureSecret()` de los
parsers de Shopify.

---

## 5. Documentación desfasada

- [x] `README.md:6` decía "Status: v5.2.0 Live". Ahora pone v5.4.0, con la lista de features al
  día (eventos que sí llegan a los listeners, tiempos separados, receta de Flex) y el bloque de
  webhooks describiendo parser + consumer + listener.
- [ ] `ROADMAP.md`:
  - "Recently shipped" se queda en v5.1.0 (faltan 5.2.0 a 5.4.0) y da por entregados la
    idempotencia, la DLQ, la state machine, el audit trail y la integración con Messenger. El
    CHANGELOG de 5.3.1/5.4.0 dice que son contratos sin adaptador, y "Out of scope" excluye un
    bridge de Messenger (ADR 0008).
  - "Open-source plugins ecosystem" aparece a la vez en "Later" y en "Out of scope".
- [x] Landing, sección de webhooks: describía un flujo que no existe (YAML que nadie lee,
  `MultiPlatformWebhookController` deprecado, clave `mapper_class`, mapper sin `$headers` y lógica
  de negocio dentro del mapper). Reescrita con routing + parser + listener, en EN y ES.
- [x] Landing, bloque de estado: decía "v5.2.0 Live" y "607 tests"; ahora pone v5.4.0, 666 tests y
  el mutation score. Las etiquetas de versión de las secciones de webhooks y observabilidad también
  estaban desfasadas.
- [x] El `PLAN-STATUS` que vivía en `docs/` era un plan interno desfasado, dentro de una carpeta
  pública. Borrado.
- [ ] `CLAUDE.md` no coincide con el `Makefile`: `make qa` también ejecuta PHPStan (CLAUDE.md dice
  cs + test), y `make pre-commit` es un alias de `ci`, que ejecuta `cs` en dry-run y no `cs-fix`.
  Igualar uno de los dos.

---

## 6. Limpieza del repo

- [x] `.deptrac.cache` (2,8 MB) estaba versionado sin que Deptrac esté en `composer.json`. Fuera del
  índice y añadido al `.gitignore`.
- [x] Borradas las ramas mergeadas `day01-b1.1-…` a `day05-b1.5-…`, en local y en `origin`. En
  `origin` ya solo queda `main`.
- [x] Borradas las tres ramas locales sin mergear: `day06-b1.6-deptrac-hexagonal-architecture`
  (`c9ac4fd`), `infection-test` (`c4e4f6a`) y `multi-request` (`21a695e`). Las puntas quedan en el
  reflog unos 90 días por si acaso.
- [x] `.mcp.json` fuera del control de versiones (sigue en disco, para que tu PhpStorm no se entere)
  y añadido al `.gitignore`.

---

## Orden sugerido

1. **Punto 0**: la suite está en rojo y el arreglo es de una línea.
2. **Punto 1**: decidir qué hacer con el parser.
3. **3.1 y 3.2**: cambios de configuración y de documentación, sin riesgo.
4. **3.3 y punto 4**: tests, empezando por `HmacSha256SignatureVerifier`, `ProcessWebhookHandler`,
   `ActionFailed` y el `sendMany()` de GraphQL.
5. **Punto 2**: reactivar el CI cuando `make ci` pase en local.
6. **Puntos 5 y 6**.
