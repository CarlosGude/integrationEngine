# IntegrationEngine — Testing Strategy Guide (Target: 90%+ meaningful coverage)

Este documento define la estrategia de testing del `IntegrationEngine` con un objetivo claro:  
**alta cobertura real del comportamiento del sistema sin depender de mocks complejos ni tests frágiles.**

La prioridad no es “cubrir líneas”, sino **garantizar que el engine nunca rompe el contrato de integración**.

---

# 🧠 Principios de la estrategia

- Los tests validan comportamiento, no implementación.
- El `IntegrationEngine` es el único punto crítico del sistema.
- Se usan fakes simples en los bordes (Config, Client, Cache).
- Evitar spies y mocks complejos.
- Cada test debe representar un escenario real de integración.
- El objetivo es detectar roturas del contrato, no microdetalles.

---

# 🧪 Fase 1 — Core Flow (contrato base del engine)

## Test 1 — Ejecución básica de una acción válida

**Qué valida:**
- El flujo completo del engine funciona de extremo a extremo.

**Qué cubre:**
- ConfigPort → resolución de Action
- Client → ejecución HTTP
- Mapper → transformación de respuesta
- Response final

**Qué se espera:**
- El engine devuelve una `ResponseInterface` válida.
- No se lanzan excepciones.

---

## Test 2 — Acción sin response devuelve respuesta vacía

**Qué valida:**
- El comportamiento de acciones que no devuelven payload (ej: DELETE).

**Qué cubre:**
- Rama `hasResponse() === false`

**Qué se espera:**
- Se devuelve una respuesta vacía consistente.
- No se invoca mapper.

---

## Test 3 — Acción sin mapper definido

**Qué valida:**
- El sistema protege el contrato de transformación.

**Qué cubre:**
- Validación de `mapper() === null`

**Qué se espera:**
- Se lanza una excepción de tipo lógica.
- El engine no intenta procesar la respuesta.

---

# 🧪 Fase 2 — Mapper system (integridad de transformación)

## Test 4 — Mapper inválido

**Qué valida:**
- El sistema rechaza mappers mal definidos.

**Qué cubre:**
- Verificación de clase inválida

**Qué se espera:**
- Se lanza `InvalidMapperException`.

---

## Test 5 — Mapper no corresponde a la acción

**Qué valida:**
- Integridad entre Action ↔ Mapper.

**Qué cubre:**
- `getAction()` del mapper vs clase real del Action

**Qué se espera:**
- Se lanza `MapperActionMismatchException`.

---

## Test 6 — Mapper correcto transforma correctamente

**Qué valida:**
- Transformación correcta de la respuesta raw.

**Qué cubre:**
- Pipeline completo de mapping

**Qué se espera:**
- La respuesta final contiene los datos transformados esperados.

---

# 🧪 Fase 3 — Authorization system (dinámico y estático)

## Test 7 — Authorization estática no modifica la acción

**Qué valida:**
- Flujo sin resolución dinámica de tokens.

**Qué cubre:**
- StaticAuthorizationConfig bypass

**Qué se espera:**
- El engine ejecuta la acción sin modificarla.
- No se accede a cache ni client adicional.

---

## Test 8 — Authorization dinámica resuelve token correctamente

**Qué valida:**
- Resolución de autenticación basada en acción secundaria.

**Qué cubre:**
- DynamicAuthorizationConfig flow
- ejecución de acción de auth

**Qué se espera:**
- Se genera un token válido.
- Se reemplaza la autorización en la acción.

---

## Test 9 — Cache evita recalcular token

**Qué valida:**
- Optimización del sistema de autenticación.

**Qué cubre:**
- Cache hit en tokens

**Qué se espera:**
- No se ejecuta la acción de auth de nuevo.
- Se reutiliza el token cacheado.

---

## Test 10 — Fallo de auth por campo inexistente

**Qué valida:**
- Robustez ante APIs externas inconsistentes.

**Qué cubre:**
- Falta de `tokenField` en respuesta de auth

**Qué se espera:**
- Se lanza RuntimeException.
- El sistema no cachea valores inválidos.

---

# 🧪 Fase 4 — Integridad del sistema (comportamiento global)

## Test 11 — Flujo completo con cache miss y auth dinámica

**Qué valida:**
- Integración completa del sistema bajo condiciones reales.

**Qué cubre:**
- Config → Auth → Client → Mapper → Cache

**Qué se espera:**
- Se ejecuta auth.
- Se guarda token en cache.
- Se completa la ejecución de la acción.

---

## Test 12 — Reutilización de token en múltiples ejecuciones

**Qué valida:**
- Estabilidad del sistema en múltiples llamadas.

**Qué cubre:**
- Persistencia del cache entre ejecuciones

**Qué se espera:**
- El auth flow solo ocurre una vez.
- Las siguientes ejecuciones reutilizan token.

---

# 🧪 Fase 5 — Robustez del sistema (edge cases críticos)

## Test 13 — Mapper missing en acción con response requerido

**Qué valida:**
- Consistencia estricta del contrato.

**Qué se espera:**
- El sistema falla de forma explícita.

---

## Test 14 — Acción inválida en ConfigPort

**Qué valida:**
- Protección ante configuración corrupta o mal definida.

**Qué se espera:**
- Excepción controlada.

---

## Test 15 — Respuesta raw vacía con mapper requerido

**Qué valida:**
- Robustez del mapping ante APIs inconsistentes.

**Qué se espera:**
- El mapper gestiona o lanza error controlado.

---

# 🧠 Resultado esperado de esta estrategia

Si esta suite se implementa correctamente:

- 60–70% coverage real en fase inicial
- 80%+ tras edge cases
- 90%+ con ampliación por dominios reales
- Sin dependencia de mocks complejos
- Sin tests frágiles ligados a implementación interna

---

# ⚖️ Filosofía final

El objetivo no es probar el código.

El objetivo es garantizar esto:

> “ninguna integración puede romper el contrato del engine sin ser detectada inmediatamente”

---

# 🚀 Evolución futura (no necesaria ahora)

- contract suites por dominio (Iberia, Stripe, etc.)
- simulación de APIs legacy
- escenarios de resiliencia (timeouts, retries, circuit breakers)
- test de comportamiento bajo degradación

---