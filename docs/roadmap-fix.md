# Pendientes de mantenimiento

Estado revisado el 2026-09-22. Este documento sustituye el listado de v5.4.0;
la revisión antigua y sus anotaciones se conservan en
[el archivo histórico](./archived/roadmap-fix-v5.md).

## Completado para la próxima versión

- [x] Lotes GraphQL: concurrencia, aislamiento de fallos, claves y middleware secuencial.
- [x] Formularios: pruebas, registro del cliente, URL dinámica y estado HTTP.
- [x] Observabilidad: pruebas de registro, filtros y logging; reparación del generador.
- [x] Resiliencia: clasificación HTTP, límite de reintentos y protección de desbordamiento.
- [x] Inspección de integraciones y acciones mediante `debug:integration`.
- [x] Webhooks: firma antes del decode, JSON malformado, rechazo de listas/escalares
  y mapper incompatible sin ejecutar transformación ni listeners.
- [x] REST secuencial: un fallo HTTP o de middleware no cancela el resto del lote.
- [x] Autorización estática: rechazo de parámetros no string antes del transporte.
- [x] DI: error explícito si no existe la clase de un request middleware etiquetado.
- [x] Configurar PCOV en el contrato con la demo, cuyo `make test` solicita cobertura.
- [x] Confirmar el CI principal y el contrato con la demo en
  `6f3107cd98daef8114d05dab9e56dd4979347c9d`: ambos completados con éxito.
  Evidencia: [CI](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588854)
  y [contrato demo](https://github.com/CarlosGude/integrationEngine/actions/runs/35735588893).
- [x] Corregir los comandos de `CLAUDE.md` y retirar las contradicciones del roadmap.
- [x] Medir exclusión Bundle/Resources: MSI cubierto 94,68 %, inferior al 95 %;
  se conserva Bundle excluido con evidencia, sin bajar umbrales.
- [x] Retirar tres ignores de mutación con pruebas de timestamp y codificación CSV.
- [x] Añadir línea a los errores de imports documentales y verificar fallos
  deliberados de configuración/imports en copias aisladas.

## Pendientes reales

- [ ] Validar el commit final de release si incorpora cambios posteriores al
  commit comprobado; el CI verde anterior no valida esos cambios.
- [ ] Publicar la siguiente versión tras cerrar la preparación de release.
  Véase [la preparación de v7.1.0](./release-7.1.md).
- [ ] Conservar la causa original de `RequestResponseException` para distinguir
  errores de transporte de preparación local con estado 0, antes de ampliar retries.
- [ ] Revisar los huecos menores de cobertura que siguen siendo comportamientos
  públicos: validación/opciones CSV, errores del generador y getters de lifecycle.
  No perseguir 100 % de líneas añadiendo pruebas de implementación.
- [x] Confirmar que Infection omite código no cubierto por defecto. La medición
  con `--with-uncovered` muestra 15 mutantes sin cobertura y supera 85/95.
  Mantener la cobertura PHPUnit como medida independiente.
- [x] Corregir las dependencias de Symfony mediante el PR de arquitectura #5,
  con fachadas de compatibilidad y contrato propio de clasificación en Core.
- [x] Activar Deptrac en make ci y en un job específico; cero violaciones y cero
  dependencias sin clasificar, sin baseline ni reglas relajadas.
- [x] Retirar todos los ignores y habilitar todos los mutadores predeterminados;
  incluir código no cubierto en la puerta habitual. MSI 96,00 % / cubierto 97,27 %.
- [x] Pruebas negativas remotas de generador PHP 8.2 y contrato demo completadas;
  PR temporal #6 cerrado sin fusionar. Evidencia enlazada en la auditoría.

Resultados, pruebas negativas y límites: [auditoría de calidad](quality-audit.md).

Los mutantes supervivientes permanecen visibles y cuentan contra los umbrales.
La medición actual y el alcance están en [QUALITY.md](./advanced/QUALITY.md).
Los componentes de proveedores, DLQ y state machine eliminados en v6 no son tareas
pendientes del bundle.

## Funcionalidades propuestas, todavía sin implementar

El alcance de selección de transporte/SSRF, regla PHPStan de mapper y métricas
está definido en [next-features.md](./advanced/next-features.md). Esa propuesta
incluye límites y criterios de aceptación; no declara entregadas esas funciones.
