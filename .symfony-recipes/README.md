# Symfony Recipe - IntegrationEngine 5.2.0

Este directorio contiene los archivos para contribuir el recipe de IntegrationEngine a [symfony-recipes-contrib](https://github.com/symfony/recipes-contrib).

## ¿Qué es un recipe?

Un recipe es una automatización que Symfony Flex ejecuta cuando haces:

```bash
composer require carlosgude/integration-engine
```

**Sin recipe:** tienes que crear config manualmente  
**Con recipe:** Flex lo hace todo automáticamente

## Estructura

```
.symfony-recipes/
├── README.md                              ← Este archivo
├── CONTRIBUTE.sh                          ← Script para crear el PR ⭐
└── integration-engine/5.2/
    ├── manifest.json                      ← Instrucciones para Flex
    ├── config/packages/
    │   └── integration_engine.yaml        ← Config por defecto
    ├── src/Integration/
    │   └── .gitkeep                       ← Placeholder
    └── post-install.txt                   ← Mensaje post-install
```

## Uso

### Opción A: Automatizado (recomendado)

```bash
cd .symfony-recipes
bash CONTRIBUTE.sh
```

Esto:
- Clone symfony-recipes-contrib
- Crea rama add-integration-engine
- Copia los archivos
- Crea commit
- Te muestra qué hacer después

### Opción B: Manual

Sigue los pasos en `CONTRIBUTE.sh` manualmente.

## Después del script

```bash
cd ~/symfony-recipes-contrib

# Push
git push origin add-integration-engine

# Crea PR en https://github.com/symfony/recipes-contrib
# (usa la descripción que te mostró el script)
```

## Qué pasa cuando se mergea

Cuando el PR se mergea a symfony-recipes-contrib:
- El recipe estará disponible en Symfony Flex
- Cualquiera con `composer require carlosgude/integration-engine` obtendrá:
  - ✅ Bundle auto-registrado
  - ✅ config/packages/integration_engine.yaml creado
  - ✅ src/Integration/ creado
  - ✅ Post-install message

## Referencias

- [Symfony Recipes Docs](https://symfony.com/doc/current/setup/flex.html)
- [symfony-recipes-contrib](https://github.com/symfony/recipes-contrib)
- [IntegrationEngine Bundle](https://github.com/CarlosGude/integrationEngine)

---

**¡Listo!** Ejecuta `bash CONTRIBUTE.sh` y sigue las instrucciones.
