# Landing — Cloudflare Worker

Landing page de [integrationengine.dev](https://integrationengine.dev), desplegada como un Cloudflare Worker.

## Estructura

```
landing/
├── src/
│   ├── index.js       ← fetch handler (entry point)
│   ├── html.js        ← HTML builder
│   ├── css.js         ← estilos inline
│   ├── client.js      ← JavaScript del navegador
│   └── i18n/
│       ├── es.js      ← traducciones en español
│       └── en.js      ← traducciones en inglés
├── wrangler.toml
└── README.md
```

Wrangler bundlea todos los archivos en un único Worker al desplegar. No hay paso de build separado.

## Idiomas

| URL | Idioma |
|-----|--------|
| `integrationengine.dev` | English (por defecto) |
| `integrationengine.dev?lang=es` | Español |
| `integrationengine.dev?lang=en` | English |

El selector de idioma está en la barra de navegación fija (🇪🇸 / 🇬🇧).

## Despliegue

### Desde el repo (recomendado)

```bash
make deploy-landing
```

Requiere autenticación local con `npx wrangler login`.

### CI/CD

El despliegue automático está configurado externamente en Cloudflare a partir
de los pushes al repositorio. Este checkout no contiene un workflow de GitHub
Actions de despliegue.

El job `landing` del CI principal ejecuta `node --test` con Node 22, sin instalar
dependencias. Para ejecutar las mismas comprobaciones localmente:

```bash
cd landing
npm test
```

Los tests comprueban paridad EN/ES (incluidas estructuras dentro de arrays),
contenido prohibido, constructores de los ejemplos PHP, anclas y rutas de
documentación dentro del repositorio.

### Estado de la demo y el correo

Revisado el 2026-09-22: el dominio previsto de la demo no resuelve por DNS;
la landing enlaza al código fuente y muestra la demo online como próxima.
Cambiar ese estado solo tras comprobar su URL pública. El roadmap no presenta
funcionalidades propuestas como ya publicadas.

Los MX de integrationengine.dev apuntan a Cloudflare. Eso no confirma las reglas
individuales de hi/hola ni la entrega al destinatario: falta comprobar Email
Routing y recibir correos de prueba. La sesión local de Wrangler estaba caducada
durante esta revisión; el despliegue se delega al flujo automático del repositorio.

## Modificar contenido

### Copy / traducciones

Edita `src/i18n/es.js` o `src/i18n/en.js`. Cada archivo exporta un objeto con todas las claves de texto de la landing.

### Ejemplos de código

Los snippets PHP están incrustados directamente en `src/html.js` (ya resaltados en HTML). Son compartidos por ambos idiomas.

### CSS

Edita `src/css.js`. El CSS se incrusta inline en el HTML generado.

### JavaScript del navegador

Edita `src/client.js`. Contiene la lógica de los tabs de código, el pipeline animado y el botón de copiar.
