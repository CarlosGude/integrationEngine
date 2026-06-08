# IntegrationEngine - Documentación

## Modelo mental

Una integración es una carpeta.

Un endpoint es una subcarpeta.

Cada endpoint contiene:

- Request (entrada)
- Response (salida)

Nada más debe dispersarse.

---

## Ciclo de vida

1. Definir integración
2. Generar endpoints
3. Implementar request + response
4. Registrar integración
5. Usar desde el engine

---

## Enviar una petición

```php
$response = $integrationEngine->send(
    actionName: GetEmployeeAction::getName(),
    context: DefaultActionContext::create(['id' => 123])
);
```

El engine resuelve:

- Acción
- Request
- Transporte
- Mapping de respuesta

---

## Estructura

Cada endpoint se divide en dos partes:

### Request
- Action
- Contexto
- Mapeo de entrada

### Response
- DTO
- Mapper
- Normalización de salida

---

## Registry

Las integraciones se obtienen desde el registry:

```php
$engine = $registry->get('dummy_rest_api');
```

---

## Cache

⚠️ Importante:

El cache en memoria es por proceso.

No es válido para:

- Sistemas distribuidos
- Auth multi-worker

Usar solo en ciclo de request.

---

## Principios

- Predictibilidad sobre flexibilidad
- Estructura sobre libertad
- Convención sobre invento
- Uniformidad entre integraciones

---

## Anti-patrones evitados

- HTTP clients dispersos
- Arquitecturas distintas por integración
- Mapeos duplicados
- Estructuras inconsistentes
