# IntegrationEngine · Plan de ejecución por días

> **Pestaña principal.** Orden de trabajo día a día. El detalle de cada tarea está en:
> - **[BUNDLE.md](BUNDLE.md)** → tareas `B*` (repo `integrationEngine`, incluida la landing)
> - **DEMO.md** → tareas `D*` (repo nuevo `integrationEngine-demo`) — no escrito todavía; se redacta antes de que arranque la Fase 2 (Día 11)
>
> Este plan manda sobre el orden. BUNDLE.md y DEMO.md mandan sobre el *cómo*.

---

## 0 · Instrucciones para agentes

### 0.1 Unidad de trabajo
- **1 día = 1 sesión de ~2 horas.** Ritmo previsto: 3-5 días por semana (5-10 h/semana).
- **1 día = 1 rama = 1 pull request**, salvo que el día indique varias tareas pequeñas.
- Nombre de rama: `dayNN-<id-tarea>-<slug>` (ej. `day04-b1.4-doc-imports-test`).
- Si una tarea no cabe en su día, **no se amplía el alcance**: se entrega lo que cumple el *alcance mínimo* y el resto se anota como deuda en el PR.

### 0.2 Reglas no negociables
1. **TDD estricto** (ver 1.2). Ningún código de producción sin un test que haya fallado antes por el motivo correcto.
2. **Nunca bajar un umbral de calidad** (MSI, nivel de PHPStan, reglas de CS o Deptrac) para que algo pase.
3. **Sin baseline de PHPStan y sin `ignoreErrors` nuevos.** Un `@phpstan-ignore` solo con comentario que explique por qué y enlace a un ADR.
4. **Sin secretos en el repositorio.** Claves de TMDB, Stripe, SSH, etc., solo en `.env.local` / secretos de CI / servidor.
5. **No tocar nada fuera de "Dónde"** del día, salvo `CHANGELOG.md` y documentación directamente afectada.
6. **Si algo del plan resulta falso** (una API ha cambiado, un endpoint no existe, una versión no es compatible): parar, documentarlo en el PR y proponer alternativa. No improvisar.
7. En el repo del bundle, respetar además `CLAUDE.md` y `landing/CLAUDE.md`.

### 0.3 Contexto mínimo del proyecto
- **Objetivo:** portfolio para puesto de **Senior Backend Engineer**, revisado por recruiters (30 s) y tech leads (10 min).
- **Prioridades de lo que debe transmitir:** (1) integra sistemas externos de forma robusta, (2) buenas decisiones de diseño, (3) código limpio y testeado, (4) modernización de legacy.
- **Filosofía:** el bundle es el protagonista; la demo existe para verlo funcionar. Criterio: *¿refuerza que todas las integraciones tengan la misma forma, o solo demuestra una tecnología?* Lo segundo va a la demo, no al bundle.
- **Demo:** tour guiado en Twig (EN/ES) de una **tienda de alquiler de películas**: TMDB (catálogo, antes/después, paralelo) → proveedor propio (extensibilidad, resiliencia) → Stripe en modo test (pagos, webhooks, RabbitMQ, panel). Online en un VPS (~10 €/mes).
- **Fuera de alcance:** OAuth2 en el tour, Spotify, Blizzard, Railway Stations, migración legacy paso a paso, puente con Messenger dentro del bundle.

---

## 1 · Estándares de calidad (ambos proyectos)

### 1.1 Puertas de calidad

| Puerta | Comando | Umbral |
|---|---|---|
| Estilo | `vendor/bin/php-cs-fixer fix --dry-run --diff` | 0 cambios |
| Análisis estático | `vendor/bin/phpstan analyse --memory-limit=1G` | `level: max` sobre `src` **y** `tests`, 0 errores, sin baseline |
| Tests | `vendor/bin/phpunit` | 100 % verde, sin tests `skipped` ni `incomplete` sin justificar |
| Mutación | `vendor/bin/infection --threads=max --show-mutations` | **MSI ≥ 85 %** y **MSI cubierto ≥ 95 %**, definidos solo en `infection.json5` |
| Arquitectura | `vendor/bin/deptrac analyse` | 0 violaciones (bundle desde el día 06, demo desde el día 11) |

> **Nota de interpretación:** "85-95 %" se refiere a **mutantes eliminados** (MSI), no a mutantes escapados. `minMsi: 85` es el suelo; `minCoveredMsi: 95` garantiza que lo que está cubierto está bien testeado.

Configuración de Infection común (fragmento obligatorio en ambos repos):

```json5
{
    "minMsi": 85,
    "minCoveredMsi": 95,
    "testFramework": "phpunit",
    "logs": { "text": "var/infection/infection.log", "summary": "var/infection/summary.log" }
}
```

Targets de `Makefile` obligatorios en ambos repos:

```make
qa:        cs stan test deptrac     # antes de cada commit
ci:        qa mutation              # antes de abrir el PR
cs:        vendor/bin/php-cs-fixer fix --dry-run --diff
cs-fix:    vendor/bin/php-cs-fixer fix
stan:      vendor/bin/phpstan analyse --memory-limit=1G
test:      vendor/bin/phpunit
mutation:  vendor/bin/infection --threads=max --show-mutations
deptrac:   vendor/bin/deptrac analyse --no-progress
```

### 1.2 Ciclo TDD obligatorio
1. **Rojo:** escribir el test (o tests) del comportamiento. Ejecutar **solo ese test**: `vendor/bin/phpunit --filter <NombreDelTest>`. Confirmar que falla **por el motivo esperado** (no por un error de sintaxis o autoload). Pegar la salida del fallo en la descripción del PR.
2. **Verde:** el código mínimo para pasar. Nada más.
3. **Refactor:** limpiar con los tests en verde. Ejecutar `make qa`.
4. **Mutación:** ejecutar `make mutation` (o `vendor/bin/infection --filter=<ruta>` durante el desarrollo). Cada mutante escapado se mata con un test o se justifica en el PR.

**Tareas sin código PHP** (Docker, VPS, landing JS, documentación): el "test rojo" es una **verificación ejecutable** que falla antes y pasa después (script, `node --test`, `curl` de humo, test de documentación). Se indica en cada día.

### 1.3 Definición de "terminado" (DoD) de un día
- [ ] Alcance mínimo cumplido.
- [ ] `make ci` verde en local y en CI del repo afectado.
- [ ] Verificaciones del día marcadas con evidencia en el PR (salida de comandos o capturas).
- [ ] `CHANGELOG.md` actualizado si cambia el comportamiento público.
- [ ] Si el día cierra una funcionalidad del bundle: **la demo la usa y su CI está en verde** antes de etiquetar la versión.

### 1.4 Definición de "terminado" de una release del bundle
- [ ] Todas las tareas de la funcionalidad cerradas.
- [ ] ADR escrito en `docs/adr/`.
- [ ] Documentación en `docs/` cubierta por los tests de documentación (B1.4, B1.5).
- [ ] Job de contrato con la demo en verde (B2.2, desde el día 31).
- [ ] Entrada en `CHANGELOG.md` y release notes en GitHub.
- [ ] Tag `vX.Y.Z` y comprobación de que Packagist muestra la versión.
- [ ] La demo pasa a exigir `^X.Y` desde Packagist.

---

## 2 · Resumen de fases

| Fase | Días | Horas | Resultado visible | Versiones |
|---|---|---|---|---|
| **1 · Presentable** | 01-10 | ~20 h | Nada publicado resta credibilidad; estado y hoja de ruta públicos | bundle v4.1.1 |
| **2 · Demo online** | 11-32 | ~44 h | `demo.integrationengine.dev` con tienda TMDB y 3 pasos de tour | demo v1.0 |
| **3 · Integraciones robustas** | 33-45 | ~26 h | Paso "When suppliers fail" en el tour | bundle v4.2.0, v4.3.0 · demo v1.1 |
| **4 · Bidireccional con Stripe** | 46-74 | ~58 h | Alquiler con pago real en test, webhooks, RabbitMQ, panel | bundle v4.4.0 · demo v2.0 |
| **5 · Calidad de diseño visible** | 75-90 | ~32 h | Extensión PHPStan, SSRF, eventos del engine | bundle v4.5.0-v4.7.0 · demo v3.0 |
| **Total** | **90 días** | **~180 h** | | |

---

## 3 · Plan por días

### FASE 1 · Presentable (bundle y landing)

#### Día 01 · B1.1 · Puertas de calidad unificadas
- **Alcance mínimo:** un único juego de umbrales (85/95) en `infection.json5`; rutas de `excludes` y `ignore` corregidas; `phpunit.xml.dist` sin exclusiones obsoletas; PHPStan analiza `src` y `tests`; `Makefile` y CI sin umbrales propios.
- **Dónde:** `integrationEngine/` → `infection.json5`, `phpunit.xml.dist`, `phpstan.neon`, `Makefile`, `.github/workflows/php.yml`, `docs/QUALITY.md` (nuevo).
- **Verificación:**
  - [ ] `grep -rnE "min-msi|min-covered-msi" Makefile .github/` → sin resultados.
  - [ ] Todas las rutas de `infection.json5` → `excludes` y de `phpunit.xml.dist` → `<exclude>` existen (`test -f`).
  - [ ] `make ci` verde; MSI y MSI cubierto reales anotados en `docs/QUALITY.md`.
  - [ ] **Cumple su necesidad si:** no existe en el repo ninguna otra cifra de MSI distinta de 85/95.

#### Día 02 · B1.2 · Compatibilidad PHP 8.2 del generador
- **Alcance mínimo:** el código generado por `make:integration` y los ejemplos no usan constantes tipadas.
- **Dónde:** `src/Bundle/Generator/TemplateRenderer.php`, `src/Core/Registry/IntegrationName.php`, `tests/Bundle/Generator/TemplateRendererTest.php`, `README.md`, `docs/`.
- **Verificación:**
  - [ ] Test rojo previo: `TemplateRendererTest::testGeneratedIntegrationDoesNotUseTypedClassConstants` falla antes del cambio.
  - [ ] `grep -rn "const string" src/ README.md docs/ DOCUMENTATION*.md agent/` → sin resultados.
  - [ ] **Cumple su necesidad si:** el archivo generado pasa `php -l` con PHP 8.2 (se verifica en CI el día 03).

#### Día 03 · B1.3 · Matriz de CI y job del generador
- **Alcance mínimo:** matriz PHP 8.2/8.3/8.4 × `lowest`/`stable` × Symfony 6.4/7.4/8.x (con exclusiones válidas) y job que genera una integración en PHP 8.2 y la valida.
- **Dónde:** `.github/workflows/php.yml`, `README.md` (badge).
- **Verificación:**
  - [ ] En el run de CI, cada celda de la matriz muestra la versión de Symfony instalada (`composer show symfony/http-client`).
  - [ ] Job `generator-php82` verde, con `php -l` sobre cada archivo generado.
  - [ ] Prueba negativa: en una rama temporal se reintroduce `const string` y el job falla (captura en el PR, la rama se borra).
  - [ ] **Cumple su necesidad si:** lo que declara `composer.json` está probado en CI.
- **⚠️ Deuda descubierta durante la ejecución (no en el alcance de hoy):** el bundle **no se auto-registra con Symfony Flex**. Repro local con una app Symfony 6.4 real: `composer require carlosgude/integration-engine` deja `config/bundles.php` intacto y `php bin/console make:integration` falla con "no commands in the make namespace". Causa raíz rastreada en el código fuente de `symfony/flex` (`SymfonyBundle::getClassNames()`): la heurística de auto-detección de Flex busca la clase del bundle en `src/IntegrationEngineBundle.php` (namespace raíz PSR-4 `IntegrationEngine\` + sufijo `Bundle`), pero la clase real vive en `src/Bundle/IntegrationEngineBundle.php` (`IntegrationEngine\Bundle\IntegrationEngineBundle`), un nivel más abajo — no coincide y Flex la descarta en silencio. El campo `extra.symfony.bundles` que el `composer.json` del bundle ya declara **no lo lee Flex en ningún punto de su código fuente** (confirmado por grep en `vendor/symfony/flex/src/*.php`: el único uso de `extra['symfony']` es `root-dir`). Tampoco existe receta para `carlosgude/integration-engine` en `symfony/recipes-contrib` (confirmado contra el índice real). **Esto afecta a cualquier usuario real que siga el "Installation" del README** — el bundle se instala pero queda inerte, sin ningún error visible. Decisión del usuario (2026-09-16): usar un registro manual de `config/bundles.php` solo dentro del job `generator-php82` (comentado como workaround de un bug conocido, no como instalación recomendada) y anotar esto como deuda — ver fila correspondiente en `## 4 · Backlog`. Arreglarlo de verdad (opción 1: reestructurar para que la heurística de Flex acierte; opción 2: documentar el registro manual en el README) necesita su propio día, con las dos alternativas ya evaluadas.

#### Día 04 · B1.4 · Test de documentación: imports
- **Alcance mínimo:** test PHPUnit que extrae todos los `use IntegrationEngine\…` de los `.md` y comprueba que existen; corrección de los ~30 namespaces obsoletos.
- **Dónde:** `tests/Documentation/DocumentationImportsTest.php` (nuevo), `README.md`, `DOCUMENTATION*.md`, `docs/*.md`, guías de agentes.
- **Verificación:**
  - [ ] Test rojo previo listando los imports rotos (salida pegada en el PR).
  - [ ] Test verde tras corregir.
  - [ ] **Cumple su necesidad si:** copiar cualquier `use` de la documentación resuelve una clase o interfaz real.

#### Día 05 · B1.5 · Test de enlaces y guía de agentes única
- **Alcance mínimo:** test de enlaces relativos en `.md`; una sola guía de agentes en `agent/`; referencias `.agent/` corregidas; `landing/README.md` sin `snippets.js`.
- **Dónde:** `tests/Documentation/DocumentationLinksTest.php` (nuevo), `agent/integration-engine-agent-guide.md`, `integration-engine-agent-guide.md` (borrar), `docs/AI-AGENT-USAGE.md`, `landing/README.md`.
- **Verificación:**
  - [ ] Test rojo previo detecta el enlace roto a `.agent/` en `docs/AI-AGENT-USAGE.md`.
  - [ ] `test ! -f integration-engine-agent-guide.md`.
  - [ ] **Cumple su necesidad si:** ningún enlace relativo de la documentación apunta a un archivo inexistente.

#### Día 06 · B1.6 · Arquitectura hexagonal verificada con Deptrac
- **Alcance mínimo:** capas `Core`, `Infrastructure`, `Bundle`, `Tests` con reglas; job de CI; `make deptrac`.
- **Dónde:** `deptrac.yaml` (nuevo), `composer.json` (dev), `Makefile`, `.github/workflows/php.yml`, `ARCHITECTURE.md`.
- **Verificación:**
  - [ ] `vendor/bin/deptrac analyse` → 0 violaciones.
  - [ ] Prueba negativa: `use Symfony\Component\HttpClient\HttpClient;` temporal en un archivo de `src/Core` → Deptrac falla (captura en PR).
  - [ ] **Cumple su necesidad si:** la afirmación "hexagonal" del README está respaldada por un job de CI.

#### Día 07 · B1.7 · CHANGELOG, UPGRADE y política de versiones
- **Alcance mínimo:** `CHANGELOG.md` desde v2.0.0 reconstruido de los tags; `UPGRADE-4.0.md`; política de versionado en `CONTRIBUTING.md`; release notes de v4.0.0 y v4.1.0 en GitHub.
- **Dónde:** `CHANGELOG.md`, `UPGRADE-4.0.md`, `CONTRIBUTING.md`.
- **Verificación:**
  - [ ] Cada major (2.0, 3.0, 4.0) tiene sección "Breaking changes".
  - [ ] `DocumentationLinksTest` verde con los nuevos archivos.
  - [ ] **Cumple su necesidad si:** un revisor entiende por qué hubo cuatro majors y qué garantiza la 4.x.

#### Día 08 · B1.8 · ADRs iniciales
- **Alcance mínimo:** plantilla y 6 ADRs (lista en BUNDLE.md B1.8).
- **Dónde:** `docs/adr/0000-template.md` … `docs/adr/0006-*.md`, enlace desde `ARCHITECTURE.md` y `README.md`.
- **Verificación:**
  - [ ] Cada ADR tiene: Estado, Contexto, Decisión, Alternativas descartadas, Consecuencias.
  - [ ] ADR 0006 (profiler sin secretos) enlaza a un test existente o nuevo que lo garantiza, y ese test está en verde.
  - [ ] **Cumple su necesidad si:** las decisiones clave se pueden leer sin leer código.

#### Día 09 · B1.9 · Landing: correcciones de credibilidad
- **Alcance mínimo:** email corregido y funcionando, snippet `EngineRequest` correcto, versiones, sección Stripe y benchmark retirados, claims, CTA en singular, meta description, test de paridad i18n.
- **Dónde:** `landing/src/html.js`, `landing/src/i18n/en.js`, `landing/src/i18n/es.js`, `landing/test/i18n-parity.test.js` (nuevo), `landing/package.json` (nuevo, solo script de test), Cloudflare Email Routing.
- **Verificación:**
  - [ ] `cd landing && node --test` verde (prueba negativa: borrar una clave en `es.js` → falla).
  - [ ] `grep -rn "integration.dev\|EngineRequest::create\|Symfony 7+\|tres años\|three years\|Stripe" landing/src` → sin resultados.
  - [ ] Email de prueba a `hi@integrationengine.dev` y `hola@integrationengine.dev` recibido.
  - [ ] **Cumple su necesidad si:** nada de la landing es falso ni está roto.

#### Día 10 · B1.10 · Estado público y release v4.1.1
- **Alcance mínimo:** bloque de estado en README, `ROADMAP.md` (Now/Next/Later, Recently shipped, Out of scope), sección Now/Next/Later en la landing, release **v4.1.1**, despliegue de la landing.
- **Dónde:** `README.md`, `ROADMAP.md` (nuevo), `landing/src/html.js`, `landing/src/i18n/*.js`, `CHANGELOG.md`.
- **Verificación:**
  - [ ] `node --test` y `make ci` verdes.
  - [ ] Tag v4.1.1 visible en Packagist.
  - [ ] Landing desplegada (`make deploy-landing`) y sección visible en EN y ES.
  - [ ] **Cumple su necesidad si:** un recruiter ve en 30 s que el proyecto está vivo y hacia dónde va, sin promesas con fecha.

---

### FASE 2 · Demo online (tienda TMDB)

#### Día 11 · D2.1 · Bootstrap del repo y puertas de calidad
- **Alcance mínimo:** Symfony 7.4 LTS + PHP 8.4, bundle `^4.1.1` desde Packagist, herramientas de calidad con los mismos umbrales, Deptrac con capas de la demo, `Makefile`.
- **Dónde:** repo nuevo `integrationEngine-demo/` → `composer.json`, `phpstan.neon`, `.php-cs-fixer.dist.php`, `infection.json5`, `phpunit.xml.dist`, `deptrac.yaml`, `Makefile`, `.gitignore`, `composer.local.json.dist`.
- **Verificación:**
  - [ ] `composer show carlosgude/integration-engine` → versión de Packagist (no `dev-main`, no `path`).
  - [ ] `grep -n '"type": "path"' composer.json composer.lock` → sin resultados.
  - [ ] `make ci` verde con un test de humo (`KernelBootTest`).
  - [ ] **Cumple su necesidad si:** `git clone` + `composer install` + `make ci` funciona en una carpeta vacía.

#### Día 12 · D2.2 · Docker y CI
- **Alcance mínimo:** `compose.yaml` con FrankenPHP, `compose.override.yaml` para desarrollo, CI con puertas de calidad y build de imagen.
- **Dónde:** `compose.yaml`, `compose.override.yaml`, `Dockerfile`, `.dockerignore`, `.github/workflows/ci.yml`, `Makefile` (`up`, `down`, `sh`).
- **Verificación:**
  - [ ] `docker compose up -d --wait` y `curl -fsS http://localhost/` → 200.
  - [ ] CI verde con los 5 jobs de calidad + build de la imagen.
  - [ ] **Cumple su necesidad si:** local y CI ejecutan exactamente las mismas puertas.

#### Día 13 · D2.3 · i18n y layout base
- **Alcance mínimo:** rutas con prefijo `/{_locale}` (`en`, `es`), redirección de `/` a `/en`, layout Twig, selector de idioma, pie con atribución de TMDB, test de paridad de traducciones.
- **Dónde:** `config/packages/translation.yaml`, `config/routes.yaml`, `translations/messages.{en,es}.yaml`, `templates/base.html.twig`, `tests/Translation/TranslationParityTest.php`.
- **Verificación:**
  - [ ] Test rojo previo de paridad y de redirección `/` → `/en`.
  - [ ] `curl -s http://localhost/es/ | grep -i "TMDB"` → aviso de atribución presente.
  - [ ] **Cumple su necesidad si:** toda cadena visible existe en EN y ES.

#### Día 14 · D2.4 · Motor del tour: registro de pasos
- **Alcance mínimo:** `TourStep`, `TourRegistry` cargado de `config/tour.yaml`, navegación anterior/siguiente, 404 para pasos desconocidos.
- **Dónde:** `src/Tour/Domain/`, `src/Tour/Infrastructure/YamlTourRegistry.php`, `config/tour.yaml`, `tests/Tour/`.
- **Verificación:**
  - [ ] Tests unitarios de orden, anterior/siguiente en extremos y paso desconocido.
  - [ ] **Cumple su necesidad si:** añadir un paso solo requiere tocar `config/tour.yaml` y traducciones.

#### Día 15 · D2.5 · Extractor de snippets desde el código real
- **Alcance mínimo:** extracción entre marcadores `tour:start <id>` / `tour:end`, lista blanca de directorios, protección contra path traversal, resaltado de sintaxis en servidor.
- **Dónde:** `src/Tour/Infrastructure/SourceSnippetExtractor.php`, `src/Tour/Infrastructure/SyntaxHighlighter.php`, `tests/Tour/Infrastructure/`.
- **Verificación:**
  - [ ] Tests: marcador válido, marcador inexistente (excepción), marcador sin cierre (excepción), `../../.env` rechazado, ruta fuera de lista blanca rechazada.
  - [ ] **Cumple su necesidad si:** es imposible mostrar en el tour código que no exista en el repo.

#### Día 16 · D2.6 · UI del tour, endpoint Run y registro de llamadas
- **Alcance mínimo:** plantilla de paso (explicación, snippets, botón Run), endpoint JSON de ejecución, middleware `TraceRecorder` que registra llamadas del engine por petición.
- **Dónde:** `src/Tour/UI/TourController.php`, `templates/tour/step.html.twig`, `assets/tour.js`, `src/Shared/Observability/TraceRecorderMiddleware.php`, `src/Shared/Observability/CallTrace.php`, `tests/`.
- **Verificación:**
  - [ ] `WebTestCase`: la página de un paso de prueba muestra el snippet real; el endpoint Run devuelve `{result, trace}` con el contrato definido.
  - [ ] Test del middleware en `process()` y `processMany()`.
  - [ ] **Cumple su necesidad si:** cada paso enseña código, lo ejecuta y muestra qué llamadas hizo el engine.

#### Día 17 · D2.7 · TMDB: configuración y películas
- **Alcance mínimo:** integración TMDB con Bearer estático; acciones `GetConfiguration` y `GetMovie`; DTOs tipados; URL del póster construida solo en el mapper.
- **Dónde:** `config/packages/integration_engine.yaml`, `src/Catalog/Infrastructure/Integrations/Tmdb/`, `tests/Catalog/Infrastructure/Integrations/Tmdb/`, `tests/Fixtures/tmdb/`.
- **Verificación:**
  - [ ] Tests de mappers con fixtures grabados de la API real.
  - [ ] `grep -rn "poster_path\|secure_base_url" src/ --include=*.php | grep -v Mapper` → solo DTOs/mappers de TMDB.
  - [ ] **Cumple su necesidad si:** ningún campo crudo de TMDB sale de la capa de integración.

#### Día 18 · D2.8 · TMDB: temporadas, Gateway y dominio
- **Alcance mínimo:** `GetTvSeason` (dos placeholders), idioma por placeholder obligatorio, `MovieCatalogGateway` → objeto de dominio `Movie`, caché de `GetConfiguration`.
- **Dónde:** `src/Catalog/Domain/`, `src/Catalog/Application/MovieCatalogGateway.php`, `src/Catalog/Infrastructure/Integrations/Tmdb/`, `deptrac.yaml`, `tests/Catalog/`.
- **Verificación:**
  - [ ] Tests del Gateway con `MockHttpClient`; test de que falta `language` → excepción del engine antes de la llamada.
  - [ ] Deptrac: `Catalog\Domain` no depende de `Catalog\Infrastructure`.
  - [ ] **Cumple su necesidad si:** los controladores solo importan dominio y Gateway.

#### Día 19 · D2.9 · Legacy y tests de caracterización
- **Alcance mínimo:** `TmdbApiService` (god class con los 5 antipatrones) y su controlador; test que demuestra que legacy y engine producen la misma salida de dominio.
- **Dónde:** `src/Legacy/`, `tests/Legacy/LegacyEngineParityTest.php`.
- **Verificación:**
  - [ ] Test de paridad verde con los mismos fixtures para ambas implementaciones.
  - [ ] Deptrac: nada fuera de `src/Legacy/` depende de `Legacy`.
  - [ ] **Cumple su necesidad si:** el antes/después compara dos implementaciones que hacen exactamente lo mismo.

#### Día 20 · D2.10 · Paso 1 del tour: "The problem"
- **Alcance mínimo:** textos EN/ES, marcadores en ambas implementaciones para los 5 antipatrones, Run de ambas lado a lado.
- **Dónde:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, marcadores en `src/Legacy/` y `src/Catalog/`.
- **Verificación:**
  - [ ] `TourSnippetsResolveTest`: todos los snippets declarados en `config/tour.yaml` se resuelven.
  - [ ] Recorrido manual en EN y ES (capturas en PR).
  - [ ] **Cumple su necesidad si:** el primer paso explica el problema en menos de 30 segundos de lectura.

#### Día 21 · D2.11 · Catálogo en paralelo
- **Alcance mínimo:** portada de la tienda con 20-30 películas curadas, carga con `sendMany()`, fallos parciales mostrados como tarjeta de reserva.
- **Dónde:** `config/packages/catalog.yaml`, `src/Catalog/Application/`, `src/Catalog/UI/StorefrontController.php`, `templates/store/`, `tests/Catalog/`.
- **Verificación:**
  - [ ] Test con una respuesta 500 en el batch → la página renderiza el resto y una tarjeta de error.
  - [ ] **Cumple su necesidad si:** la portada carga con N llamadas concurrentes y tolera fallos parciales.

#### Día 22 · D2.12 · Paso 2 del tour: "Parallel requests" y benchmark
- **Alcance mínimo:** Run que mide secuencial frente a paralelo en vivo; comando `app:benchmark` con mediana de 20 repeticiones.
- **Dónde:** `src/Catalog/UI/Console/BenchmarkCommand.php`, `src/Shared/Stats/Median.php`, `config/tour.yaml`, traducciones, `tests/`.
- **Verificación:**
  - [ ] Tests de `Median` (par, impar, vacío) y del comando con `MockHttpClient` con retardos.
  - [ ] **Cumple su necesidad si:** las cifras de rendimiento que se publiquen salen de este comando.

#### Día 23 · D2.13 · Proveedor propio y adaptador CSV
- **Alcance mínimo:** servicio `supplier` en `compose.yaml` que sirve `prices.csv`; `CsvClientAdapter` (`ClientAdapterInterface`) e integración de precios.
- **Dónde:** `docker/supplier/`, `compose.yaml`, `src/Pricing/Infrastructure/Http/CsvClientAdapter.php`, `src/Pricing/`, `tests/Pricing/`.
- **Verificación:**
  - [ ] Tests del adaptador: CSV válido, cabecera ausente, fila malformada.
  - [ ] `docker compose exec php curl -fsS http://supplier/prices.csv` → 200.
  - [ ] **Cumple su necesidad si:** un protocolo no JSON se integra con la misma forma que el resto.

#### Día 24 · D2.14 · GraphQL y middleware propio
- **Alcance mínimo:** integración GraphQL pública de países (`client: graphql`) y `RateLimitMiddleware` aplicado a TMDB.
- **Dónde:** `src/Pricing/Infrastructure/Integrations/Countries/`, `src/Shared/Infrastructure/Middleware/RateLimitMiddleware.php`, `config/packages/integration_engine.yaml`, `config/packages/rate_limiter.yaml`, `tests/`.
- **Verificación:**
  - [ ] Verificación previa de que la API GraphQL elegida responde (anotar en PR; si no responde, proponer alternativa y parar).
  - [ ] Tests del middleware: consume token, rechaza al agotar límite, `processMany()` consume N tokens.
  - [ ] **Cumple su necesidad si:** GraphQL y middleware propio se configuran solo con YAML y una clase.

#### Día 25 · D2.15 · Paso 3 del tour: "Behind the counter"
- **Alcance mínimo:** paso de puntos de extensión (CSV, GraphQL, middleware) con textos EN/ES y Run.
- **Dónde:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, marcadores en `src/Pricing/` y `src/Shared/`.
- **Verificación:**
  - [ ] `TourSnippetsResolveTest` verde.
  - [ ] **Cumple su necesidad si:** se ve que el bundle se extiende sin tocar su código.

#### Día 26 · D2.16 · Endurecimiento para uso público
- **Alcance mínimo:** rate limiter por IP en endpoints Run, páginas de error propias, cabeceras de seguridad, entorno `prod` sin debug.
- **Dónde:** `src/Tour/UI/`, `config/packages/rate_limiter.yaml`, `config/packages/prod/`, `templates/bundles/TwigBundle/Exception/`, `Caddyfile`, `tests/`.
- **Verificación:**
  - [ ] Test: la llamada N+1 a Run desde la misma IP → 429 con cuerpo JSON.
  - [ ] `curl -sI` en `prod` muestra `Content-Security-Policy`, `X-Content-Type-Options`, `Referrer-Policy`.
  - [ ] **Cumple su necesidad si:** la demo aguanta visitas públicas sin agotar cuotas de terceros.

#### Día 27 · D2.17 · VPS
- **Alcance mínimo:** servidor Hetzner, usuario `deploy`, SSH solo con clave, firewall, actualizaciones automáticas, Docker, DNS `demo.integrationengine.dev`.
- **Dónde:** Hetzner Cloud, Cloudflare DNS, `docs/ops/VPS.md` (en el repo de la demo; checklist sin secretos).
- **Verificación:**
  - [ ] `ssh root@<ip>` con contraseña → rechazado.
  - [ ] Escaneo de puertos desde fuera: solo 22 (restringido a tu IP), 80 y 443.
  - [ ] `dig +short demo.integrationengine.dev` → IP del VPS.
  - [ ] **Cumple su necesidad si:** el servidor es seguro por defecto antes de desplegar nada.

#### Día 28 · D2.18 · Despliegue continuo
- **Alcance mínimo:** `compose.prod.yaml`, imagen en GHCR, workflow que despliega por SSH al fusionar en `main` y hace prueba de humo.
- **Dónde:** `compose.prod.yaml`, `.github/workflows/deploy.yml`, secretos de GitHub, `/srv/integrationengine-demo/` en el VPS.
- **Verificación:**
  - [ ] Merge a `main` → workflow verde → `curl -fsS https://demo.integrationengine.dev/en/` → 200 con HTTPS válido.
  - [ ] Los secretos de la app solo existen en el VPS (`.env.prod.local`, permisos 600).
  - [ ] **Cumple su necesidad si:** desplegar es fusionar un PR.

#### Día 29 · D2.19 · Healthcheck y operación mínima
- **Alcance mínimo:** `/healthz`, healthcheck de Docker, rotación de logs, monitor externo gratuito.
- **Dónde:** `src/Shared/UI/HealthController.php`, `compose.prod.yaml`, `docs/ops/RUNBOOK.md`, `tests/`.
- **Verificación:**
  - [ ] Test de `/healthz` (200 y sin datos sensibles).
  - [ ] `docker inspect` muestra `healthy`.
  - [ ] **Cumple su necesidad si:** te enteras de una caída antes que un recruiter.

#### Día 30 · D2.20 + B2.1 · README de la demo, archivo del repo antiguo y enlaces
- **Alcance mínimo:** README de la demo (qué es, tour, ejecutar en local, arquitectura); banner y archivado de `integrationEngine-use-example`; enlaces y botón "Live demo" en bundle y landing.
- **Dónde:** `integrationEngine-demo/README.md`; `integrationEngine-use-example/README.md`; `integrationEngine/README.md`, `landing/src/html.js`, `landing/src/i18n/*.js`.
- **Verificación:**
  - [ ] `grep -rn "integrationEngine-use-example" integrationEngine/ --include=*.md --include=*.js` → solo menciones de archivo.
  - [ ] Repo antiguo marcado como *Archived* en GitHub.
  - [ ] `node --test` verde en `landing/`.
  - [ ] **Cumple su necesidad si:** todos los caminos llevan a la demo nueva.

#### Día 31 · B2.2 · Job de contrato bundle ↔ demo
- **Alcance mínimo:** job en el CI del bundle que instala la demo con el bundle del commit actual y ejecuta sus tests.
- **Dónde:** `integrationEngine/.github/workflows/contract.yml`.
- **Verificación:**
  - [ ] Job verde en un PR normal.
  - [ ] Prueba negativa: renombrar temporalmente un método público usado por la demo → job rojo (captura en PR).
  - [ ] **Cumple su necesidad si:** un cambio rompedor del bundle no puede fusionarse sin enterarse.

#### Día 32 · D2.21 · Revisión en frío y demo v1.0
- **Alcance mínimo:** recorrido completo como recruiter y como tech lead, corrección de lo encontrado, tag `v1.0.0` de la demo.
- **Dónde:** ambos repos (solo correcciones), `integrationEngine-demo/CHANGELOG.md`.
- **Verificación:**
  - [ ] Checklist de revisión de DEMO.md D2.21 completa.
  - [ ] Lighthouse de la portada y del paso 1: Accessibility ≥ 90.
  - [ ] **Cumple su necesidad si:** puedes poner el enlace en tu CV hoy.

---

### FASE 3 · Integraciones robustas

#### Día 33 · B3.1 · Form-encoded: contrato y `Request`
- **Alcance mínimo:** `FormEncodedBodyInterface`, enum `BodyEncoding`, `Request` con codificación (retrocompatible).
- **Dónde:** `src/Core/Contract/Action/FormEncodedBodyInterface.php`, `src/Core/Contract/Client/BodyEncoding.php`, `src/Core/Contract/Client/Request.php`, `tests/Core/`.
- **Verificación:**
  - [ ] Tests: `Request` por defecto es JSON; `withHeader()` conserva la codificación.
  - [ ] Test de retrocompatibilidad: construcción posicional antigua de `Request` sigue funcionando.
  - [ ] **Cumple su necesidad si:** el core puede expresar un body form-encoded sin romper a nadie.

#### Día 34 · B3.2 · Form-encoded: adaptador REST
- **Alcance mínimo:** `send()` y `sendMany()` envían `application/x-www-form-urlencoded` para bodies `FormEncodedBodyInterface`, incluidos arrays anidados.
- **Dónde:** `src/Infrastructure/Http/SymfonyHttpClientAdapter.php`, `tests/Infrastructure/SymfonyHttpClientAdapterFormBodyTest.php`.
- **Verificación:**
  - [ ] Tests con `MockHttpClient` que inspeccionan `Content-Type` y cuerpo (`metadata[movie_id]=550`) en `send`, `sendMany` y con request middlewares.
  - [ ] **Cumple su necesidad si:** Stripe podría recibir la petición tal cual.

#### Día 35 · D3.1 · Proveedor: escenarios y reservas
- **Alcance mínimo:** el servicio `supplier` expone stock con escenarios deterministas y un endpoint de reservas que exige form-encoded e `Idempotency-Key`.
- **Dónde:** `docker/supplier/src/`, `docker/supplier/tests/`, `compose.yaml`.
- **Verificación:**
  - [ ] Tests del servicio (PHPUnit propio del directorio): cada escenario, 415 si el `Content-Type` no es form, misma respuesta con la misma `Idempotency-Key`.
  - [ ] **Cumple su necesidad si:** los fallos de la demo son reproducibles a voluntad.

#### Día 36 · D3.3 · Reservas form-encoded contra el commit del bundle
- **Alcance mínimo:** integración `Supplier` con `CreateReservation` (form + `Idempotency-Key`) usando el bundle local vía `composer.local.json`.
- **Dónde:** `src/Rental/Infrastructure/Integrations/Supplier/`, `tests/Rental/`.
- **Verificación:**
  - [ ] Test de integración contra el servicio `supplier` en Docker (grupo `@integration`) y test unitario con `MockHttpClient`.
  - [ ] **Cumple su necesidad si:** la funcionalidad de v4.2 tiene un consumidor real antes de publicarse.

#### Día 37 · B3.3 · Form-encoded: GraphQL, docs y release v4.2.0
- **Alcance mínimo:** error explícito si se usa form con GraphQL; documentación; ADR; release **v4.2.0**; la demo exige `^4.2`.
- **Dónde:** `src/Infrastructure/Http/GraphQLClientAdapter.php`, `docs/actions.md`, `docs/clients.md`, `docs/adr/`, `CHANGELOG.md`; en la demo `composer.json`.
- **Verificación:**
  - [ ] DoD de release (1.4) completa.
  - [ ] CI de la demo verde con `^4.2` desde Packagist.
  - [ ] **Cumple su necesidad si:** v4.2.0 está publicada y usada.

#### Día 38 · B3.4 · Resiliencia: configuración
- **Alcance mínimo:** nodos `timeout`, `max_duration` y `retry` por integración con validación.
- **Dónde:** `src/Bundle/DependencyInjection/Configuration.php`, `tests/Bundle/DependencyInjection/ConfigurationTest.php`.
- **Verificación:**
  - [ ] Tests de valores por defecto, valores válidos y rechazo de inválidos (negativos, `multiplier < 1`, códigos fuera de 100-599).
  - [ ] **Cumple su necesidad si:** la configuración de resiliencia es declarativa y validada al compilar el contenedor.

#### Día 39 · B3.5 · Resiliencia: cableado en el contenedor
- **Alcance mínimo:** el cliente HTTP de cada integración se decora con opciones de timeout y `RetryableHttpClient` + `GenericRetryStrategy` solo si hay configuración.
- **Dónde:** `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php`, `tests/Bundle/DependencyInjection/IntegrationCompilerPassTest.php`.
- **Verificación:**
  - [ ] Tests del compiler pass: sin `retry` no hay decorador; con `retry` la definición usa `RetryableHttpClient`; con `client_service` propio no se decora.
  - [ ] **Cumple su necesidad si:** integraciones existentes no cambian de comportamiento.

#### Día 40 · B3.6 · Resiliencia: comportamiento
- **Alcance mínimo:** reintentos en métodos idempotentes, POST sin reintento salvo opt-in, `Retry-After`, timeout convertido en `RequestResponseException`.
- **Dónde:** `src/Infrastructure/Http/` (si hace falta estrategia propia), `tests/Infrastructure/ResilienceBehaviourTest.php`.
- **Verificación:**
  - [ ] Tests con `MockHttpClient`: GET 503→503→200 = éxito con 3 intentos; POST 503 = fallo con 1 intento; POST con `retry_non_idempotent` = 3 intentos; 429 con `Retry-After: 2` respeta el retardo (reloj/estrategia inyectable); timeout → `statusCode 0`.
  - [ ] **Cumple su necesidad si:** los fallos transitorios se recuperan sin duplicar operaciones no idempotentes.

#### Día 41 · D3.2 · Stock con resiliencia contra el commit del bundle
- **Alcance mínimo:** integración `GetStock` con `timeout` y `retry`; `StockGateway`; cabecera `X-Attempt` del proveedor visible en la respuesta.
- **Dónde:** `src/Rental/Infrastructure/Integrations/Supplier/`, `src/Rental/Application/StockGateway.php`, `config/packages/integration_engine.yaml`, `tests/Rental/`.
- **Verificación:**
  - [ ] Tests unitarios por escenario e integración contra `supplier` en Docker.
  - [ ] **Cumple su necesidad si:** la resiliencia de v4.3 tiene consumidor real antes de publicarse.

#### Día 42 · B3.7 · Resiliencia: batch, timeout por acción, docs y release v4.3.0
- **Alcance mínimo:** concurrencia de `sendMany()` preservada con reintentos; `timeout` por acción en YAML; `docs/resilience.md`; ADR; release **v4.3.0**.
- **Dónde:** `src/Core/Contract/Action/AbstractAction.php`, `src/Infrastructure/Adapter/YamlConfigAdapter.php`, `src/Infrastructure/Http/SymfonyHttpClientAdapter.php`, `docs/`, `CHANGELOG.md`.
- **Verificación:**
  - [ ] Test: batch de 5 con una petición que falla 2 veces → todas se despachan antes de consumir ninguna respuesta; resultado correcto.
  - [ ] DoD de release completa; demo con `^4.3`.
  - [ ] **Cumple su necesidad si:** reintentar no convierte el paralelo en secuencial.

#### Día 43 · D3.4 · Paso 4 del tour: "When suppliers fail"
- **Alcance mínimo:** botones de escenario (ok, falla dos veces, 429, lento, reserva duplicada), explicación EN/ES, intentos visibles.
- **Dónde:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, `templates/tour/`, marcadores en `config/` y `src/Rental/`.
- **Verificación:**
  - [ ] `TourSnippetsResolveTest` verde; `WebTestCase` de cada botón Run.
  - [ ] **Cumple su necesidad si:** se ve en vivo que el engine reintenta lo seguro y no lo peligroso.

#### Día 44 · D3.5 · Despliegue y demo v1.1
- **Alcance mínimo:** servicio `supplier` en producción (no expuesto a Internet), despliegue, tag `v1.1.0`, `ROADMAP.md` del bundle actualizado.
- **Dónde:** `compose.prod.yaml`, `integrationEngine/ROADMAP.md`.
- **Verificación:**
  - [ ] Desde fuera, `supplier` no responde; desde `php`, sí.
  - [ ] Paso 4 funciona en producción.
  - [ ] **Cumple su necesidad si:** la fase es visible online.

#### Día 45 · Colchón de la fase 3
- **Alcance mínimo:** deuda anotada en PRs de la fase, mutantes escapados pendientes, revisión de textos del tour.
- **Dónde:** según deuda.
- **Verificación:** `make ci` verde en ambos repos; lista de deuda de la fase vacía o movida explícitamente al backlog.

---

### FASE 4 · Bidireccional con Stripe

#### Día 46 · B4.1 · Webhooks: spike y ADRs
- **Alcance mínimo:** comprobar en código fuente de Symfony (6.4, 7.4, 8.x) el contrato de `AbstractRequestParser`, `RemoteEvent`, `WebhookController` y la respuesta ante `null`/rechazo; decidir compatibilidad; ADRs de diseño e idempotencia.
- **Dónde:** `docs/adr/00NN-inbound-webhooks.md`, `docs/adr/00NN-webhook-idempotency.md`, `docs/spikes/webhooks.md`.
- **Verificación:**
  - [ ] Spike con enlaces a las líneas exactas del código de Symfony consultado para cada versión.
  - [ ] **Cumple su necesidad si:** no queda ninguna suposición sin verificar antes de escribir código.

#### Día 47 · B4.2 · Webhooks: definición en YAML
- **Alcance mínimo:** sección `webhooks:` en `{Name}.yaml` parseada a objetos tipados y validada.
- **Dónde:** `src/Core/Contract/Webhook/WebhookDefinition.php`, `src/Core/Contract/Webhook/SignatureConfig.php`, `src/Core/Port/ConfigPort.php`, `src/Infrastructure/Adapter/YamlConfigAdapter.php`, `tests/Infrastructure/YamlConfigAdapterWebhooksTest.php`.
- **Verificación:**
  - [ ] Tests: definición válida, `type` de firma desconocido, mapper que no existe, `event_type` duplicado.
  - [ ] **Cumple su necesidad si:** un webhook se declara igual que una acción.

#### Día 48 · B4.3 · Verificador `hmac_sha256`
- **Alcance mínimo:** verificador HMAC simple con prefijo configurable.
- **Dónde:** `src/Core/Contract/Webhook/SignatureVerifierInterface.php`, `src/Core/Webhook/HmacSha256SignatureVerifier.php`, `tests/Core/Webhook/`.
- **Verificación:**
  - [ ] Tests: válida, inválida, cabecera ausente, prefijo incorrecto, body modificado, comparación con `hash_equals` (test de mutación que elimina la comparación muere).
  - [ ] **Cumple su necesidad si:** una firma incorrecta nunca se acepta.

#### Día 49 · B4.4 · Verificador `timestamped_hmac` (esquema de Stripe)
- **Alcance mínimo:** parseo `t=…,v1=…`, varias `v1`, tolerancia con reloj PSR-20 inyectable, `v0` ignorado.
- **Dónde:** `src/Core/Webhook/TimestampedHmacSignatureVerifier.php`, `composer.json` (`psr/clock`), `tests/Core/Webhook/`.
- **Verificación:**
  - [ ] Tests: válida; dos `v1` con una válida (rotación); ninguna válida; timestamp fuera de tolerancia (pasado y futuro); cabecera malformada; body modificado.
  - [ ] **Cumple su necesidad si:** reproduce el esquema documentado por Stripe, incluida la rotación de secretos.

#### Día 50 · B4.5 · Mapper de webhooks y evento tipado
- **Alcance mínimo:** `AbstractWebhookMapper`, `WebhookEventInterface`, evento serializable.
- **Dónde:** `src/Core/Contract/Webhook/AbstractWebhookMapper.php`, `src/Core/Contract/Webhook/WebhookEventInterface.php`, `tests/Core/Webhook/`.
- **Verificación:**
  - [ ] Tests: mapeo correcto; `serialize`/`unserialize` conserva el evento; mapper con definición distinta → excepción.
  - [ ] **Cumple su necesidad si:** la invariante del mapper se cumple también en la entrada.

#### Día 51 · B4.6 · Request parser genérico
- **Alcance mínimo:** `IntegrationWebhookRequestParser` que verifica, resuelve el tipo de evento, mapea y devuelve `MappedRemoteEvent`.
- **Dónde:** `src/Infrastructure/Webhook/IntegrationWebhookRequestParser.php`, `src/Infrastructure/Webhook/MappedRemoteEvent.php`, `tests/Infrastructure/Webhook/`.
- **Verificación:**
  - [ ] Tests con `Symfony\Component\HttpFoundation\Request` construidas a mano: flujo feliz, método no POST, `Content-Type` no JSON, JSON inválido, tipo desconocido (según ADR).
  - [ ] **Cumple su necesidad si:** no hace falta escribir un parser por proveedor.

#### Día 52 · B4.7 · Cableado en el contenedor
- **Alcance mínimo:** servicio `integration_engine.webhook_parser.{name}` por integración con webhooks, solo si `symfony/webhook` está instalado.
- **Dónde:** `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php`, `composer.json` (`suggest` y `require-dev`), `tests/Bundle/DependencyInjection/`.
- **Verificación:**
  - [ ] Tests: con webhooks → servicio definido; sin webhooks → no; sin la clase de Symfony → excepción de configuración clara.
  - [ ] Deptrac verde (Core sin Symfony).
  - [ ] **Cumple su necesidad si:** quien no usa webhooks no carga nada nuevo.

#### Día 53 · B4.8 · Rechazos con códigos de motivo
- **Alcance mínimo:** todos los fallos terminan en `RejectWebhookException` con código estable y sin filtrar el secreto.
- **Dónde:** `src/Core/Exception/WebhookRejectionReason.php`, `src/Infrastructure/Webhook/`, `tests/Infrastructure/Webhook/WebhookRejectionTest.php`.
- **Verificación:**
  - [ ] Test parametrizado de todos los motivos; test de que el mensaje no contiene el secreto ni la firma esperada.
  - [ ] **Cumple su necesidad si:** un rechazo es diagnosticable sin exponer nada sensible.

#### Día 54 · D4.1 · Persistencia SQLite
- **Alcance mínimo:** Doctrine con SQLite en volumen, entidades `Rental` y `WebhookDelivery`, migraciones.
- **Dónde:** `config/packages/doctrine.yaml`, `migrations/`, `src/Rental/Domain/`, `src/Payments/Domain/`, `src/*/Infrastructure/Persistence/`, `tests/`.
- **Verificación:**
  - [ ] Tests de repositorios con SQLite en memoria; `bin/console doctrine:schema:validate` OK.
  - [ ] **Cumple su necesidad si:** alquileres y entregas sobreviven a un reinicio del contenedor.

#### Día 55 · D4.2 · Stripe: crear PaymentIntent
- **Alcance mínimo:** `Stripe.yaml` con `CreatePaymentIntent` (form, Bearer `sk_test`, `Stripe-Version` fijada, `Idempotency-Key`), mapper y manejo de 402.
- **Dónde:** `src/Payments/Infrastructure/Integrations/Stripe/`, `config/packages/integration_engine.yaml`, `tests/Payments/`, `tests/Fixtures/stripe/`.
- **Verificación:**
  - [ ] Tests con `MockHttpClient`: cuerpo form exacto, cabeceras, 200 → DTO, 402 → excepción de dominio `PaymentDeclined`.
  - [ ] Prueba manual con clave de test: PaymentIntent visible en el Dashboard de Stripe (captura sin datos sensibles).
  - [ ] **Cumple su necesidad si:** se crean pagos de prueba reales con la forma estándar del bundle.

#### Día 56 · D4.3 · Stripe: listado de pagos
- **Alcance mínimo:** `ListPaymentIntents` con `limit` y `starting_after` opcional; `PaymentsGateway`.
- **Dónde:** `src/Payments/Infrastructure/Integrations/Stripe/`, `src/Payments/Application/PaymentsGateway.php`, `tests/Payments/`.
- **Verificación:**
  - [ ] Tests: sin cursor, con cursor, `has_more`.
  - [ ] **Cumple su necesidad si:** el panel puede paginar pagos desde Stripe como fuente de verdad.

#### Día 57 · D4.4 · Caso de uso "alquilar"
- **Alcance mínimo:** `POST /{_locale}/movies/{id}/rent` con tarjeta `success`/`declined`, CSRF, rate limit, `Rental` en `pending`.
- **Dónde:** `src/Rental/Application/RentMovie.php`, `src/Rental/UI/RentController.php`, `templates/store/movie.html.twig`, `tests/Rental/`.
- **Verificación:**
  - [ ] `WebTestCase`: sin CSRF → 403; tarjeta desconocida → 400; flujo feliz → 202 con `rentalId`; límite → 429.
  - [ ] **Cumple su necesidad si:** alquilar crea el pago y deja el estado pendiente de confirmación.

#### Día 58 · D4.5 · Recepción de webhooks y registro de entregas
- **Alcance mínimo:** `framework.webhook.routing.stripe` → parser decorado que registra toda entrega (aceptada o rechazada) antes de delegar; webhooks declarados en `Stripe.yaml`.
- **Dónde:** `config/packages/webhook.yaml`, `config/routes/webhook.yaml`, `src/Payments/Infrastructure/Webhook/RecordingRequestParser.php`, `src/Payments/Infrastructure/Integrations/Stripe/Webhook/`, `tests/Payments/`.
- **Verificación:**
  - [ ] `WebTestCase` con firmas generadas en el test: válida → 202 y entrega `queued`; inválida → 406 y entrega `rejected` con motivo.
  - [ ] **Cumple su necesidad si:** toda entrega, incluso las malas, queda visible para el panel.

#### Día 59 · D4.6 · Messenger y RabbitMQ
- **Alcance mínimo:** transporte AMQP, `ConsumeRemoteEventMessage` enrutado a `async`, servicios `rabbitmq` y `worker` en Compose, transporte `in-memory` en tests.
- **Dónde:** `config/packages/messenger.yaml`, `config/packages/test/messenger.yaml`, `compose.yaml`, `tests/Payments/`.
- **Verificación:**
  - [ ] Test: tras un webhook válido, el transporte `async` contiene exactamente un `ConsumeRemoteEventMessage`.
  - [ ] `docker compose exec rabbitmq rabbitmq-diagnostics check_running` OK; el puerto de gestión no está publicado.
  - [ ] **Cumple su necesidad si:** la recepción responde rápido y el trabajo real va a la cola.

#### Día 60 · D4.7 · Consumer idempotente
- **Alcance mínimo:** consumer de eventos de Stripe que deduplica por id, actualiza `Rental` y marca la entrega.
- **Dónde:** `src/Payments/Application/StripeRemoteEventConsumer.php`, `src/Payments/Domain/`, `tests/Payments/`.
- **Verificación:**
  - [ ] Tests: `succeeded` → `rented`; `payment_failed` → `failed`; mismo evento dos veces → un único cambio y entrega `duplicate`; `payment_failed` tras `rented` → ignorado.
  - [ ] **Cumple su necesidad si:** entregas repetidas o desordenadas no corrompen el estado.

#### Día 61 · B4.9 · Generador `make:integration --webhook`
- **Alcance mínimo:** generación de mapper, evento y entrada YAML de webhook.
- **Dónde:** `src/Bundle/Command/MakeIntegrationCommand.php`, `src/Bundle/Generator/`, `tests/Bundle/`.
- **Verificación:**
  - [ ] Tests del generador y del comando; archivos generados pasan `php -l` en el job de PHP 8.2.
  - [ ] **Cumple su necesidad si:** un webhook se crea tan rápido como una acción.

#### Día 62 · B4.10 · Webhooks: docs, seguridad y release v4.4.0
- **Alcance mínimo:** `docs/webhooks.md`, sección "Security model", Deptrac actualizado, release **v4.4.0**, demo con `^4.4`.
- **Dónde:** `docs/`, `ARCHITECTURE.md`, `deptrac.yaml`, `CHANGELOG.md`; demo `composer.json`.
- **Verificación:**
  - [ ] DoD de release completa; job de contrato verde.
  - [ ] **Cumple su necesidad si:** v4.4.0 está publicada y la demo recibe webhooks con ella.

#### Día 63 · D4.8 · Estado del alquiler en vivo
- **Alcance mínimo:** `GET /{_locale}/rentals/{id}/status` JSON; la ficha sondea cada segundo (máx. 30 s) y muestra Processing → Rented/Failed.
- **Dónde:** `src/Rental/UI/RentalStatusController.php`, `assets/rental-status.js`, `templates/store/movie.html.twig`, `tests/Rental/`.
- **Verificación:**
  - [ ] `WebTestCase` de los tres estados y de id inexistente (404).
  - [ ] **Cumple su necesidad si:** el webhook asíncrono tiene un efecto visible para el visitante.

#### Día 64 · D4.9 · Panel: pagos
- **Alcance mínimo:** pestaña "Payments" con pagos de Stripe (caché 10 s), EN/ES, enlace desde el tour.
- **Dónde:** `src/Payments/UI/PanelController.php`, `templates/panel/payments.html.twig`, traducciones, `tests/Payments/`.
- **Verificación:**
  - [ ] `WebTestCase` con Gateway simulado: lista, vacío, error de Stripe con mensaje amable.
  - [ ] **Cumple su necesidad si:** los pagos de prueba se ven sin entrar en Stripe.

#### Día 65 · D4.10 · Panel: bandeja de webhooks
- **Alcance mínimo:** lista y detalle de entregas (estado, motivo, cabecera `Stripe-Signature`, payload crudo, evento mapeado), escapado seguro.
- **Dónde:** `src/Payments/UI/WebhookInboxController.php`, `templates/panel/webhooks*.html.twig`, `tests/Payments/`.
- **Verificación:**
  - [ ] Test XSS: `metadata` con `<script>` se muestra escapado.
  - [ ] **Cumple su necesidad si:** se ve el camino completo de cada entrega, bien o mal.

#### Día 66 · D4.11 · Herramientas de reenvío y simulación
- **Alcance mínimo:** reenviar la entrega original (duplicado o timestamp caducado según antigüedad), simular timestamp caducado, firma inválida y cabecera ausente, con rate limit y etiquetas "simulated".
- **Dónde:** `src/Payments/Application/WebhookReplayer.php`, `src/Payments/UI/WebhookToolsController.php`, `tests/Payments/`.
- **Verificación:**
  - [ ] Tests con reloj inyectable: reenvío < 300 s → `duplicate`; > 300 s → `rejected: timestamp_out_of_tolerance`; firma inválida → `rejected: signature_invalid`.
  - [ ] **Cumple su necesidad si:** las protecciones se demuestran con datos reales y a demanda.

#### Día 67 · D4.12 · Reinicio nocturno
- **Alcance mínimo:** comando `app:demo:reset` y tarea programada con Symfony Scheduler.
- **Dónde:** `src/Shared/UI/Console/ResetDemoCommand.php`, `src/Shared/Scheduler/DemoSchedule.php`, `compose.yaml` (consumo de `scheduler_default`), `tests/Shared/`.
- **Verificación:**
  - [ ] Test del comando (vacía tablas, conserva esquema) y del schedule (expresión cron esperada).
  - [ ] **Cumple su necesidad si:** la demo amanece limpia cada día.

#### Día 68 · D4.13 · Pasos 5 y 6 del tour
- **Alcance mínimo:** "Renting a movie" (salida a Stripe) y "Payment confirmation" (webhooks, cola, idempotencia), EN/ES, enlaces al panel.
- **Dónde:** `config/tour.yaml`, `translations/tour.{en,es}.yaml`, marcadores en `src/Payments/`, `config/`.
- **Verificación:**
  - [ ] `TourSnippetsResolveTest` verde; recorrido manual completo con tarjeta que funciona y que falla.
  - [ ] **Cumple su necesidad si:** la historia de la tienda se cierra de principio a fin.

#### Día 69 · D4.14 · Stripe real en local
- **Alcance mínimo:** perfil de Compose `stripe-local` con Stripe CLI reenviando a la app; documentación.
- **Dónde:** `compose.yaml` (perfil), `docs/STRIPE-LOCAL.md`, `Makefile` (`stripe-local`).
- **Verificación:**
  - [ ] `make stripe-local` + `stripe trigger payment_intent.succeeded` → entrega `processed` en el panel local.
  - [ ] **Cumple su necesidad si:** un tech lead puede reproducir el flujo real en su máquina.

#### Día 70 · D4.15 · Despliegue de la fase 4
- **Alcance mínimo:** RabbitMQ, worker y scheduler en producción; volúmenes; migraciones en el despliegue; endpoint registrado en el Dashboard de Stripe; secreto de firma en el VPS.
- **Dónde:** `compose.prod.yaml`, `.github/workflows/deploy.yml`, Dashboard de Stripe, VPS.
- **Verificación:**
  - [ ] Alquiler real en producción → `rented` en ≤ 10 s y entrega `processed` en el panel.
  - [ ] Uso de memoria del VPS tras 24 h anotado en `docs/ops/RUNBOOK.md` (margen ≥ 30 %).
  - [ ] **Cumple su necesidad si:** el flujo bidireccional funciona online con Stripe de verdad.

#### Día 71 · D4.16 · Revisión de seguridad de la demo pública
- **Alcance mínimo:** checklist de seguridad de DEMO.md D4.16 completa y correcciones.
- **Dónde:** ambos entornos de la demo.
- **Verificación:**
  - [ ] Checklist completa con evidencia.
  - [ ] **Cumple su necesidad si:** una demo con tu nombre no expone nada ni se puede usar para abusar de terceros.

#### Día 72 · B4.11 · Landing: "Both directions, one pattern"
- **Alcance mínimo:** sección con YAML de salida y entrada lado a lado (copiados de la demo), diagrama con la frontera bundle/aplicación, enlace al paso 6.
- **Dónde:** `landing/src/html.js`, `landing/src/i18n/*.js`, `landing/src/css.js`.
- **Verificación:**
  - [ ] `node --test` verde; comparación textual de los snippets con los archivos de la demo (script `landing/test/snippets-match.test.js`).
  - [ ] **Cumple su necesidad si:** la landing cuenta la funcionalidad más diferenciadora con código real.

#### Día 73 · D4.17 · Revisión en frío y demo v2.0
- **Alcance mínimo:** recorrido completo, correcciones, tag `v2.0.0`, `ROADMAP.md` actualizado.
- **Dónde:** ambos repos.
- **Verificación:** checklist de DEMO.md D2.21 repetida con los pasos 5-6.

#### Día 74 · Colchón de la fase 4
- **Alcance mínimo y verificación:** igual que el día 45.

---

### FASE 5 · Calidad de diseño visible

#### Día 75 · B5.1 · Extensión PHPStan: spike de inferencia
- **Alcance mínimo:** prototipo desechable de `DynamicMethodReturnTypeExtension` para `IntegrationEngine::send()`; ADR go/no-go (timebox 2 h).
- **Dónde:** rama `spike/phpstan-inference` (no se fusiona), `docs/adr/00NN-phpstan-extension.md`.
- **Verificación:** ADR con decisión y evidencia (qué casos infiere y cuáles no).

#### Día 76 · B5.2 · Regla: el mapper apunta a una acción válida
- **Dónde:** `src/PHPStan/Rules/MapperActionRule.php`, `tests/PHPStan/Rules/`.
- **Alcance mínimo y verificación:** ver BUNDLE.md B5.2; `RuleTestCase` con casos válidos e inválidos; **cumple su necesidad si** un mapper mal enlazado falla en análisis estático.

#### Día 77 · B5.3 · Regla: el facade no devuelve arrays sin tipar
- **Dónde:** `src/PHPStan/Rules/IntegrationFacadeReturnTypeRule.php`, `tests/PHPStan/Rules/`.
- **Alcance mínimo y verificación:** ver BUNDLE.md B5.3; **cumple su necesidad si** ningún array crudo puede salir de un facade sin que PHPStan lo marque.

#### Día 78 · B5.4 · Regla: respuestas `final readonly`
- **Dónde:** `src/PHPStan/Rules/ResponseClassModifiersRule.php`, `tests/PHPStan/Rules/`.
- **Alcance mínimo y verificación:** ver BUNDLE.md B5.4; **cumple su necesidad si** una respuesta mutable falla en análisis estático.

#### Día 79 · B5.5 · Inferencia (si hubo "go") y publicación de la extensión
- **Alcance mínimo:** extensión de inferencia con tests (o nada, si el ADR dijo "no-go"), `extension.neon`, `extra.phpstan.includes`, documentación.
- **Dónde:** `src/PHPStan/`, `extension.neon`, `composer.json`, `docs/phpstan.md`.
- **Verificación:** en un proyecto vacío con `phpstan/extension-installer`, la extensión se carga sola (salida de `phpstan diagnose` en el PR).

#### Día 80 · D5.1 · Extensión PHPStan aplicada a la demo
- **Alcance mínimo:** demo usando la extensión desde el commit del bundle; `\assert()` eliminados donde la inferencia funciona.
- **Dónde:** `integrationEngine-demo/composer.local.json`, `src/**/Integrations/**`.
- **Verificación:** `make ci` verde; `grep -rn "assert(\$response instanceof" src/` → 0 (si hubo "go").

#### Día 81 · Release v4.5.0 y demo
- **Alcance mínimo:** DoD de release; demo con `^4.5`; despliegue.
- **Verificación:** DoD 1.4 completa.

#### Día 82 · B5.6 · SSRF: configuración
- **Dónde:** `src/Bundle/DependencyInjection/Configuration.php`, `tests/Bundle/DependencyInjection/ConfigurationTest.php`.
- **Alcance mínimo y verificación:** nodos `allowed_hosts` y `block_private_networks` validados (ver BUNDLE.md B5.6); **cumple su necesidad si** la política de hosts es declarativa.

#### Día 83 · B5.7 · SSRF: aplicación
- **Alcance mínimo:** comprobación de host antes de despachar (incluidas URLs de `connection_resolver`), `NoPrivateNetworkHttpClient`, `DisallowedHostException`, `SECURITY.md`, ADR.
- **Dónde:** `src/Core/Security/HostPolicy.php`, `src/Core/IntegrationEngine.php`, `src/Bundle/DependencyInjection/Compiler/IntegrationCompilerPass.php`, `SECURITY.md`, `tests/`.
- **Verificación:** tests de host permitido, no permitido, IP privada, `169.254.169.254`, IPv6 local y redirección a IP privada; **cumple su necesidad si** un tenant no puede apuntar el engine a la red interna.

#### Día 84 · D5.2 · Paso 7 del tour: "Partner stores"
- **Alcance mínimo:** tiendas asociadas con base URL propia: una válida (`supplier`), una no permitida y una a IP de metadatos; rechazo visible antes de salir a red.
- **Dónde:** `src/Partners/`, `config/tour.yaml`, traducciones, `tests/Partners/`.
- **Verificación:** tests que confirman que no se realiza ninguna llamada HTTP en los casos rechazados (`MockHttpClient::getRequestsCount() === 0`).

#### Día 85 · Release v4.6.0 y despliegue
- **Verificación:** DoD 1.4; paso 7 funcionando en producción; comprobación desde el VPS de que `169.254.169.254` no se consulta (logs).

#### Día 86 · B5.8 · Eventos del ciclo de vida: core
- **Dónde:** `src/Core/Event/`, `src/Core/IntegrationEngine.php`, `src/Core/Auth/DynamicAuthHandler.php`, `composer.json` (`psr/event-dispatcher`), `tests/Core/Event/`.
- **Alcance mínimo y verificación:** ver BUNDLE.md B5.8; **cumple su necesidad si** se puede observar el engine sin escribir middlewares.

#### Día 87 · B5.9 · Eventos: webhooks, garantía sin secretos, docs y ADR
- **Dónde:** `src/Infrastructure/Webhook/`, `tests/Core/Event/EventsContainNoSecretsTest.php`, `docs/events.md`, `docs/adr/`.
- **Verificación:** test que recorre todos los eventos emitidos en los tests de integración y comprueba que ninguna propiedad contiene tokens, secretos ni cabeceras de autorización.

#### Día 88 · D5.3 · Panel "Engine events" en la demo
- **Alcance mínimo:** listener de eventos que sustituye la fuente de datos de la traza del tour; pestaña del panel con los últimos eventos.
- **Dónde:** `src/Shared/Observability/`, `templates/panel/events.html.twig`, `tests/Shared/`.
- **Verificación:** la traza del tour y el panel muestran reintentos (paso 4) y rechazos de webhooks (paso 6).

#### Día 89 · Release v4.7.0, demo v3.0 y despliegue
- **Verificación:** DoD 1.4; tag demo `v3.0.0`; todo el tour funcionando en producción.

#### Día 90 · B5.10 + D5.4 · Cierre
- **Alcance mínimo:** README con "Reviewing this project? Start here", `ROADMAP.md` y landing actualizados, revisión en frío final con una persona ajena.
- **Dónde:** `integrationEngine/README.md`, `ROADMAP.md`, `landing/`, `integrationEngine-demo/README.md`.
- **Verificación:** checklist final de DEMO.md D5.4 completa.

---

## 4 · Backlog (sin fecha, fuera de las 90 sesiones)

| Idea | Condición para entrar |
|---|---|
| **Bundle no se auto-registra con Symfony Flex** (descubierto en el Día 03, ver nota ahí): `composer require carlosgude/integration-engine` no toca `config/bundles.php` — la heurística de detección de bundles de Flex no encuentra la clase porque vive en `src/Bundle/IntegrationEngineBundle.php`, no en `src/IntegrationEngineBundle.php`. Afecta a instalaciones reales, no solo a CI. | Antes de la release v4.1.1 (Día 10) si es rápido de verificar con una opción concreta; si no, primer día disponible de la fase 1 restante |
| Letterboxd (OAuth2 client credentials) en la ficha de película | Solo si Letterboxd aprueba el acceso a la API |
| Proveedor OAuth2 propio para enseñar refresco y reintento tras 401 | Solo si una entrevista lo pide o sobra tiempo |
| Migración legacy paso a paso (strangler fig con tags) | Tras la fase 5 |
| Artículo técnico (webhooks con el mismo patrón que las salidas) | Tras la fase 4 |
| Stripe Checkout con tarjeta 4242 introducida por el visitante | Tras la fase 4, si los botones resultan poco tangibles |
