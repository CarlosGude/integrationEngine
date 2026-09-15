# IntegrationEngine · Tareas del bundle

> **Pestaña: bundle.** Repo `github.com/CarlosGude/integrationEngine` (incluye `landing/`).
> El orden de ejecución lo marca **[PLAN.md](PLAN.md)**. Aquí está el *cómo* de cada tarea.
> Estándares de calidad, TDD y DoD: **PLAN.md § 1**. No se repiten salvo cuando una tarea los concreta.

**Punto de partida:** v4.1.0 · PHP `>=8.2` · Symfony `^6.4|^7.0|^8.0` · ~397 tests · PHPStan max sobre `src`.

**Capas actuales (no romper):**
- `src/Core/` — dominio del engine, sin dependencias de Symfony (solo PSR: `psr/log`, `psr/cache`).
- `src/Infrastructure/` — adaptadores (HTTP, YAML, caché, debug).
- `src/Bundle/` — integración con Symfony (DI, comando, generador, plantillas Twig del profiler).
- `tests/Fake/` — dobles de test reutilizables (`FakeClient`, `FakeCache`, …). Usarlos antes de crear dobles nuevos.

---

## FASE 1 · Presentable

### B1.1 · Puertas de calidad unificadas

**Necesidad:** hoy conviven cuatro umbrales de MSI (`infection.json5`: 100/100; CI: 92/92; `make mutation`: 92/92; `make pre-commit`: 98/99) y rutas de exclusión que ya no existen. Nadie puede saber cuál es el estándar real.

**Archivos:** `infection.json5`, `phpunit.xml.dist`, `phpstan.neon`, `Makefile`, `.github/workflows/php.yml`, `docs/QUALITY.md` (nuevo).

**Pasos:**
1. **Verificación roja (script):** crear `bin/check-quality-config.php` (o test `tests/Quality/QualityConfigTest.php`, preferible) que:
   - lea `infection.json5` (quitando comentarios) y compruebe `minMsi === 85` y `minCoveredMsi === 95`;
   - compruebe que cada ruta de `source.excludes` existe bajo `src/`;
   - lea `phpunit.xml.dist` y compruebe que cada `<file>` de `<exclude>` existe;
   - compruebe que `Makefile` y `.github/workflows/*.yml` no contienen `--min-msi` ni `--min-covered-msi`.
   Ejecutar y confirmar que falla por los cuatro motivos.
2. **`infection.json5`:**
   - `minMsi: 85`, `minCoveredMsi: 95`.
   - `excludes` con rutas reales:
     - `Bundle`
     - `Core/Contract/Action/ActionBodyInterface.php`
     - `Core/Contract/Action/ActionContextInterface.php`
     - `Core/Contract/Client/ClientInterface.php`
     - `Core/Contract/Client/RequestHeadersInterface.php`
     - `Core/Contract/Response/ResponseInterface.php`
     - `Core/Port/CachePort.php`
     - `Core/Port/ConfigPort.php`
     - `Core/Registry/IntegrationName.php`
   - `Throw_.ignore`: `IntegrationEngine\Core\Contract\Action\AbstractAction` y `IntegrationEngine\Core\IntegrationEngine`.
   - Revisar si excluir **todo** `Bundle` sigue justificado. Si el MSI resultante lo permite, reducir la exclusión a `Bundle/Resources` y documentar la decisión en `docs/QUALITY.md`. Si no, mantenerla y documentar por qué.
   - `logs` a `var/infection/`.
3. **`phpunit.xml.dist`:** corregir `<exclude>` con las mismas rutas reales de interfaces.
4. **`phpstan.neon`:** `paths: [src, tests]`, mantener `level: max`, `treatPhpDocTypesAsCertain: true`. Añadir `phpstan/phpstan-phpunit` si los tests generan ruido legítimo de PHPUnit (sin `ignoreErrors`).
5. **`Makefile`:** targets exactamente como PLAN.md § 1.1. Borrar `pre-commit` con umbrales propios (o dejarlo como alias de `ci`).
6. **CI:** el job `mutation` ejecuta `vendor/bin/infection --threads=max --show-mutations` sin umbrales en CLI. Añadir `phpstan` sobre `tests` (ya incluido vía `phpstan.neon`).
7. **`docs/QUALITY.md`:** tabla de puertas, comandos, MSI y MSI cubierto reales tras el cambio, y fecha.
8. Si PHPStan sobre `tests` o el nuevo MSI fallan, **arreglar el código o los tests**, nunca los umbrales.

**Criterios de aceptación:**
- [ ] `QualityConfigTest` verde.
- [ ] `make ci` verde en local y en CI.
- [ ] MSI ≥ 85 y MSI cubierto ≥ 95 con las exclusiones corregidas.
- [ ] `docs/QUALITY.md` enlazado desde `CONTRIBUTING.md`.

**Verificación:**
```bash
vendor/bin/phpunit --filter QualityConfigTest
grep -rnE "min-msi|min-covered-msi" Makefile .github/ ; test $? -eq 1
make ci
```

---

### B1.2 · Compatibilidad PHP 8.2 del generador

**Necesidad:** `composer.json` declara `php >=8.2`, pero `make:integration` genera `public const string NAME`, que es sintaxis de PHP 8.3. El generador produce código que da error fatal en una versión soportada.

**Archivos:** `src/Bundle/Generator/TemplateRenderer.php` (línea ~32), `src/Core/Registry/IntegrationName.php` (docblock, línea ~14), `tests/Bundle/Generator/TemplateRendererTest.php`, `README.md`, `DOCUMENTATION.md`, `DOCUMENTATION_ES.md`, `docs/*.md`, `agent/*.md`.

**TDD:**
1. **Rojo:** en `TemplateRendererTest` añadir:
   - `testGeneratedIntegrationDoesNotUseTypedClassConstants()`: renderiza la plantilla del facade y afirma `assertDoesNotMatchRegularExpression('/const\s+(string|int|bool|float|array)\s+[A-Z_]+/', $output)`.
   - `testGeneratedIntegrationDeclaresNameConstant()`: afirma que contiene `public const NAME = '<name>';`.
2. **Verde:** cambiar la plantilla a `public const NAME = '{$name}';`.
3. **Refactor:** actualizar el docblock de `IntegrationName` y todos los ejemplos de documentación.
4. Añadir `tests/Documentation/NoTypedConstantsInDocsTest.php`: recorre `README.md`, `DOCUMENTATION*.md`, `docs/**/*.md`, `agent/**/*.md` y falla si encuentra constantes tipadas dentro de bloques de código PHP.

**Criterios de aceptación:**
- [ ] Los tres tests nuevos pasan y fallaban antes.
- [ ] `grep -rn "const string" src/ README.md DOCUMENTATION*.md docs/ agent/` sin resultados.
- [ ] Entrada `### Fixed` en `CHANGELOG.md` (sección `Unreleased`, se publicará en v4.1.1).

---

### B1.3 · Matriz de CI y job del generador

**Necesidad:** el CI solo prueba PHP 8.4; por eso B1.2 no se detectó. Lo que declara `composer.json` debe estar probado.

**Archivos:** `.github/workflows/php.yml`, `README.md`.

**Pasos:**
1. **Job `tests` con matriz:**
   ```yaml
   strategy:
     fail-fast: false
     matrix:
       php: ['8.2', '8.3', '8.4']
       symfony: ['6.4.*', '7.4.*', '8.*']
       deps: ['lowest', 'stable']
       exclude:
         - { php: '8.2', symfony: '8.*' }
         - { php: '8.3', symfony: '8.*' }
   ```
   - Verificar antes en la documentación de Symfony los requisitos de PHP de 7.4 y 8.x. Si difieren de lo anterior, ajustar exclusiones y anotarlo en el PR.
   - Instalar con `symfony/flex` global y `SYMFONY_REQUIRE=${{ matrix.symfony }}`:
     ```bash
     composer global config --no-plugins allow-plugins.symfony/flex true
     composer global require --no-progress --no-scripts --no-plugins symfony/flex
     composer update --prefer-dist --no-progress ${{ matrix.deps == 'lowest' && '--prefer-lowest --prefer-stable' || '' }}
     ```
   - Paso de evidencia: `composer show symfony/http-client | grep versions`.
   - Ejecutar solo `vendor/bin/phpunit` en la matriz. `cs`, `stan`, `mutation` y `deptrac` siguen en un único job con PHP 8.4 y deps estables.
2. **Job `generator-php82`:**
   - PHP 8.2, Symfony 6.4.
   - Crear app mínima temporal con `composer create-project symfony/skeleton:"6.4.*" /tmp/app`.
   - Añadir un repositorio `path` al checkout actual **solo dentro del job** e instalar el bundle.
   - Ejecutar `php bin/console make:integration Acme GetThing --no-interaction` (añadir flags o respuestas por `stdin` según las preguntas actuales del comando; si el comando no admite modo no interactivo, **crear primero un test y el soporte de `--no-interaction` con valores por defecto**, y anotarlo en el PR).
   - `find src/Infrastructure/Integrations/Acme -name '*.php' -print0 | xargs -0 -n1 php -l`.
3. Badge del workflow en `README.md`.

**Criterios de aceptación:**
- [ ] Todas las celdas de la matriz verdes.
- [ ] `generator-php82` verde.
- [ ] Prueba negativa documentada en el PR (rama temporal con `const string` → job rojo).

---

### B1.4 · Test de documentación: imports

**Necesidad:** unos 30 ejemplos de la documentación usan namespaces anteriores a la reorganización de `Core/Contract/` en subcarpetas. Copiar y pegar un ejemplo no funciona.

**Archivos:** `tests/Documentation/DocumentationImportsTest.php` (nuevo), `README.md`, `DOCUMENTATION.md`, `DOCUMENTATION_ES.md`, `ARCHITECTURE.md`, `TESTING.md`, `docs/*.md`, `agent/*.md`, `integration-engine-agent-guide.md`.

**TDD:**
1. **Rojo:** `DocumentationImportsTest`
   - `#[DataProvider('markdownFiles')]` que lista todos los `.md` del repo excepto `vendor/`, `var/`, `node_modules/`, `landing/`.
   - Extrae con regex `^use\s+(IntegrationEngine\\[\w\\]+)\s*;` (multilínea) las clases importadas.
   - Para cada una: `assertTrue(class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn) || enum_exists($fqcn), "$file: $fqcn does not exist")`.
   - Mensaje de fallo con archivo y línea.
2. Ejecutar y pegar la lista de fallos en el PR.
3. **Verde:** corregir según el mapa:

   | Obsoleto | Correcto |
   |---|---|
   | `Core\Contract\AbstractAction` | `Core\Contract\Action\AbstractAction` |
   | `Core\Contract\ActionBodyInterface` | `Core\Contract\Action\ActionBodyInterface` |
   | `Core\Contract\ActionContextInterface` | `Core\Contract\Action\ActionContextInterface` |
   | `Core\Contract\DefaultActionContext` | `Core\Contract\Action\DefaultActionContext` |
   | `Core\Contract\GraphQLBodyInterface` | `Core\Contract\Action\GraphQLBodyInterface` |
   | `Core\Contract\PathResolvableContextInterface` | `Core\Contract\Action\PathResolvableContextInterface` |
   | `Core\Contract\ClientInterface` | `Core\Contract\Client\ClientInterface` |
   | `Core\Contract\BatchClientInterface` | `Core\Contract\Client\BatchClientInterface` |
   | `Core\Contract\ClientAdapterInterface` | `Core\Contract\Client\ClientAdapterInterface` |
   | `Core\Contract\RequestHeadersInterface` | `Core\Contract\Client\RequestHeadersInterface` |
   | `Core\Contract\AbstractMapper` | `Core\Contract\Mapper\AbstractMapper` |
   | `Core\Contract\ResponseInterface` | `Core\Contract\Response\ResponseInterface` |

4. Revisar también los ejemplos que usan clases sin `use` explícito (por ejemplo, `new EngineRequest(...)` con `EngineRequest::create`) y corregir llamadas a métodos inexistentes detectadas durante la revisión (anotarlas en el PR).

**Criterios de aceptación:**
- [ ] Test verde; fallaba antes con la lista completa.
- [ ] El test se ejecuta en la suite normal (no en un grupo aparte).

---

### B1.5 · Test de enlaces y guía de agentes única

**Necesidad:** hay dos guías de agentes distintas (raíz: 1013 líneas; `agent/`: 899) y `docs/AI-AGENT-USAGE.md` enlaza a `.agent/`, que no existe.

**Archivos:** `tests/Documentation/DocumentationLinksTest.php` (nuevo), `agent/integration-engine-agent-guide.md`, `integration-engine-agent-guide.md` (borrar), `docs/AI-AGENT-USAGE.md`, `landing/README.md`.

**TDD:**
1. **Rojo:** `DocumentationLinksTest`
   - Extrae enlaces Markdown `[texto](destino)` y referencias en backticks con forma de ruta relativa que acaben en `.md`.
   - Ignora `http(s)://`, `mailto:` y anclas puras `#…`.
   - Para rutas relativas: resuelve contra el directorio del archivo y comprueba `file_exists` (quitando `#ancla`).
   - Debe fallar con `docs/AI-AGENT-USAGE.md → .agent/integration-engine-agent-guide.md`.
2. **Verde:**
   - `diff` entre las dos guías; fusionar en `agent/integration-engine-agent-guide.md` conservando la información más reciente (comprobar contra el código actual: namespaces, opciones YAML, métodos).
   - Borrar la guía de la raíz.
   - Corregir las tres referencias `.agent/` → `../agent/` (relativa correcta desde `docs/`).
   - Quitar `snippets.js` del árbol de `landing/README.md`.
3. Buscar en `README.md` y `docs/` referencias a la guía de la raíz y actualizarlas.

**Criterios de aceptación:**
- [ ] `DocumentationLinksTest` y `DocumentationImportsTest` verdes.
- [ ] `test ! -f integration-engine-agent-guide.md`.

---

### B1.6 · Arquitectura hexagonal verificada con Deptrac

**Necesidad:** el README y el CV afirman arquitectura hexagonal; ninguna herramienta lo impide romper.

**Archivos:** `deptrac.yaml` (nuevo), `composer.json`, `Makefile`, `.github/workflows/php.yml`, `ARCHITECTURE.md`.

**Pasos:**
1. `composer require --dev deptrac/deptrac` (verificar el nombre actual del paquete en Packagist antes de instalar).
2. `deptrac.yaml`:
   ```yaml
   deptrac:
     paths: [./src, ./tests]
     layers:
       - name: Core
         collectors: [{ type: directory, value: src/Core/.* }]
       - name: Infrastructure
         collectors: [{ type: directory, value: src/Infrastructure/.* }]
       - name: Bundle
         collectors: [{ type: directory, value: src/Bundle/.* }]
       - name: Tests
         collectors: [{ type: directory, value: tests/.* }]
       - name: Psr
         collectors: [{ type: classNameRegex, value: '#^Psr\\#' }]
       - name: Symfony
         collectors: [{ type: classNameRegex, value: '#^Symfony\\#' }]
     ruleset:
       Core: [Psr]
       Infrastructure: [Core, Psr, Symfony]
       Bundle: [Core, Infrastructure, Psr, Symfony]
       Tests: [Core, Infrastructure, Bundle, Psr, Symfony]
   ```
   Ajustar si aparecen dependencias legítimas no previstas (por ejemplo, `Twig` en `Bundle`) añadiendo capas explícitas, nunca relajando `Core`.
3. `make deptrac` y job de CI.
4. `ARCHITECTURE.md`: sección "Enforced layers" con la tabla de reglas y el comando. Opcional: `vendor/bin/deptrac analyse --formatter=graphviz-image --output=docs/img/layers.png` si Graphviz está disponible en CI; si no, diagrama Mermaid escrito a mano con las mismas reglas.

**Criterios de aceptación:**
- [ ] 0 violaciones sin tocar el código de producción (si aparecen, abrir PR aparte para corregirlas antes).
- [ ] Prueba negativa documentada en el PR.

---

### B1.7 · CHANGELOG, UPGRADE y política de versiones

**Necesidad:** 44 versiones publicadas, cuatro majors en ~10 semanas y ningún registro de cambios. Es lo primero que mira quien evalúa una dependencia.

**Archivos:** `CHANGELOG.md`, `UPGRADE-4.0.md` (y `UPGRADE-3.0.md`, `UPGRADE-2.0.md` si da tiempo), `CONTRIBUTING.md`.

**Pasos:**
1. `git tag --sort=creatordate` y, para cada major, `git log --oneline vX.0.0^..vX.0.0` y `git diff --stat v(X-1).last..vX.0.0 -- src/`.
2. `CHANGELOG.md` en formato Keep a Changelog, con `## [Unreleased]` arriba. Mínimo: v2.0.0, v3.0.0, v4.0.0, v4.1.0 detallados; versiones 1.x agrupadas en una sección resumida.
3. `UPGRADE-4.0.md`: cada cambio rompedor con "Before" / "After" en código y motivo.
4. `CONTRIBUTING.md` → sección **Versioning policy**:
   - La 4.x es estable: no hay major nuevo sin ADR que lo justifique.
   - Qué es rompedor: firmas públicas de `src/Core/Contract`, claves de configuración, formato YAML, comportamiento documentado.
   - Toda funcionalidad nueva es minor y debe tener consumidor en la demo antes de etiquetarse.
   - Enlace a PLAN.md § 1.4 (copiado, no enlazado, porque PLAN.md no vive en el repo).
5. Release notes en GitHub para v4.0.0 y v4.1.0 copiando la sección del changelog.

**Criterios de aceptación:**
- [ ] `DocumentationLinksTest` verde.
- [ ] Cada major tiene "Breaking changes" y enlace a su UPGRADE si existe.

---

### B1.8 · ADRs iniciales

**Necesidad:** prioridad 2 del proyecto (buenas decisiones de diseño). Las decisiones existen en `ARCHITECTURE.md`, pero no en un formato que un revisor lea en 2 minutos.

**Archivos:** `docs/adr/0000-template.md`, `docs/adr/0001-*.md` … `docs/adr/0006-*.md`, `docs/adr/README.md` (índice), `ARCHITECTURE.md`, `README.md`.

**Plantilla (`0000-template.md`):**
```markdown
# NNNN · Título en inglés
- **Status:** Proposed | Accepted | Superseded by NNNN
- **Date:** YYYY-MM-DD
## Context
## Decision
## Alternatives considered
## Consequences
## References (code, tests, docs)
```

**ADRs (en inglés, 1 página máx. cada uno):**
1. `0001-stateless-actions-and-declarative-yaml.md` — acciones stateless y YAML frente a atributos PHP.
2. `0002-mapper-invariant.md` — solo el mapper toca campos crudos; enlazar tests de mappers.
3. `0003-gateway-acl-outside-the-bundle.md` — el Gateway pertenece a la aplicación.
4. `0004-concurrency-with-lazy-http-responses.md` — respuestas lazy de Symfony HttpClient frente a procesos/fibers; enlazar `SymfonyHttpClientAdapter::sendMany()` y sus tests.
5. `0005-token-cache-per-connection-and-single-retry-on-401.md` — enlazar `DynamicAuthHandler`, `BatchTokenRetry` y tests.
6. `0006-profiler-never-records-secrets.md` — `IntegrationCall` solo guarda el path plantilla.
   - **TDD:** si no existe, crear `tests/Infrastructure/Debug/TracingMiddlewareDoesNotRecordSecretsTest.php`: ejecuta una llamada con cabecera `Authorization: Bearer secret-token`, contexto con valores y body; afirma que ninguna propiedad de `IntegrationCall` (serializada con `var_export`) contiene `secret-token`, los valores del contexto ni el body.
7. `0007-versioning-policy-after-v4.md` — por qué cuatro majors y qué cambia.
8. `0008-no-messenger-bridge-in-the-bundle.md` — criterio "cómo" frente a "cuándo".

(Si el día no da para 8, el mínimo son 0001-0006; 0007 y 0008 pasan al día 10.)

**Criterios de aceptación:**
- [ ] Índice `docs/adr/README.md` enlazado desde `ARCHITECTURE.md` y `README.md`.
- [ ] Test de 0006 verde.
- [ ] `DocumentationLinksTest` verde.

---

### B1.9 · Landing: correcciones de credibilidad

**Necesidad:** la landing contiene un email roto, un snippet que no compila, un ejemplo de Stripe técnicamente incorrecto, un benchmark inventado y claims que no cuadran con el CV.

**Archivos:** `landing/src/html.js`, `landing/src/i18n/en.js`, `landing/src/i18n/es.js`, `landing/src/css.js` (si hay que retirar estilos huérfanos), `landing/package.json` (nuevo), `landing/test/i18n-parity.test.js` (nuevo), `landing/test/content-guards.test.js` (nuevo).

**TDD (Node, sin dependencias, `node --test`):**
1. **Rojo — `i18n-parity.test.js`:** importa `en.js` y `es.js` (ESM) y compara recursivamente el conjunto de claves. Falla si falta alguna en cualquiera de los dos.
2. **Rojo — `content-guards.test.js`:** lee `src/**/*.js` como texto y afirma que **no** contiene:
   - `integration.dev` (sin `engine`)
   - `EngineRequest</span>::<span class="fn">create` ni `EngineRequest::create`
   - `Symfony 7+`
   - `three years`, `tres años`
   - `stripe` (insensible a mayúsculas) — temporal hasta B4.11
   - `0.8s`, `0,8s`, `4.2s`, `4,2s`
   - `Drop us a line`, `Send us an email`, `Escríbenos`, `Envíanos`
   - `SAP, Salesforce`
   Y afirma que **sí** contiene `hi@integrationengine.dev` y `hola@integrationengine.dev`.
3. **Verde:**
   - Email: claves `ctaEmail` / `ctaEmailHref` (líneas ~166-167) en ambos idiomas.
   - `html.js` líneas ~173 y ~663: sustituir por el HTML equivalente a
     `$requests[$key] = new EngineRequest(actionName: GetMovieAction::getName(), context: DefaultActionContext::create($params));`
     (usar ya nombres de TMDB para no rehacerlo en el día 20).
   - `html.js` líneas ~97 y ~117: `Symfony 6.4+`.
   - Eliminar la sección `<!-- STRIPE EXAMPLE -->` y sus claves `stripe*` en ambos idiomas.
   - Eliminar el bloque de benchmark con cifras y sus claves `parallelBeforeTime`, `parallelAfterTime`, `parallelBeforeDetail` (mantener el texto explicativo sin cifras).
   - `heroP` y `thanksP`: "distilled from production integrations in logistics and travel" / "destilado de integraciones en producción en logística y viajes" (sin años).
   - CTA en singular.
   - Meta description (`html.js:41`) sin nombres de empresas.
4. **Email Routing:** activar en Cloudflare para `integrationengine.dev`, reglas `hi@` y `hola@` → `carlos.sgude@gmail.com`. Añadir registros DNS que Cloudflare pida. Enviar correo de prueba desde otra cuenta.
5. `package.json`: `{ "type": "module", "private": true, "scripts": { "test": "node --test" } }`. Añadir job `landing` al CI que ejecute `node --test` en `landing/`.

**Criterios de aceptación:**
- [ ] Ambos tests fallaban antes y pasan después.
- [ ] Correos de prueba recibidos.
- [ ] Captura de la landing en local (`npx wrangler dev`) en EN y ES.

---

### B1.10 · Estado público y release v4.1.1

**Necesidad:** un recruiter debe ver en 30 segundos que el proyecto está vivo y hacia dónde va.

**Archivos:** `README.md`, `ROADMAP.md` (nuevo), `landing/src/html.js`, `landing/src/i18n/*.js`, `landing/src/css.js`, `CHANGELOG.md`.

**Pasos:**
1. **README**, justo bajo el título:
   ```markdown
   > **Status:** actively maintained · v4.1 · stable API since v4.0 · [Changelog](CHANGELOG.md)
   > **Now:** companion live demo · **Next:** form-encoded bodies, declarative resilience, signed inbound webhooks · [Roadmap →](ROADMAP.md)
   ```
2. **`ROADMAP.md`** (inglés), secciones: *Recently shipped* (enlaces al changelog), *Now*, *Next*, *Later*, *Out of scope* (con el porqué: Messenger bridge, CQRS, circuit breaker integrado, generación OpenAPI en el core, OpenTelemetry). Cada elemento: una línea de "why". **Sin fechas.** Fecha de última actualización al final.
3. **Landing:** sección "Roadmap" antes del CTA con tres columnas Now/Next/Later (2-3 elementos cada una) y enlace a `ROADMAP.md`. Claves nuevas en `en.js` y `es.js`.
4. Test `content-guards.test.js`: afirmar que existe la sección (`id="roadmap"`).
5. `CHANGELOG.md`: mover `Unreleased` a `## [4.1.1]` con fecha.
6. Tag `v4.1.1`, release notes, comprobar Packagist, `make deploy-landing`.

**Criterios de aceptación:**
- [ ] `make ci` y `node --test` verdes.
- [ ] v4.1.1 en Packagist.
- [ ] Landing desplegada con sección Roadmap en EN y ES.

---

## FASE 2 · Demo online (parte del bundle)

### B2.1 · Enlaces a la demo en bundle y landing

**Archivos:** `README.md`, `DOCUMENTATION*.md`, `landing/src/html.js`, `landing/src/i18n/*.js`.

**Pasos:**
1. Test rojo en `content-guards.test.js`: la landing contiene `https://demo.integrationengine.dev` y no contiene `integrationEngine-use-example`.
2. Test rojo en `DocumentationLinksTest` (ampliar): ningún `.md` enlaza a `integrationEngine-use-example` salvo una mención explícita "archived".
3. Botón "Live demo" en el hero y en la navegación; enlace "Demo source" a GitHub.
4. README: sustituir enlaces al repo antiguo por `https://github.com/CarlosGude/integrationEngine-demo` y `https://demo.integrationengine.dev`.
5. `ROADMAP.md`: mover "companion live demo" a *Recently shipped*.

**Criterios de aceptación:** tests verdes; landing desplegada.

---

### B2.2 · Job de contrato bundle ↔ demo

**Necesidad:** la demo es el primer consumidor del bundle. Un cambio rompedor debe detectarse en el PR del bundle, no tras publicar.

**Archivo:** `.github/workflows/contract.yml` (nuevo).

**Pasos:**
```yaml
name: Demo contract
on: [pull_request, push]
jobs:
  demo:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { path: bundle }
      - uses: actions/checkout@v4
        with: { repository: CarlosGude/integrationEngine-demo, path: demo }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.4', coverage: none, extensions: pdo_sqlite, amqp }
      - name: Use bundle from this commit
        working-directory: demo
        run: |
          composer config repositories.bundle '{"type":"path","url":"../bundle","options":{"symlink":false}}'
          composer require carlosgude/integration-engine:@dev --no-update
          composer update carlosgude/integration-engine --with-all-dependencies --no-progress
      - name: Demo tests (no external services)
        working-directory: demo
        run: vendor/bin/phpunit --exclude-group integration
      - name: Demo static analysis
        working-directory: demo
        run: vendor/bin/phpstan analyse --memory-limit=1G
```
- La modificación de `composer.json` ocurre solo en el runner; nunca se commitea en la demo.
- Si la demo necesita variables de entorno para arrancar el kernel en test, deben tener valores falsos en `.env.test` de la demo.

**Criterios de aceptación:**
- [ ] Job verde.
- [ ] Prueba negativa documentada (rama temporal que rompe un método usado por la demo).
- [ ] `CONTRIBUTING.md`: "A red *Demo contract* job means a breaking change: justify it with an ADR or redesign."

---

## FASE 3 · Integraciones robustas

### B3.1 · Form-encoded: contrato y `Request`

**Necesidad:** OAuth2 token endpoints y la API de Stripe exigen `application/x-www-form-urlencoded`. Hoy el adaptador REST solo envía JSON (`$options['json']`).

**Archivos:**
- `src/Core/Contract/Action/FormEncodedBodyInterface.php` (nuevo)
- `src/Core/Contract/Client/BodyEncoding.php` (nuevo, enum)
- `src/Core/Contract/Client/Request.php`
- `tests/Core/RequestTest.php` (nuevo), `tests/Fake/FakeFormBody.php` (nuevo)

**Diseño:**
```php
namespace IntegrationEngine\Core\Contract\Action;

/** Marker: the adapter must send toArray() as application/x-www-form-urlencoded. */
interface FormEncodedBodyInterface extends ActionBodyInterface {}
```
```php
namespace IntegrationEngine\Core\Contract\Client;

enum BodyEncoding: string
{
    case Json = 'json';
    case Form = 'form';
}
```
`Request` gana un quinto parámetro **opcional al final** (retrocompatible):
```php
public function __construct(
    public string $method,
    public string $url,
    public array $headers,
    public ?array $body = null,
    public BodyEncoding $bodyEncoding = BodyEncoding::Json,
) {}
```
`withHeader()` debe propagar `bodyEncoding`.

**TDD:**
1. **Rojo:**
   - `RequestTest::testDefaultsToJsonEncoding()`
   - `RequestTest::testWithHeaderPreservesBodyAndEncoding()`
   - `RequestTest::testPositionalConstructionWithoutEncodingStillWorks()` (4 argumentos posicionales).
   - `FakeFormBody implements FormEncodedBodyInterface` en `tests/Fake`.
2. **Verde:** implementar.
3. **Mutación:** asegurar que el mutante que elimina la propagación en `withHeader()` muere.

**Criterios de aceptación:**
- [ ] Tests verdes; ningún test existente modificado.
- [ ] Deptrac verde (todo en `Core`).

---

### B3.2 · Form-encoded: adaptador REST

**Archivos:** `src/Infrastructure/Http/SymfonyHttpClientAdapter.php`, `tests/Infrastructure/SymfonyHttpClientAdapterFormBodyTest.php` (nuevo).

**Cambios:**
- `buildOptions()`: si `$body instanceof FormEncodedBodyInterface` y método POST/PUT/PATCH → `$options['body'] = $body->toArray()` (Symfony HttpClient codifica arrays con `http_build_query` y pone el `Content-Type`); si no, `json` como hoy. Tipo de retorno del docblock actualizado: `array{headers: array<string,string>, json?: array<string,mixed>, body?: array<string,mixed>}`.
- `send()`: construir `Request` con `$options['json'] ?? $options['body'] ?? null` y `BodyEncoding::Form` cuando corresponda.
- `execute()`: según `$request->bodyEncoding`, usar `json` o `body`.
- `sendMany()`: ya usa `buildOptions()`; verificar con test.

**TDD (con `Symfony\Component\HttpClient\MockHttpClient` y `MockResponse`):**
1. `testSendEncodesFormBodyAsUrlEncoded()` — captura la petición en el callback de `MockHttpClient`; afirma `Content-Type: application/x-www-form-urlencoded` y cuerpo `amount=2000&currency=eur`.
2. `testSendEncodesNestedArraysWithBrackets()` — `['metadata' => ['movie_id' => '550']]` → `metadata%5Bmovie_id%5D=550`.
3. `testSendManyEncodesFormBodies()` — batch mixto JSON + form; cada petición con su codificación.
4. `testRequestMiddlewareReceivesFormEncoding()` — un `FakeRequestMiddleware` observa `$request->bodyEncoding === BodyEncoding::Form` y el body array.
5. `testGetWithFormBodyDoesNotSendBody()` — mismo comportamiento que JSON hoy.
6. `testJsonBodiesAreUnchanged()` — regresión.

**Criterios de aceptación:**
- [ ] Tests verdes; tests existentes de `SymfonyHttpClientAdapterBodyTest` sin cambios.
- [ ] MSI del archivo sin mutantes escapados en las ramas nuevas.

---

### B3.3 · Form-encoded: GraphQL, documentación y release v4.2.0

**Archivos:** `src/Infrastructure/Http/GraphQLClientAdapter.php`, `tests/Infrastructure/GraphQLClientAdapterBodyTest.php`, `docs/actions.md`, `docs/clients.md`, `DOCUMENTATION*.md`, `docs/adr/0009-form-encoded-bodies.md`, `CHANGELOG.md`, `ROADMAP.md`.

**TDD:**
1. `GraphQLClientAdapterBodyTest::testFormEncodedBodyIsRejectedWithExplicitMessage()` — hoy ya lanza porque no es `GraphQLBodyInterface`; afirmar que el mensaje menciona que GraphQL requiere `GraphQLBodyInterface` (evitar regresión).
2. Documentación con ejemplo completo (body form + YAML + test con `FakeClient`).
3. ADR 0009: por qué una interfaz marcador y no una opción YAML `encoding: form` (el body sabe cómo se serializa; el YAML no debería conocer la forma del payload).

**Release:** DoD PLAN.md § 1.4; la demo (D3.3) ya consume la funcionalidad desde el commit; tras el tag, demo a `^4.2`.

---

### B3.4 · Resiliencia: configuración

**Necesidad:** hoy solo se reintenta tras 401. No hay timeouts ni reintentos ante 5xx, 429 o red.

**Archivos:** `src/Bundle/DependencyInjection/Configuration.php`, `tests/Bundle/DependencyInjection/ConfigurationTest.php`.

**Configuración nueva por integración:**
```yaml
integration_engine:
  integrations:
    supplier:
      base_url: '%env(SUPPLIER_URL)%'
      config_path: '...'
      timeout: 2.0            # float, segundos, opcional (inactividad)
      max_duration: 10.0      # float, segundos, opcional (total)
      retry:                  # opcional; ausente = sin reintentos
        max_retries: 3        # int >= 1
        delay_ms: 200         # int >= 0
        multiplier: 2.0       # float >= 1
        max_delay_ms: 2000    # int >= 0, 0 = sin tope
        jitter: 0.1           # float 0..1
        status_codes: [423, 425, 429, 500, 502, 503, 504, 507, 510]
        retry_non_idempotent: false
```

**TDD:**
1. `testTimeoutsAreOptionalAndNullByDefault()`
2. `testRetryIsAbsentByDefault()`
3. `testRetryDefaultsWhenEnabled()` (`retry: ~` → valores por defecto de arriba)
4. `testRejectsNegativeTimeout()`, `testRejectsMultiplierBelowOne()`, `testRejectsJitterOutOfRange()`, `testRejectsStatusCodeOutOfRange()`, `testRejectsZeroMaxRetries()`
5. `testRetryIsIncompatibleWithClientService()` — con `client_service` propio, `retry`/`timeout` → excepción de configuración clara (el engine no controla ese cliente).

**Criterios de aceptación:** tests verdes; `IntegrationCompilerPass` todavía no usa los valores (B3.5).

---

### B3.5 · Resiliencia: cableado en el contenedor

**Archivos:** `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php` (`resolveHttpClientRef()`), `src/Infrastructure/Http/RetryStrategyFactory.php` (nuevo), `tests/Bundle/DependencyInjection/IntegrationCompilerPassTest.php`, `tests/Infrastructure/RetryStrategyFactoryTest.php` (nuevo).

**Diseño:**
- En `resolveHttpClientRef()`, el primer argumento deja de ser siempre `new Reference('http_client')`:
  1. `integration_engine.transport.{name}.base` = `http_client`, y si hay `timeout`/`max_duration` → definición con factory `[Reference('http_client'), 'withOptions']` y argumento `['timeout' => ..., 'max_duration' => ...]`.
  2. Si hay `retry` → `integration_engine.transport.{name}` = `Symfony\Component\HttpClient\RetryableHttpClient` con argumentos `(base, strategy, max_retries, logger?)`.
  3. `strategy` = servicio creado por `RetryStrategyFactory::create(array $retryConfig): GenericRetryStrategy`.
- **Idempotencia:** `GenericRetryStrategy` acepta `status_codes` como mapa código → métodos. La factory construye:
  - por defecto: cada código → `['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'TRACE']`;
  - con `retry_non_idempotent: true`: cada código → todos los métodos.
  - Verificar en el código fuente de `GenericRetryStrategy` (versiones 6.4, 7.4, 8.x) que el formato de mapa código→métodos y el tratamiento de errores de transporte son los esperados; anotar en el PR con enlace a la línea.
- Sin `retry` ni timeouts: definición idéntica a la actual (regresión cero).

**TDD:**
1. `RetryStrategyFactoryTest`: mapa por defecto solo idempotentes; con opt-in todos; `delay_ms`, `multiplier`, `max_delay_ms`, `jitter` pasados correctamente (inspección vía reflexión o comportamiento con `MockResponse`).
2. `IntegrationCompilerPassTest`:
   - `testNoRetryNoTimeoutKeepsPlainHttpClient()`
   - `testTimeoutsDecorateHttpClientWithOptions()`
   - `testRetryWrapsTransportInRetryableHttpClient()`
   - `testClientServiceIsNeverDecorated()`

**Criterios de aceptación:** tests verdes; toda la suite existente sin cambios; Deptrac verde (`RetryStrategyFactory` en `Infrastructure`).

---

### B3.6 · Resiliencia: comportamiento

**Archivos:** `tests/Infrastructure/ResilienceBehaviourTest.php` (nuevo); `src/Infrastructure/Http/RetryAfterAwareStrategy.php` **solo si** la verificación demuestra que `GenericRetryStrategy`/`RetryableHttpClient` no respetan `Retry-After`.

**TDD (construyendo `SymfonyHttpClientAdapter` con `RetryableHttpClient(MockHttpClient)` y la strategy de la factory):**
1. `testGetIsRetriedOnTransient503AndSucceeds()` — respuestas 503, 503, 200 → éxito; `MockHttpClient::getRequestsCount() === 3`.
2. `testPostIsNotRetriedByDefault()` — 503 → `RequestResponseException` con `statusCode 503`; 1 petición.
3. `testPostIsRetriedWhenNonIdempotentRetryEnabled()` — 3 peticiones.
4. `testRetryAfterHeaderIsHonoured()` — 429 con `Retry-After: 2` y luego 200; comprobar el retardo solicitado (vía `delay_ms` observado en el logger/strategy o midiendo con un reloj falso; **no usar `sleep` real en tests**). Si `RetryableHttpClient` ya lo respeta, el test documenta el comportamiento; si no, implementar `RetryAfterAwareStrategy` (decorador de `RetryStrategyInterface::getDelay()`).
5. `testGivesUpAfterMaxRetries()` — 4 × 503 con `max_retries: 3` → excepción; 4 peticiones.
6. `testTimeoutBecomesNetworkRequestResponseException()` — `MockResponse` con `timeout` o callback que lanza `TransportException` → `RequestResponseException` con `statusCode 0`.
7. `testNon2xxOutsideConfiguredCodesIsNotRetried()` — 400 → 1 petición.

**Criterios de aceptación:** tests verdes, sin `sleep` real; tiempo total de la clase < 1 s.

---

### B3.7 · Resiliencia: batch, timeout por acción, documentación y release v4.3.0

**Archivos:** `src/Core/Contract/Action/AbstractAction.php`, `src/Infrastructure/Adapter/YamlConfigAdapter.php`, `src/Infrastructure/Http/SymfonyHttpClientAdapter.php`, `tests/Infrastructure/ResilienceBatchTest.php`, `tests/Core/AbstractActionTest.php`, `tests/Infrastructure/YamlConfigAdapterTest.php`, `docs/resilience.md` (nuevo), `docs/adr/0010-declarative-resilience-reusing-symfony-retry.md`, `CHANGELOG.md`, `ROADMAP.md`.

**Alcance mínimo:**
1. **Batch:** `ResilienceBatchTest::testRetriesDoNotSerialiseTheBatch()` — `MockHttpClient` con callback que registra el orden de "request started"; 5 peticiones donde una recibe 503, 503, 200. Afirmar que las 5 se inician antes de que se consuma la primera respuesta y que el resultado es correcto.
2. **Timeout por acción:**
   - `AbstractAction::create(..., ?float $timeout = null)` como último parámetro opcional y `getTimeout(): ?float`.
   - `YamlConfigAdapter` lee `timeout` de la entrada de acción.
   - `SymfonyHttpClientAdapter::buildOptions()` añade `timeout` si la acción lo define (tiene prioridad sobre el de la integración).
   - Tests: YAML con y sin `timeout`; opción presente en la petición capturada por `MockHttpClient`.
3. `docs/resilience.md`: configuración, tabla de métodos idempotentes, `Retry-After`, `Idempotency-Key` como forma segura de reintentar POST (con ejemplo de `RequestHeadersInterface`), interacción con batch y con `client_service`.
4. ADR 0010.

**Release:** DoD PLAN.md § 1.4, tras D3.2 verde con el commit del bundle.

---

## FASE 4 · Webhooks entrantes

> Principio: **todo lo que va hasta el `RemoteEvent` es del bundle; lo que ocurre después (cola, consumidor, persistencia) es de la aplicación.**

### B4.1 · Spike y ADRs

**Necesidad:** no escribir código sobre suposiciones del componente Webhook de Symfony.

**Archivos:** `docs/spikes/webhooks.md`, `docs/adr/0011-inbound-webhooks-on-symfony-webhook.md`, `docs/adr/0012-webhook-idempotency-exposed-not-enforced.md`.

**Preguntas que el spike debe responder con enlace a código fuente (tag exacto de 6.4, 7.4 y 8.x):**
1. ¿`symfony/webhook` y `symfony/remote-event` están marcados como experimentales en 6.4? ¿Y en 7.4?
2. Firma de `AbstractRequestParser::parse()`, `doParse()`, `getRequestMatcher()`, `createSuccessfulResponse()`, `createRejectedResponse()`.
3. ¿Qué hace `WebhookController` si `parse()` devuelve `null`? ¿Y si lanza `RejectWebhookException`? ¿Qué código HTTP devuelve en cada caso?
4. ¿Cómo se configura `framework.webhook.routing.<type>` (clave `service`, `secret`)? ¿Cómo se importan las rutas (`webhook.xml` / `webhook.php`)?
5. ¿`WebhookController` despacha `ConsumeRemoteEventMessage` al bus siempre? ¿Qué pasa si no hay Messenger?
6. ¿`RemoteEvent` es extensible (no `final`)? ¿Su payload debe ser `array`?
7. ¿Cómo recibe el parser el cuerpo crudo? (`Request::getContent()` antes de cualquier decodificación).

**Decisiones a registrar:**
- **ADR 0011:** construir sobre Symfony Webhook; parser genérico configurado por YAML; dependencias en `suggest`; versión mínima de Symfony para esta funcionalidad (6.4 o 7.4 según el spike) sin cambiar el mínimo del bundle.
- **ADR 0012:** **opción A** — el bundle expone el id del evento (`id_field`) en el `RemoteEvent` y **no** deduplica (el bundle sigue sin estado); la deduplicación es de la aplicación. Alternativa descartada: deduplicación en caché dentro del bundle (explicar por qué: TTL arbitrario, caché compartida entre workers, falsa sensación de exactamente-una-vez).

**Criterios de aceptación:** las 7 preguntas respondidas con enlaces; si alguna respuesta contradice este plan, **parar** y proponer el ajuste antes del día 47.

---

### B4.2 · Definición de webhooks en YAML

**Archivos:**
- `src/Core/Contract/Webhook/WebhookDefinition.php` (nuevo)
- `src/Core/Contract/Webhook/SignatureConfig.php` (nuevo)
- `src/Core/Contract/Webhook/SignatureType.php` (nuevo, enum `HmacSha256` / `TimestampedHmac`)
- `src/Core/Port/ConfigPort.php` (nuevo método)
- `src/Infrastructure/Adapter/YamlConfigAdapter.php`
- `tests/Infrastructure/YamlConfigAdapterWebhooksTest.php` (nuevo), `tests/Fake/FakeConfigPort.php`

**Formato YAML (en el mismo `{Name}.yaml` que las acciones, bajo la clave reservada `webhooks`):**
```yaml
webhooks:
  type_field: type          # ruta con puntos al tipo de evento en el payload
  id_field: id              # ruta con puntos al id del evento
  signature:
    type: timestamped_hmac  # hmac_sha256 | timestamped_hmac
    header: Stripe-Signature
    secret: '%env(STRIPE_WEBHOOK_SECRET)%'
    tolerance: 300          # solo timestamped_hmac
    prefix: ~               # solo hmac_sha256 (ej. 'sha256=')
  unknown_events: ignore    # ignore | reject
  events:
    payment_intent.succeeded:
      mapper: App\...\PaymentIntentSucceededMapper
    payment_intent.payment_failed:
      mapper: App\...\PaymentIntentFailedMapper
```

**Nota:** la resolución de `%env()%` ocurre en el contenedor; `YamlConfigAdapter` ya trabaja con valores resueltos o parámetros. Verificar cómo se resuelven hoy los `%env()%` de `authorization.token` y seguir el mismo camino.

**Diseño:**
- `ConfigPort::getWebhookDefinition(): ?WebhookDefinition` (null si la integración no declara webhooks).
- `WebhookDefinition` inmutable: `typeField`, `idField`, `SignatureConfig`, `UnknownEventPolicy`, `array<string, class-string<AbstractWebhookMapper>> $mappers`, método `mapperFor(string $eventType): ?string`.
- La clave `webhooks` no debe interpretarse como acción (hoy el adaptador itera todas las claves como acciones: añadir exclusión y test).

**TDD:**
1. `testParsesValidWebhookDefinition()`
2. `testReturnsNullWhenNoWebhooksSection()`
3. `testWebhooksKeyIsNotTreatedAsAnAction()`
4. `testRejectsUnknownSignatureType()`
5. `testRejectsMissingMapperClass()`
6. `testRejectsMapperNotExtendingAbstractWebhookMapper()` (se completa en B4.5; hasta entonces, usar clase de `tests/Fake` y marcar la aserción como parte de B4.5 si no existe la clase base — sin `markTestSkipped`: el test se añade en B4.5)
7. `testTimestampedHmacRequiresTolerance()` / `testHmacSha256RejectsTolerance()`
8. `testDotPathFieldsAreValidated()` (vacío → error)

---

### B4.3 · Verificador `hmac_sha256`

**Archivos:** `src/Core/Contract/Webhook/SignatureVerifierInterface.php`, `src/Core/Webhook/HmacSha256SignatureVerifier.php`, `src/Core/Webhook/SignatureVerificationResult.php` (o excepción tipada), `src/Core/Exception/WebhookRejectionReason.php` (enum, se amplía en B4.8), `tests/Core/Webhook/HmacSha256SignatureVerifierTest.php`.

**Contrato:**
```php
interface SignatureVerifierInterface
{
    /** @param array<string, list<string>> $headers normalised lower-case names */
    public function verify(string $rawBody, array $headers, SignatureConfig $config): void; // throws WebhookSignatureException
}
```
`WebhookSignatureException` lleva un `WebhookRejectionReason` (`header_missing`, `header_malformed`, `signature_invalid`) y **nunca** incluye el secreto ni la firma esperada en el mensaje.

**TDD:**
1. `testAcceptsValidSignature()` — firma calculada en el test con `hash_hmac('sha256', $body, $secret)`.
2. `testAcceptsValidSignatureWithPrefix()` — `sha256=<hex>`.
3. `testRejectsInvalidSignature()` → `signature_invalid`.
4. `testRejectsMissingHeader()` → `header_missing`.
5. `testRejectsWrongPrefix()` → `header_malformed`.
6. `testRejectsTamperedBody()`.
7. `testHeaderLookupIsCaseInsensitive()`.
8. `testExceptionMessageNeverContainsSecretOrExpectedSignature()`.

**Mutación:** el mutante que sustituye `hash_equals` por `true` o invierte la condición debe morir.

---

### B4.4 · Verificador `timestamped_hmac` (esquema de Stripe)

**Archivos:** `src/Core/Webhook/TimestampedHmacSignatureVerifier.php`, `composer.json` (`psr/clock: ^1.0` en `require`), `tests/Core/Webhook/TimestampedHmacSignatureVerifierTest.php`, `tests/Fake/FakeClock.php`.

**Especificación (verificar contra la documentación de Stripe sobre verificación manual de firmas antes de implementar y enlazarla en el PR):**
- Cabecera: `t=<unix>,v1=<hex>[,v1=<hex>…][,v0=<hex>]`.
- Payload firmado: `"{t}.{rawBody}"`, HMAC-SHA256 con el secreto.
- Válido si **cualquier** `v1` coincide (rotación de secreto). `v0` se ignora.
- `|now - t| <= tolerance`; si no → `timestamp_out_of_tolerance`.
- El reloj se inyecta (`Psr\Clock\ClockInterface`); en producción, un reloj del sistema en `Infrastructure`.

**TDD:**
1. `testAcceptsValidSignature()`
2. `testAcceptsWhenOneOfSeveralV1Matches()`
3. `testRejectsWhenNoV1Matches()` → `signature_invalid`
4. `testIgnoresV0Signatures()` (solo `v0` válida → rechazo)
5. `testRejectsTimestampTooOld()` / `testRejectsTimestampInTheFuture()` → `timestamp_out_of_tolerance`
6. `testAcceptsExactlyAtToleranceBoundary()` (mutantes `<=` → `<`)
7. `testRejectsMissingTimestamp()` / `testRejectsNonNumericTimestamp()` → `header_malformed`
8. `testRejectsTamperedBody()`
9. `testSignedPayloadUsesRawBodyNotReEncodedJson()` — body con espacios y orden de claves no canónico.

**Deptrac:** `psr/clock` pertenece a la capa `Psr` (permitida en `Core`).

---

### B4.5 · Mapper de webhooks y evento tipado

**Archivos:** `src/Core/Contract/Webhook/AbstractWebhookMapper.php`, `src/Core/Contract/Webhook/WebhookEventInterface.php`, `src/Core/Exception/WebhookMapperMismatchException.php`, `tests/Core/Webhook/AbstractWebhookMapperTest.php`, `tests/Fake/FakeWebhookMapper.php`, `tests/Fake/FakeWebhookEvent.php`.

**Diseño (simétrico a `AbstractMapper`):**
```php
abstract class AbstractWebhookMapper
{
    /** @return non-empty-string event type this mapper handles, e.g. "payment_intent.succeeded" */
    abstract public static function eventType(): string;

    /**
     * @param array<mixed>                 $payload decoded JSON
     * @param array<string, list<string>>  $headers
     */
    final public static function map(string $eventType, array $payload, array $headers): WebhookEventInterface
    {
        if ($eventType !== static::eventType()) { throw WebhookMapperMismatchException::for(static::class, $eventType); }
        return static::transform($payload, $headers);
    }

    /** @param array<mixed> $payload @param array<string, list<string>> $headers */
    abstract protected static function transform(array $payload, array $headers): WebhookEventInterface;
}
```
`WebhookEventInterface` es un marcador; las implementaciones deben ser `final readonly` y serializables (solo escalares, arrays y otros objetos readonly).

**TDD:**
1. `testMapsPayloadToTypedEvent()`
2. `testThrowsOnEventTypeMismatch()`
3. `testMappedEventSurvivesSerializeRoundTrip()` (`unserialize(serialize($event)) == $event`)
4. Completar `YamlConfigAdapterWebhooksTest::testRejectsMapperNotExtendingAbstractWebhookMapper()`.
5. `testDefinitionEventTypeMustMatchMapperEventType()` en `YamlConfigAdapterWebhooksTest` (clave YAML ≠ `eventType()` → error).

---

### B4.6 · Request parser genérico

**Archivos:** `src/Infrastructure/Webhook/IntegrationWebhookRequestParser.php`, `src/Infrastructure/Webhook/MappedRemoteEvent.php`, `src/Infrastructure/Webhook/DotPath.php`, `src/Infrastructure/Clock/SystemClock.php`, `tests/Infrastructure/Webhook/IntegrationWebhookRequestParserTest.php`, `tests/Infrastructure/Webhook/DotPathTest.php`, `composer.json` (`symfony/webhook` y `symfony/remote-event` en `require-dev` y `suggest`).

**Diseño (ajustar a lo que confirme B4.1):**
- `IntegrationWebhookRequestParser extends AbstractRequestParser`
  - constructor: `WebhookDefinition`, `SignatureVerifierInterface` (elegido por `SignatureType`), nombre de integración.
  - `getRequestMatcher()`: `ChainRequestMatcher([new MethodRequestMatcher('POST'), new IsJsonRequestMatcher()])`.
  - `doParse(Request $request, string $secret)`:
    1. `$raw = $request->getContent()`.
    2. Verificar firma con `$raw` y cabeceras (secreto: el de `SignatureConfig`; si Symfony pasa `$secret` desde `framework.webhook.routing`, **documentar cuál manda** según el spike y testearlo).
    3. `json_decode($raw, true, flags: JSON_THROW_ON_ERROR)` → error → `payload_invalid`.
    4. Tipo y id vía `DotPath::get($payload, $definition->typeField)`; ausentes → `payload_invalid`.
    5. Sin mapper para el tipo: `unknown_events: ignore` → devolver lo que el spike determine como "aceptado sin evento"; `reject` → `unknown_event`.
    6. `MappedRemoteEvent(name: $type, id: $id, payload: $payload, event: $mapped)`.
- `MappedRemoteEvent extends RemoteEvent` con `public function event(): WebhookEventInterface`.

**TDD:**
1. `DotPathTest`: `id`, `data.object.id`, clave inexistente, valor no escalar.
2. Parser:
   - `testParsesValidStripeLikeRequest()` — firma válida, `MappedRemoteEvent` con nombre, id, evento tipado.
   - `testRejectsNonPostRequest()`, `testRejectsNonJsonContentType()`.
   - `testRejectsInvalidJsonAfterValidSignature()`.
   - `testRejectsMissingTypeOrId()`.
   - `testIgnoresUnknownEventWhenPolicyIsIgnore()` / `testRejectsUnknownEventWhenPolicyIsReject()`.
   - `testSignatureIsVerifiedBeforeJsonDecoding()` — body no JSON con firma inválida → `signature_invalid` (no `payload_invalid`).
   - `testRemoteEventPayloadIsRawDecodedPayload()`.

---

### B4.7 · Cableado en el contenedor

**Archivos:** `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php`, `src/Bundle/Exception/IntegrationConfigurationException.php`, `tests/Bundle/DependencyInjection/IntegrationCompilerPassWebhooksTest.php`.

**Diseño:**
- Para cada integración cuyo `ConfigPort::getWebhookDefinition()` no sea `null`:
  - si `!class_exists(AbstractRequestParser::class)` → `IntegrationConfigurationException::webhooksRequireSymfonyWebhook($name)` con mensaje que indica `composer require symfony/webhook symfony/remote-event`;
  - registrar `integration_engine.webhook_parser.{name}` (`IntegrationWebhookRequestParser`), con el verificador adecuado y `SystemClock`.
- **No** tocar `framework.webhook.routing`: el usuario lo configura explícitamente (documentado en B4.10). Alcance mínimo, explícito y sin magia.

**TDD:**
1. `testRegistersParserForIntegrationWithWebhooks()`
2. `testDoesNotRegisterParserWithoutWebhooks()`
3. `testUsesTimestampedVerifierForTimestampedHmac()` / `testUsesHmacVerifierForHmacSha256()`
4. `testThrowsClearErrorWhenSymfonyWebhookIsMissing()` — simular ausencia inyectando un "class existence checker" en el compiler pass (no manipular el autoloader).

**Deptrac:** `Infrastructure` puede depender de `Symfony`; `Core` sigue sin hacerlo.

---

### B4.8 · Rechazos con códigos de motivo

**Archivos:** `src/Core/Exception/WebhookRejectionReason.php`, `src/Infrastructure/Webhook/IntegrationWebhookRequestParser.php`, `tests/Infrastructure/Webhook/WebhookRejectionTest.php`.

**Enum completo:** `header_missing`, `header_malformed`, `signature_invalid`, `timestamp_out_of_tolerance`, `payload_invalid`, `unknown_event`.

**Diseño:**
- El parser traduce toda excepción del core a `RejectWebhookException` de Symfony con código HTTP según B4.1 y mensaje `"Webhook rejected: {reason}"`.
- Exponer el motivo de forma estructurada para la aplicación: `WebhookRejectedException` (propia, `Infrastructure`) con `reason(): WebhookRejectionReason`, lanzada **dentro** y convertida a `RejectWebhookException` en el límite. Si `RejectWebhookException` permite `previous`, encadenar la propia para que un decorador (demo D4.5) pueda leer el motivo.

**TDD:**
1. `#[DataProvider]` con un caso por motivo → `RejectWebhookException` con el motivo en el mensaje y `getPrevious()` tipado.
2. `testRejectionMessagesContainNoSecretsOrSignatures()` — recorre todos los casos y comprueba que el mensaje y el previo no contienen el secreto, la firma recibida ni la calculada.

---

### B4.9 · Generador `make:integration --webhook`

**Archivos:** `src/Bundle/Command/MakeIntegrationCommand.php`, `src/Bundle/Generator/IntegrationFileGenerator.php`, `src/Bundle/Generator/TemplateRenderer.php`, `src/Bundle/Generator/IntegrationContext.php`, `tests/Bundle/Command/MakeIntegrationCommandTest.php`, `tests/Bundle/Generator/*`.

**Comportamiento:**
```bash
php bin/console make:integration Stripe PaymentIntentSucceeded --webhook --event-type=payment_intent.succeeded
```
Genera, sin sobrescribir archivos existentes (salvo `--force`):
```
Stripe/
├─ Stripe.yaml                          ← añade/actualiza sección webhooks.events
└─ Webhook/PaymentIntentSucceeded/
   ├─ PaymentIntentSucceededEvent.php   ← final readonly, implements WebhookEventInterface
   └─ PaymentIntentSucceededMapper.php  ← extends AbstractWebhookMapper
```
Si `Stripe.yaml` no tiene sección `webhooks`, la crea con `signature` de ejemplo comentada y `unknown_events: ignore`.

**TDD:**
1. Generador: archivos esperados, namespace, `eventType()` correcto, YAML válido tras añadir dos eventos seguidos, no sobrescribe.
2. Comando: `--webhook` sin `--event-type` → error de validación; modo no interactivo.
3. Job `generator-php82` (B1.3): añadir generación de webhook y `php -l`.

---

### B4.10 · Documentación, seguridad y release v4.4.0

**Archivos:** `docs/webhooks.md` (nuevo), `ARCHITECTURE.md` (sección "Security model"), `README.md`, `DOCUMENTATION*.md`, `deptrac.yaml`, `CHANGELOG.md`, `ROADMAP.md`, `agent/integration-engine-agent-guide.md`.

**Contenido mínimo de `docs/webhooks.md`:**
1. Instalación (`symfony/webhook`, `symfony/remote-event`, Messenger recomendado).
2. YAML completo con los dos esquemas.
3. Configuración de Symfony:
   ```yaml
   # config/packages/webhook.yaml
   framework:
     webhook:
       routing:
         stripe:
           service: integration_engine.webhook_parser.stripe
           secret: '%env(STRIPE_WEBHOOK_SECRET)%'   # ajustar según B4.1
   ```
   y la importación de rutas según versión.
4. Consumidor de ejemplo con `#[AsRemoteEventConsumer('stripe')]` y `MappedRemoteEvent::event()`.
5. **Idempotencia:** entrega "al menos una vez"; ejemplo de deduplicación en la aplicación (ADR 0012).
6. Tabla de motivos de rechazo.
7. Rotación de secretos (varias `v1`) y tolerancia.
8. Testing: helper para firmar payloads en tests (`tests/Fake` → valorar publicarlo como `IntegrationEngine\Testing\WebhookSigner` en `src/Testing/` con capa Deptrac propia).

**Security model** (`ARCHITECTURE.md`): qué datos toca el engine, qué nunca registra (ADR 0006), dónde se cachean tokens (ADR 0005), cómo se verifican webhooks (orden: firma → JSON → mapeo) y qué no garantiza (exactamente-una-vez).

**Release:** DoD PLAN.md § 1.4, tras D4.5-D4.7 verdes con el commit del bundle.

---

### B4.11 · Landing: "Both directions, one pattern"

**Archivos:** `landing/src/html.js`, `landing/src/i18n/{en,es}.js`, `landing/src/css.js`, `landing/test/snippets-match.test.js` (nuevo), `landing/test/content-guards.test.js`.

**Pasos:**
1. **Rojo — `snippets-match.test.js`:** lee de un directorio `landing/snippets/` (nuevo) los fragmentos YAML/PHP que se muestran y comprueba que están **contenidos literalmente** en los archivos de la demo. Para no depender del otro repo en el test local, el CI del job `landing` hace checkout de `integrationEngine-demo` en `../demo`; en local, el test se salta **con mensaje explícito** solo si `../demo` no existe (y el CI falla si no existe).
2. Quitar `stripe` de la lista prohibida de `content-guards.test.js` y añadir aserción de que la sección `id="both-directions"` existe.
3. Sección con:
   - YAML de salida `CreatePaymentIntent` y de entrada `webhooks.events.payment_intent.succeeded`, lado a lado.
   - Diagrama (SVG inline o HTML/CSS) `Stripe → bundle (verify · map · RemoteEvent) │ app (Messenger → RabbitMQ → consumer)` con la frontera marcada.
   - Enlace al paso 6 del tour.
4. Desplegar.

---

## FASE 5 · Calidad de diseño visible

### B5.1 · Extensión PHPStan: spike de inferencia

**Pregunta:** ¿puede una `DynamicMethodReturnTypeExtension` inferir el tipo de retorno de `IntegrationEngine::send()` cuando el primer argumento es `SomeAction::getName()`?

**Cadena a resolver en el spike:**
1. Argumento `StaticCall` → clase `SomeAction` → método estático `mapper()` → devuelve `class-string<SomeMapper>` (¿es un literal constante que PHPStan puede leer vía reflexión o AST?).
2. `SomeMapper` → tipo de retorno de `transform()` (hoy `ResponseInterface`; ¿los mappers concretos declaran un tipo más específico?).
3. Si no es inferible con fiabilidad: alternativa de genéricos (`@template` en `AbstractAction`/`AbstractMapper`) y su coste de migración.

**Salida:** ADR `00NN-phpstan-extension.md` con decisión **go** (inferencia) o **no-go** (solo reglas), ejemplos de lo que funciona y lo que no. Rama `spike/*` no se fusiona.

---

### B5.2 · Regla: el mapper apunta a una acción válida

**Archivos:** `src/PHPStan/Rules/MapperActionRule.php`, `tests/PHPStan/Rules/MapperActionRuleTest.php`, `tests/PHPStan/Rules/data/mapper-action-*.php`, `deptrac.yaml` (capa `PHPStan`: puede depender de `Core` y `PHPStan\*`).

**Regla:** en clases que extienden `AbstractMapper`, el método `getAction()` (o el equivalente real en el código actual: verificarlo) debe devolver `X::class` donde `X` extiende `AbstractAction`, y `X::mapper()` debe devolver la clase del propio mapper.

**TDD (`PHPStan\Testing\RuleTestCase`):**
1. Fichero válido → sin errores.
2. `getAction()` devuelve una clase que no extiende `AbstractAction` → error con mensaje y línea.
3. La acción apunta a otro mapper → error.
4. `getAction()` devuelve algo no constante → error "must return a class constant".

---

### B5.3 · Regla: el facade no devuelve arrays sin tipar

**Archivos:** `src/PHPStan/Rules/IntegrationFacadeReturnTypeRule.php`, `tests/PHPStan/Rules/IntegrationFacadeReturnTypeRuleTest.php`, `tests/PHPStan/Rules/data/facade-*.php`.

**Regla:** en clases que implementan `IntegrationName`, cada método público debe declarar un tipo de retorno que no sea `array`, `mixed` ni `iterable` sin genéricos. Se permite `array` **solo** si el PHPDoc declara `list<T>` o `array<K, T>` con `T` objeto.

**TDD:** válido (`GetMovieResponse`), válido (`list<MovieDto>`), inválido (`array` sin PHPDoc), inválido (`array<string, mixed>`), inválido (`mixed`), método privado ignorado.

---

### B5.4 · Regla: respuestas `final readonly`

**Archivos:** `src/PHPStan/Rules/ResponseClassModifiersRule.php`, tests y datos.

**Regla:** toda clase no abstracta que implemente `ResponseInterface` o `WebhookEventInterface` debe ser `final` y `readonly`.

**TDD:** `final readonly` válido; falta `final`; falta `readonly`; clase abstracta ignorada; clase anónima ignorada.

---

### B5.5 · Inferencia (si "go") y publicación de la extensión

**Archivos:** `src/PHPStan/Type/IntegrationEngineSendReturnTypeExtension.php` (si "go"), `extension.neon` (nuevo, en la raíz), `composer.json`, `docs/phpstan.md`, `docs/adr/`, `CHANGELOG.md`.

**Pasos:**
1. (Si "go") TDD con `PHPStan\Testing\TypeInferenceTestCase`: ficheros de datos con `assertType('App\GetMovieResponse', $engine->send(GetMovieAction::getName()))`; caso no inferible → `assertType('IntegrationEngine\Core\Contract\Response\ResponseInterface', …)`.
2. `extension.neon` registra las reglas (y la extensión si existe) con tags `phpstan.rules.rule` / `phpstan.broker.dynamicMethodReturnTypeExtension`.
3. `composer.json`:
   ```json
   "extra": { "phpstan": { "includes": ["extension.neon"] } }
   ```
   `phpstan/phpstan` sigue en `require-dev`; documentar que la extensión solo se carga si el proyecto usa PHPStan.
4. Excluir `src/PHPStan` del autoload de producción no es necesario (no se carga sin PHPStan), pero **Infection sí debe cubrirlo**.
5. `docs/phpstan.md` con instalación, reglas y ejemplos de errores.
6. Verificación en proyecto vacío con `phpstan/extension-installer` (salida de `vendor/bin/phpstan diagnose` en el PR).
7. **Release v4.5.0** en el día 81, tras D5.1.

---

### B5.6 · SSRF: configuración

**Archivos:** `src/Bundle/DependencyInjection/Configuration.php`, `tests/Bundle/DependencyInjection/ConfigurationTest.php`.

```yaml
integration_engine:
  integrations:
    partners:
      base_url: 'https://partner-a.example'
      allowed_hosts: ['partner-a.example', '*.partners.example']   # opcional
      block_private_networks: true                                 # opcional, por defecto false
```

**TDD:** por defecto `allowed_hosts: []` (sin restricción) y `block_private_networks: false`; patrón con comodín solo al inicio (`*.`); rechaza esquemas o rutas en `allowed_hosts`; rechaza `block_private_networks` con `client_service` propio (el engine no controla ese cliente).

---

### B5.7 · SSRF: aplicación

**Archivos:**
- `src/Core/Security/HostPolicy.php` (nuevo: allowlist pura, sin red)
- `src/Core/Exception/DisallowedHostException.php` (nuevo)
- `src/Core/IntegrationEngine.php` (`resolveForDispatch()`: validar la URL final tras `baseUrl` y `connection_resolver`)
- `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php` (envolver transporte con `NoPrivateNetworkHttpClient` si `block_private_networks`)
- `SECURITY.md` (nuevo), `ARCHITECTURE.md`, `docs/security.md`, `docs/adr/00NN-ssrf-protection.md`
- Tests: `tests/Core/Security/HostPolicyTest.php`, `tests/Core/EngineHostPolicyTest.php`, `tests/Infrastructure/PrivateNetworkBlockingTest.php`, `tests/Bundle/DependencyInjection/IntegrationCompilerPassTest.php`

**TDD:**
1. `HostPolicyTest`: host exacto permitido; comodín `*.partners.example` permite `a.partners.example` y **no** `partners.example` ni `evilpartners.example`; mayúsculas; puerto ignorado; política vacía permite todo; host con `@` (`user@evil.example`) evaluado por el host real.
2. `EngineHostPolicyTest` (con `FakeClient`): `send(baseUrl: 'https://evil.example')` → `DisallowedHostException` **y** `FakeClient` sin peticiones; mismo caso vía `FakeConnectionResolver`; host permitido → petición enviada.
3. `PrivateNetworkBlockingTest` (con `NoPrivateNetworkHttpClient(MockHttpClient)`): `http://127.0.0.1`, `http://10.0.0.5`, `http://169.254.169.254/latest/meta-data`, `http://[::1]` → `RequestResponseException` con `statusCode 0` y causa de red privada; redirección 302 desde host público a `127.0.0.1` → bloqueada (verificar comportamiento real de `NoPrivateNetworkHttpClient` con redirecciones y documentarlo).
4. Compiler pass: con `block_private_networks: true` el transporte se envuelve; sin él, no.

**`SECURITY.md`:** versiones soportadas (4.x), cómo reportar (email), plazo orientativo de respuesta, alcance.

**Release v4.6.0** en el día 85, tras D5.2.

---

### B5.8 · Eventos del ciclo de vida: core

**Archivos:**
- `src/Core/Event/RequestSent.php`, `ResponseMapped.php`, `RequestFailed.php`, `TokenRefreshed.php` (nuevos, `final readonly`)
- `src/Core/IntegrationEngine.php`, `src/Core/Auth/DynamicAuthHandler.php`, `src/Core/Batch/BatchTokenRetry.php` (puntos de emisión)
- `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php` (inyectar `event_dispatcher` si existe)
- `composer.json` (`psr/event-dispatcher: ^1.0` en `require`)
- `tests/Core/Event/*`, `tests/Fake/FakeEventDispatcher.php`

**Contenido de los eventos (sin secretos):**
- `RequestSent`: integración, acción, método, path plantilla (`getRawPath()`), `connectionId` si existe, marca de tiempo.
- `ResponseMapped`: integración, acción, duración (ms), código HTTP si se conoce, clase de respuesta.
- `RequestFailed`: integración, acción, duración, código HTTP (`0` en red), clase de excepción, mensaje **saneado** (sin cuerpo de respuesta).
- `TokenRefreshed`: integración, acción de token, motivo (`cache_miss` | `rejected_401`).

**TDD:**
1. Con `FakeEventDispatcher`: `send()` feliz → `RequestSent` + `ResponseMapped` en orden; fallo → `RequestSent` + `RequestFailed`; 401 con token cacheado → `TokenRefreshed(rejected_401)`.
2. `sendMany()` → un par de eventos por clave.
3. Sin dispatcher (`null`) → comportamiento idéntico y sin errores.
4. Compiler pass: con servicio `event_dispatcher` se inyecta; sin él, `null`.

---

### B5.9 · Eventos: webhooks, garantía sin secretos, documentación y ADR

**Archivos:** `src/Core/Event/WebhookReceived.php`, `src/Core/Event/WebhookRejected.php`, `src/Infrastructure/Webhook/IntegrationWebhookRequestParser.php`, `tests/Core/Event/EventsContainNoSecretsTest.php`, `docs/events.md`, `docs/adr/00NN-events-vs-middlewares.md`, `CHANGELOG.md`, `ROADMAP.md`.

**Pasos:**
1. Parser emite `WebhookReceived` (integración, tipo, id) y `WebhookRejected` (integración, motivo) — nunca cabeceras ni payload.
2. **`EventsContainNoSecretsTest`:** ejecuta escenarios con token `Bearer SECRET_TOKEN_123`, secreto de webhook `whsec_SECRET_456`, cabecera de firma y body con `CONFIDENTIAL_789`; captura todos los eventos y comprueba con `var_export` recursivo que ninguno contiene esas cadenas.
3. **ADR eventos frente a middlewares:** los middlewares intervienen (modifican/cortan); los eventos observan (no alteran). Por qué existen ambos y cuándo usar cada uno.
4. `docs/events.md` con tabla de eventos, momento de emisión y ejemplo de listener de métricas.
5. **Release v4.7.0** en el día 89, tras D5.3.

---

### B5.10 · Cierre del bundle

**Archivos:** `README.md`, `ROADMAP.md`, `landing/src/*`, `docs/QUALITY.md`.

**Pasos:**
1. README → sección al principio **"Reviewing this project? Start here"** (10 minutos):
   1. The problem and the idea (3 líneas).
   2. Live demo: paso 1 del tour (antes/después) → enlace directo.
   3. ADRs clave: 0002, 0004, 0011, 0012.
   4. Un test de mutación representativo y por qué existe (enlace a `TimestampedHmacSignatureVerifierTest::testAcceptsExactlyAtToleranceBoundary`).
   5. Deptrac y extensión de PHPStan.
   6. Paso 6 del tour: Stripe de extremo a extremo.
2. `ROADMAP.md`: todo lo publicado a *Recently shipped*; *Now/Next* con el backlog de PLAN.md § 4 que siga vigente.
3. `docs/QUALITY.md`: MSI actual, nº de tests, fecha.
4. Landing: sección Roadmap actualizada; `node --test` verde; despliegue.
