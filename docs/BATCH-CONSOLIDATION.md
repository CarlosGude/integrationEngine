# Batch: respuesta consolidada — notas de diseño

> Estado: **pendiente de implementar**. Diseño discutido el 2026-06-12, tras
> implementar `sendMany()` / `sendManyOrFail()`. Se retoma en una sesión futura.

## El problema

`sendMany()` devuelve un array de `BatchResult`, uno por petición. Eso resuelve
el caso "quiero las N respuestas", pero el caso más común es otro: lanzas la
misma acción con N contextos (paginación, fan-out por ids) y lo que quieres al
final es **una** respuesta consolidada — una lista única — no N resultados que
recorrer a mano.

Dentro de "consolidada" hay en realidad dos casos distintos:

1. **Misma acción, N peticiones** (paginación, fan-out). La consolidación es
   mecánica — concatenar/mergear N respuestas del mismo tipo — y pertenece al
   engine. Es el caso común.
2. **Acciones mixtas** (empleado + contrato + nóminas → un DTO "ficha
   completa"). Eso es *composición*: según la filosofía del bundle, los DTOs
   representan la forma del API externo y los application services componen
   hacia dominio. Si el engine fabrica DTOs que no corresponden a ningún
   endpoint real, se difumina el invariante mapper↔action.

## Decisión: A, iterando hacia B. C descartada.

### A — `BatchResultSet` (primera iteración)

`sendMany()` pasa a devolver un objeto-colección en lugar del array desnudo
(viable sin BC break: el feature batch aún no está publicado).

API prevista:

- Iterable y con acceso por clave (las claves del caller se preservan).
- `responses()`, `errors()`, `hasFailures()`.
- `merge()` / `collect(callable)` para consolidar desde el caller.

**Restricción de diseño:** B será una capa encima de A, así que
`BatchResultSet` debe exponer lo que un futuro mapper agregado necesitará —
los resultados crudos keyed y la distinción éxito/fallo — sin asumir nada
sobre cómo se consolida. En A, `merge()`/`collect()` es del caller; en B ese
mismo punto de entrada se convierte en el mapper agregado declarado. Si A se
diseña así, B sale solo añadiendo, sin tocar lo hecho.

### B — `AbstractBatchMapper` (siguiente iteración)

Mapper agregado para el caso 1 (misma acción, N contextos): recibe los N
resultados y devuelve un único `ResponseInterface`. Simétrico al
`AbstractMapper` actual pero a nivel batch; el invariante puede mantenerse
porque todos los items son de la misma acción. Se invoca desde el result set
(`->mapWith(EmployeeListBatchMapper::class)`), **no** desde YAML.

**⚠️ Mapeo en dos etapas — documentar bien, no es intuitivo:**

Cada item del batch pasa primero por su mapper individual, como en cualquier
`send()`. Cuando el flujo llega al consolidador, **ya recibe DTOs tipados
(`ResponseInterface`), no arrays crudos**:

```
raw array (HTTP)
  → GetEmployeesMapper::map()        ← etapa 1: por item, invariante intacto
  → GetEmployeesResponse (DTO página)
  → EmployeeListBatchMapper::consolidate()   ← etapa 2: N DTOs → 1 DTO
  → EmployeeListResponse (DTO consolidado)
```

El batch mapper es una **segunda etapa de mapeo, no un reemplazo** de la
primera. `consolidate()` trabaja con `GetEmployeesResponse[]`, nunca con JSON
decodificado. Quien espere recibir arrays crudos en el consolidador (lo
intuitivo si vienes del `AbstractMapper`) se sorprenderá: la transformación
raw→DTO ya ocurrió, item a item.

Ejemplo de uso completo:

```php
final class EmployeeListBatchMapper extends AbstractBatchMapper
{
    public static function getAction(): string
    {
        return GetEmployeesAction::class; // invariante a nivel batch
    }

    protected static function consolidate(BatchResultSet $results): ResponseInterface
    {
        $employees = [];
        foreach ($results->responses() as $page) {
            /** @var GetEmployeesResponse $page */  // ← DTO, no array
            $employees = [...$employees, ...$page->employees()];
        }

        return new EmployeeListResponse($employees);
    }
}

$all = $engine->sendMany($requests)->mapWith(EmployeeListBatchMapper::class);
```

Implicaciones para A (restricciones concretas):

- `BatchResultSet` debe guardar **la clase de acción de cada item** además del
  resultado, para que `mapWith()` valide el invariante en runtime
  (`BatchMapperActionMismatchException` si algún item no pertenece a la acción
  declarada).
- B no añade entradas al YAML de la integración: el batch mapper se referencia
  en la llamada. El YAML sigue describiendo endpoints, no composiciones.
- La decisión abierta de fallos parciales aterriza exactamente en `mapWith()`
  (¿estricto por defecto, lanzando si `hasFailures()`?).

### C — acción compuesta en YAML: descartada

Los YAML se quedan cortos: para declarar "qué endpoint es" funcionan bien,
pero expresar composición (orden, dependencias entre respuestas, política de
fallos) en YAML acaba siendo un DSL a medias — eso es código. Además, la
composición de acciones mixtas pertenece a los application services, no al
engine.

### ¿Bypass del mapper individual? No en la primera iteración.

Se evaluó permitir que el consolidador reciba arrays crudos saltándose la
etapa 1. Descartado como opción por defecto:

- El coste de los DTOs intermedios es despreciable — son wrappers finos sobre
  arrays ya descargados y decodificados; el coste dominante (HTTP +
  `json_decode`) se paga igual.
- Un consolidador sobre raw duplica el conocimiento del formato del API: dos
  rutas raw→DTO para la misma acción. Cuando el API cambie, el mapper se
  actualiza y el consolidador-bypass se rompe en silencio. Es la clase de bug
  que el invariante mapper↔action existe para impedir.

**Puerta de escape futura** (si aparece el caso real): mappers individuales
pesados en batches grandes donde el consolidado solo usa una fracción de los
campos. Si se da, añadir un `AbstractRawBatchMapper` separado — opt-in
explícito con el mismo invariante de acción — sin tocar lo construido. No
implementarlo de forma especulativa.

## Decisión abierta antes de implementar A

Semántica de la consolidación ante fallos parciales — opciones sobre la mesa:

- Consolidar solo los éxitos.
- Exigir batch completo (fallar si hay algún error).
- Que lo decida el caller con dos métodos: `merge()` estricto vs
  `mergeSuccessful()`.

## Disciplina

Al implementar, aplicar la doble revisión de mutantes descrita en
[TESTING.md](../TESTING.md#mutation-testing--dont-trust-100-msi): aserciones
de comportamiento, cobertura por clase y revisión manual de `infection.log`.
