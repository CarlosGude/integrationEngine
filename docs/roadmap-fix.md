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
  La ejecución remota del workflow modificado todavía está pendiente.
- [x] Corregir los comandos de `CLAUDE.md` y retirar las contradicciones del roadmap.

## Pendientes reales

- [ ] Confirmar el CI principal y el contrato con la demo sobre el commit que
  incluya estos últimos cambios; no basta con el resultado del commit anterior.
- [ ] Publicar la siguiente versión cuando pase esa validación.
  Véase [la preparación de v7.1.0](./release-7.1.md).
- [ ] Conservar la causa original de `RequestResponseException` para distinguir
  errores de transporte de preparación local con estado 0, antes de ampliar retries.
- [ ] Revisar los huecos menores de cobertura que siguen siendo comportamientos
  públicos: validación/opciones CSV, errores del generador y getters de lifecycle.
  No perseguir 100 % de líneas añadiendo pruebas de implementación.
- [ ] Investigar y documentar por qué Infection omite determinadas líneas no
  cubiertas en esta configuración. Mantener la cobertura PHPUnit como medida
  independiente; un MSI alto no resuelve esta pregunta.

Los cinco mutantes supervivientes anteriores están razonados en
[QUALITY.md](./advanced/QUALITY.md). No requieren bajar umbrales ni añadir exclusiones.
Los componentes de proveedores, DLQ y state machine eliminados en v6 no son tareas
pendientes del bundle.

## Funcionalidades propuestas, todavía sin implementar

El alcance de selección de transporte/SSRF, regla PHPStan de mapper y métricas
está definido en [next-features.md](./advanced/next-features.md). Esa propuesta
incluye límites y criterios de aceptación; no declara entregadas esas funciones.
