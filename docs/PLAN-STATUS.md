# Plan final de ejecución del bundle

Auditoría: **2026-09-22**. Base: commit `6f3107cd98daef8114d05dab9e56dd4979347c9d`.
Este documento reconcilia las tareas B1.1–B5.10 facilitadas por el usuario con
el repositorio actual y fija el orden para el trabajo restante. No ejecuta releases,
despliegues ni cambios de contratos. Los identificadores B se conservan para trazabilidad.

## 1. Punto de partida comprobado

- El último tag local es **v7.0.2**, no v4.1.0. Existe preparación de v7.1.0 en
  [release-7.1](release-7.1.md); el changelog mantiene Unreleased.
- Composer sigue declarando PHP >=8.2 y Symfony ^6.4|^7.0|^8.0.
- No se ha encontrado PLAN.md en el checkout. Sus §1.1, §1.4 y §4 no están
  disponibles: no se declara cumplido su contenido desconocido. Las puertas
  operativas de este documento se basan en los requisitos recibidos y en el repo.
- Había un cambio previo en README (enlace al repositorio demo) y un directorio
  .codex sin seguimiento. Se conservan; no forman parte de la implementación de esta auditoría.
- Se mantienen Core, Infrastructure y Bundle. No hay configuración ni dependencia
  Deptrac, ni extensión propia PHPStan, ni HostPolicy.

### Evidencia ejecutada y límites

| Comprobación | Resultado |
|---|---|
| `make qa` | Verde: estilo, PHPStan max sobre src/tests y **778 tests, 2.155 aserciones**. PHP local 8.5.6. |
| `node --test` en landing | **10/10** verdes, sin saltos. Las guardas son incompletas respecto a B1.9. |
| [CI del commit auditado](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588854) | Finalizado con éxito; workflow con matriz, generador PHP 8.2 y mutación. |
| [Contrato demo del commit auditado](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588893) | Finalizado con éxito. La modificación PCOV ya tiene evidencia remota. |
| Informe local Infection existente | 1.093 mutantes, 1.087 muertos por tests, 1 error, 5 escapados, 0 no cubiertos: MSI y MSI cubierto **99,54 %** según ese informe. |

No se ha vuelto a ejecutar Infection localmente durante esta auditoría: el informe
existente no se presenta como una nueva medición. El CI verde aporta evidencia
de las puertas remotas del commit, no de futuros cambios. El primer `make qa`
falló por el socket del sandbox; repetido con permiso, pasó.

No se han comprobado en esta revisión Packagist, releases publicadas, correo
recibido, configuración Cloudflare, capturas EN/ES ni disponibilidad funcional
de la demo pública. Tampoco se reconstruye evidencia histórica roja de TDD ni
pruebas negativas de PR a partir de que los tests actuales estén verdes.

## 2. Estado frente a las tareas originales

**Cumple local** significa que el comportamiento o artefacto está comprobado en
el checkout; no certifica publicación. **Parcial** conserva criterios abiertos.
**Divergente** significa que hay una solución publicada con otro contrato: debe
reconciliarse antes de aplicar literalmente el diseño de v4. **Pendiente** carece
de implementación. Las releases v4.2–v4.7 propuestas no se reutilizarán para trabajo nuevo.

### Fase 1

| Tarea | Estado | Condiciones satisfechas y trabajo restante |
|---|---|---|
| B1.1 Calidad | Parcial | QualityConfigTest verde; umbrales 85/95 centralizados, rutas reales, logs en var/infection, PHPStan max src/tests, pre-commit alias, enlace desde CONTRIBUTING. Falta medir la alternativa Bundle/Resources y justificar con datos la exclusión completa; revisar ignores no universalmente equivalentes y el MSI de líneas omitidas. El estándar exacto del PLAN externo no es comprobable. |
| B1.2 PHP 8.2 | Cumple local; evidencia histórica pendiente | Generador usa `public const NAME`; tests del renderer y NoTypedConstantsInDocsTest pasan. CI del generador PHP 8.2 verde. No se acredita aquí que los tres tests fallaran antes. |
| B1.3 Matriz | Parcial | Matriz 8.2/8.3/8.4 × Symfony × lowest/stable y smoke de generador presentes, CI verde. Falta evidencia de prueba negativa y conservar referencias verificadas a requisitos de Symfony. El smoke aún no genera webhooks. |
| B1.4 Imports | Parcial | DocumentationImportsTest en suite normal, verde. Mensaje actual incluye archivo y clase, pero no línea. Falta evidencia roja histórica y revisión de llamadas sin import; el test no valida métodos. |
| B1.5 Enlaces/guía | Divergente | Tests de enlaces/imports verdes; guía duplicada de raíz ausente. La guía vigente es CLAUDE.md, no agent/. Backticks se resuelven desde raíz, frente al directorio del documento solicitado. Revisar y documentar esta convención, sin recrear una segunda guía. |
| B1.6 Deptrac | Pendiente | No hay paquete, configuración, target ni job. No hay garantía automatizada de capas. |
| B1.7 Versiones | Parcial | Changelog desde v2, guías de migración y política SemVer presentes. Falta reconciliar política tras v7, revisar resumen 1.x, enlaces de majors y evidencia de releases GitHub. CONTRIBUTING no exige todos los puntos originales de ADR y consumidor demo. |
| B1.8 ADR | Parcial | ADR 0001–0008 e índice existen; ARCHITECTURE enlaza índice. Falta completar visibilidad desde README y comprobar la garantía de ausencia de secretos con la prueba explícita requerida. ADR posteriores contienen decisiones distintas del plan. |
| B1.9 Credibilidad landing | Parcial, incumplimientos confirmados | Paridad y correos hi/hola presentes. Persisten EngineRequest::create en HTML, 4.2s/0.8s y equivalentes ES, CTA plural y meta con SAP/Salesforce. Guardas permiten el create resaltado y no cubren todo el listado. No hay job landing. Correo, capturas y routing sin verificar. |
| B1.10 Estado/release | Parcial | README muestra v7.0.2 y roadmap público; tag local v4.1.1 y changelog existen. No hay sección id=roadmap en landing. Packagist/despliegue sin verificar. Reemplazar el hito histórico por cierre de la versión actual. |

### Fases 2 y 3

| Tarea | Estado | Condiciones satisfechas y trabajo restante |
|---|---|---|
| B2.1 Demo visible | Parcial | Cambio previo del README apunta al repo nuevo. Falta enlace demo pública en landing, navegación/hero, guardas contra repo antiguo y sincronización del roadmap. Comprobar demo antes de anunciarla entregada. |
| B2.2 Contrato demo | Parcial | Workflow instala checkout mediante path y ejecuta tests/stan; **verde en HEAD**. Faltan prueba negativa y regla explícita en CONTRIBUTING. No requiere repetir la reparación PCOV. |
| B3.1 Contrato form | Pendiente según diseño original | Request tiene cuatro argumentos y withHeader, pero no BodyEncoding ni FormEncodedBodyInterface. Form-encoded existe mediante adaptador separado. Decidir compatibilidad antes de duplicar mecanismos. |
| B3.2 REST form | Divergente | FormEncodedClientAdapter probado, registrado, con URL dinámica/status. REST no incorpora el marcador ni batch mixto JSON/form del diseño solicitado; falta contrato de middleware que preserve codificación. |
| B3.3 GraphQL/docs form | Parcial/divergente | Hay tests GraphQL y documentación de clientes. No está entregado el conjunto marcador + ADR form + consumo demo previsto. Publicar bajo versión actual tras resolver B3.1–2, no v4.2. |
| B3.4 Config retries | Pendiente | Configuration no ofrece retry, timeout ni max_duration propuestos. |
| B3.5 Transporte retries | Pendiente | No RetryStrategyFactory ni cableado RetryableHttpClient. Utilidades Core/Resilience no equivalen a retries automáticos. |
| B3.6 Conducta retries | Pendiente | Tests de ErrorClassifier/ExponentialBackoffPolicy pasan, pero no prueban Retry-After, transporte ni los siete escenarios del plan. |
| B3.7 Batch/timeout/docs | Pendiente | Faltan timeout por acción y contrato de concurrencia durante retries. Existe documentación de utilidades; no acredita resiliencia declarativa. |

### Fase 4

| Tarea | Estado | Condiciones satisfechas y trabajo restante |
|---|---|---|
| B4.1 Spike/ADR | Parcial/divergente | Hay spike archivado y ADR webhooks/idempotencia. Actualizar las siete respuestas con tags exactos y reconciliar decisiones posteriores, incluido ADR 0014. Resolver discrepancias antes del nuevo parser. |
| B4.2 YAML | Divergente | WebhookDefinition, SignatureConfig y tests YAML existen con definiciones por evento. No constituyen el contrato único type_field/id_field/unknown_events ni la API nullable propuesta. |
| B4.3 HMAC | Parcial/divergente | Verificador y tests existen. Contrato actual devuelve bool y recibe firma/secreto; no usa headers normalizados ni excepción tipada con motivos. |
| B4.4 Timestamped HMAC | Parcial | ClockInterface, múltiples v1, límite inclusivo y tests de cuerpo modificado presentes. Falta declarar psr/clock directamente en require y cerrar prueba raw-body/formatos y motivos del contrato original; no depender de paquetes transitivos. |
| B4.5 Mapper/evento | Divergente | AbstractWebhookMapper y eventos existen y tienen tests, pero el contrato difiere del map estático/eventType solicitado. Verificar roundtrip y validación YAML contra el diseño que se conserve. |
| B4.6 Parser | Divergente | Parser abstracto verifica firma antes del JSON y devuelve RemoteEvent; tests verdes. No hay MappedRemoteEvent/DotPath ni selección genérica YAML; permite id vacío y no exige Content-Type JSON. No marcar esos criterios como hechos. |
| B4.7 DI | Pendiente según diseño original | No se acredita registro del parser genérico por integración ni checker inyectable para dependencia ausente. Existe infraestructura de webhooks distinta. |
| B4.8 Motivos | Pendiente | No enum de motivos ni cadena de excepciones estructurada del plan; mensajes actuales no son ese contrato. |
| B4.9 Generador | Divergente | Existe make:webhook y generador probado, no make:integration --webhook. Falta smoke PHP 8.2 de webhooks, evidencia de no sobrescritura/YAML incremental según contrato definitivo. |
| B4.10 Docs/release | Parcial/divergente | WEBHOOK, ARCHITECTURE y ADR describen la implementación actual. Resolver dependencia opcional, límites de idempotencia y contratos antes de reescribir guía/agentes o anunciar cumplimiento. |
| B4.11 Landing bilateral | Pendiente | No sección id=both-directions, snippets-match ni directorio snippets. Stripe ya aparece sin la condición de cierre pedida. Requiere demo real, contrato y snippets verificados. |

### Fase 5

| Tarea | Estado | Condiciones satisfechas y trabajo restante |
|---|---|---|
| B5.1 Spike inferencia | Pendiente | No decisión go/no-go documentada ni extensión. Comprobar API real de acciones/mappers antes de decidir. |
| B5.2 Regla mapper | Pendiente | Propuesta acotada en next-features; no src/PHPStan ni fixtures RuleTestCase. Resolver tratamiento de declaraciones dinámicas y distribución. |
| B5.3 Regla facade | Pendiente | Sin implementación. Debe ser optativa y admitir arrays de objetos tipados; no prohibir arrays legítimos del engine. |
| B5.4 Regla readonly | Pendiente | Sin implementación. Inventariar DTO existentes antes de activar; una regla opcional no puede romper instalación de producción. |
| B5.5 Publicar extensión | Pendiente | Sin extension.neon ni instalación de prueba. Inferencia depende de B5.1; las reglas pueden publicarse sin ella. |
| B5.6 SSRF config | Pendiente | Sin allowed_hosts/block_private_networks. Existe propuesta diferente de selección http_client_service que no satisface por sí sola allowlist del engine. |
| B5.7 SSRF ejecución | Pendiente | Sin HostPolicy/validación URL final ni NoPrivateNetworkHttpClient cableado ni SECURITY. Verificar redirecciones, resolución y matriz; diferenciar protección de host y red. |
| B5.8 Lifecycle | Parcial/divergente | Eventos ActionStarted/ActionCompleted/ActionFailed, timings, dispatcher propio y puente Symfony implementados. Faltan equivalencia por elemento batch, TokenRefreshed y contrato PSR solicitado. ActionFailed transporta Throwable y acción: no garantiza ausencia de secretos. |
| B5.9 Eventos seguros/webhooks | Pendiente/parcial | Hay docs de lifecycle/observabilidad, pero no garantía recursiva global sin secretos ni pareja WebhookReceived/WebhookRejected pedida. Definir política de fallos de listeners y resultados únicos antes de métricas. |
| B5.10 Cierre | Parcial | Roadmap y métricas de calidad existen. Falta ruta de revisión de diez minutos, enlaces a tour verificables, secciones landing y cierre integrado de funciones pendientes. |

## 3. Decisiones que condicionan la ejecución

1. **Versiones:** continuar desde v7; no recrear releases históricas ni prometer
   estabilidad desde v4. La próxima publicación candidata es v7.1.0, con alcance
   de [su preparación](release-7.1.md). Features posteriores necesitan sus propios
   incrementos SemVer; romper APIs públicas exige ADR y migración.
2. **Formularios:** preservar client: form_encoded existente. Elegir entre añadir
   soporte REST con marcador de manera compatible o sustituir formalmente el
   criterio original por el adaptador. El segundo camino es cambio de alcance,
   no tarea completada. Por defecto, el objetivo original sigue abierto.
3. **Webhooks:** conservar contratos v7 mientras se diseña la API genérica aditiva.
   Idempotencia/dispatch actuales y ADR posteriores no se eliminarán por copiar
   el plan antiguo. B4.1 debe resolver explícitamente este conflicto antes de B4.2–10.
4. **Eventos:** definir eventos observables sin referencias a payload, acción con
   credenciales o excepciones crudas; evaluar migración del contrato existente.
   No basta sanear un mensaje manteniendo Throwable en el objeto serializado.
5. **SSRF:** selección de transporte puede ser un primer incremento útil, pero
   B5.6–7 no se cierran sin allowlist final y pruebas de red especificadas, salvo
   sustitución explícita del alcance. Custom clients y redes internas tienen
   límites documentados.
6. **PHPStan:** resolver reglas optativas, declaraciones dinámicas y carga sin
   PHPStan en producción antes de implementar. No ejecutar métodos de la app
   durante análisis. El spike decide inferencia, no bloquea las reglas independientes.

Estas decisiones se documentan mediante ADR con alternativas, compatibilidad,
tests y consumidor. Los números 0009–0014 ya están ocupados: asignar números
nuevos al integrar, sin sobrescribir decisiones históricas.

## 4. Orden final y frentes paralelizables

Cada fila es una unidad de entrega revisable. Solo se inicia después de sus
dependencias. Paralelizar implica ramas/worktrees aislados y revisión de integración;
no edición simultánea de composer.lock, Configuration, compiler pass o workflows.

| Orden / bloque | Trabajo | Dependencias | Puede avanzar en paralelo con | Condición de salida |
|---|---|---|---|---|
| 0 · Base | Actualizar mantenimiento/release/QUALITY con CI y contrato ya verdes; revisar contradicción PHP 8.4 mínimo en changelog frente a Composer 8.2. | Auditoría actual | 1A, 1B, 1C | Estado trazable y ninguna promesa histórica falsa. |
| 1A · Calidad | Cerrar B1.1, introducir B1.6, mensajes con línea de B1.4, convención documental B1.5; evidencia negativa de matriz y contrato B1.3/B2.2. | 0 para integrar | 1B, 1C | Deptrac 0 violaciones, calidad sin umbrales rebajados, pruebas negativas documentadas. |
| 1B · Landing y demo | B1.9, B1.10 visual, B2.1; guardas completas, links, roadmap EN/ES y job landing. | Ninguna para correcciones locales | 1A, 1C | Tests realmente detectan incumplimientos; capturas y enlaces; routing/correos comprobados antes del cierre externo. |
| 1C · Diseño compatible | ADR form, spike webhooks B4.1, contrato eventos/secretos, spike B5.1 y decisiones distribución/SSRF. | Inventario de APIs actuales | 1A, 1B | Decisiones por frente, sin romper API publicada silenciosamente. |
| R0 · Publicar mantenimiento | Cerrar preparación v7.1.0 usando alcance documentado, compatibilidad y consumidor. | 0 + gates del commit de release | Ramas de features aisladas | CI/contrato exactos, changelog, tag/release, Packagist verificado. No arrastrar todas las features a esta release. |
| 2A · Form | B3.1 → B3.2 → B3.3, con migración/adición según ADR. | 1C-form + 1A | 2B-core, 2C, 2D | JSON sin regresión, form/middleware/batch verificados, demo consumiendo. |
| 2B · Webhooks | B4.2 → B4.5; B4.3 y B4.4 en paralelo tras contrato firma; unir en B4.6 → B4.7 → B4.8. | 1C-webhooks + 1A | 2A, 2C, 2D; coordinar DI | Firma antes de JSON, payload/tipo/id y motivos probados, opcionalidad de dependencias comprobada. |
| 2C · PHPStan | B5.2, B5.3 y B5.4 en paralelo tras contrato común → B5.5; inferencia solo si B5.1 go. | 1C-PHPStan + 1A | 2A, 2B, 2D | RuleTestCase, consumo demo, instalación vacía y producción sin PHPStan. |
| 2D · Eventos | B5.8 core/batch/token → B5.9 secretos/ADR/docs; parte webhook espera 2B. | 1C-eventos + 1A | 2B parser y 2C | Resultado por operación/clave definido, observadores no duplican resultados, prueba recursiva sin secretos. |
| 3A · Transporte/SSRF | Selección transporte si se adopta → B5.6 → B5.7. | 1C-SSRF + 2A integrado | 3B diseño/tests unitarios, 3C, 2C | Tests de URL dinámica/token/batch, IP/redirección/resolución, límites documentados. |
| 3B · Resiliencia | Preservar causa original de errores estado 0 → B3.4 → B3.5 → B3.6 → B3.7. | 1C + transporte acordado; integrar después de 3A | 3C, 2C, 2D | Retry-After sin sleep, métodos seguros, tope, batch y timeout por acción, demo consumiendo. |
| 3C · Experiencia webhooks | B4.9 → B4.10 → B4.11. | 2B; 2A y demo para snippets de salida; 1B | 3A, 3B | Smoke PHP 8.2, docs del contrato real, snippets literales demo y sección bilateral. |
| 4 · Cierre público | B1.7/B1.8 pendientes documentales + B5.10; actualizar Now/Next y guía revisión. | Frentes que se anuncien como entregados | Solo revisiones independientes | Calidad actual, tour real, README/landing sin claims pendientes, despliegue verificado. |

**Camino de dependencias principal:** calidad + decisiones → formularios/transporte
→ SSRF → resiliencia. Webhooks, PHPStan y eventos tienen caminos independientes;
convergen con demo/documentación/landing en sus entregas. No hay que esperar a
terminar PHPStan para publicar webhooks ni a acabar resiliencia para corregir la landing.

### Conflictos de integración que deben serializarse

- Composer, lock y workflows: un responsable integra dependencias y jobs; los
  demás frentes entregan sus cambios de configuración para integración secuencial.
- Configuration/IntegrationCompilerPass: integrar formulario, webhook, eventos,
  SSRF y retries uno a uno; ejecutar suite DI tras cada unión.
- REST/GraphQL/Request: cerrar codificación antes de agregar decoradores de
  transporte y timeouts; verificar orden de middlewares y fallback secuencial.
- IntegrationEngine: cambios lifecycle y validación host se integran uno detrás
  de otro con pruebas de batch, auth y connection resolver.
- README/changelog/roadmap/ADRs: actualizar con el comportamiento integrado;
  asignar versión y número ADR al final, no en varias ramas simultáneamente.
- Landing puede trabajar sin bundle, pero snippets bilateral/tour no se anuncian
  como válidos hasta comparar con el checkout de demo que use ese bundle.

## 5. Puertas de salida y dependencias externas

Para cada cambio de comportamiento: test rojo que demuestre el problema, cambio
mínimo, suite afectada y `make qa`; `make ci` antes de integrar/publicar. Añadir
Deptrac y tests landing a las puertas una vez introducidos. No bajar MSI ni usar
ignores/baselines para ocultar fallos. Revisar exclusiones existentes con evidencia.

La aceptación de cada bloque requiere:

1. Código y tests de comportamiento, incluidos límites y compatibilidad PHP 8.2.
2. Documentación, imports/enlaces y ADR cuando cambia una decisión pública.
3. CI matriz, mutation, arquitectura y contrato demo en el commit exacto candidato.
4. Evidencia negativa donde la tarea la solicita, sin publicar ramas deliberadamente
   rotas como releases. No afirmar TDD histórico si no se conserva evidencia.
5. Para features, consumidor demo y sus tests sin servicios externos; validar también
   los escenarios de integración pertinentes antes de anunciar el tour disponible.
6. Para release: SemVer revisado, changelog/upgrade, tag y release notes, Packagist.
   Para landing: tests, capturas EN/ES, despliegue y comprobación posterior.

Dependencias externas que no bloquean trabajo local independiente:

- Recuperar el PLAN externo para contrastar exactamente §1.1/§1.4 y backlog §4.
- Acceso Cloudflare/cuenta emisora para routing y prueba real de hi/hola.
- Repo demo y sus tareas D3/D4/D5: registrar commits y criterios concretos; el
  contrato actual verde no certifica funcionalidades futuras ni el tour.
- Verificar fuentes oficiales al ejecutar spikes Symfony/Stripe/Deptrac/PHPStan;
  esta auditoría de código no sustituye esa investigación de versiones.

### Seguimiento del bloque 0

- [x] Estado de mantenimiento, release, roadmap y QUALITY actualizado con enlaces
  al CI y contrato verdes del commit auditado.
- [x] Contradicción del changelog corregida: el mínimo declarado sigue siendo
  PHP 8.2; PHP 8.4 corresponde a los jobs dedicados de calidad y contrato demo.
- [x] README actualizado para retirar el claim obsoleto de MSI 100 % y enlazar
  las mediciones y evidencias actuales, conservando el cambio previo del enlace demo.
- [x] Publicación y validación del candidato final distinguidas de la validación
  ya completada del commit de implementación.

El **bloque 0 está completado**. La siguiente fase es **1A, 1B y 1C**, que pueden
avanzar en paralelo respetando las restricciones de integración anteriores.

### Seguimiento del bloque 1A · Calidad

La tabla de auditoría anterior conserva el estado inicial. Cierre posterior:

- [x] Corrección de arquitectura separada en PR #5, validada por matriz y demo,
  con APIs antiguas preservadas en una capa explícita de compatibilidad (ADR 0015).
- [x] Deptrac en make ci y CI, sin baseline: cero violaciones y dependencias sin clasificar.
- [x] Todos los mutadores predeterminados habilitados y todos los ignores retirados.
- [x] Código no cubierto incluido en la puerta habitual; umbrales 85/95 intactos.
  Medición local: MSI 96,00 %, cubierto 97,27 %; 796 tests en make ci.
- [x] Bundle revisado con todos los mutadores: excluyendo solo Resources da
  93,18 % cubierto. Se mantiene la exclusión completa permitida por B1.1.
- [x] Pruebas negativas de configuración, imports (archivo/línea/clase), capas
  prohibidas y dependencias sin clasificar.
- [x] Pruebas negativas remotas B1.3/B2.2: lint PHP 8.2 rechaza constante tipada;
  la demo detecta send() ausente. PR temporal #6 cerrado sin fusionar.
- [x] Convención de enlaces/backticks y guía canónica documentada en CONTRIBUTING.
- [ ] Confirmar el CI del commit final del cierre de calidad antes de integrarlo.

Evidencia, resultados y límites: [auditoría de calidad](quality-audit.md).

### Seguimiento del bloque 1B · Landing

- [x] Corregidos los dos constructores EngineRequest y los enlaces a documentos
  trasladados a docs; ejemplo breve de batch con GetMovieAction.
- [x] Retirados ejemplo de pagos no verificado, benchmarks inventados, claims de
  latencia/aceleración y cifras obsoletas de tests/MSI. CTA singular en EN/ES.
- [x] Roadmap Now/Next/Later antes del contacto, alineado con el roadmap público.
- [x] Código de la demo enlazado en hero y navegación de escritorio/móvil.
- [x] Tests reforzados: 11 fallos antes de corregir, 23 tests verdes después;
  job landing en CI con Node 22 y sin dependencias.
- [ ] Demo online: dominio previsto sin resolución DNS; se anuncia como próxima
  hasta comprobar una URL funcional. No marcar B2.1 como completado.
- [ ] Email Routing: MX de Cloudflare confirmados; reglas hi/hola y recepción
  de pruebas pendientes de acceso a la cuenta.
- [ ] Confirmar despliegue automático y CI después del push solicitado.

La publicación de mantenimiento puede cerrarse por separado de las nuevas
funcionalidades; cualquier desviación del alcance original queda explícita en ADR
y en esta matriz antes de marcar una tarea como completada.
