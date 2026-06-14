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
│   ├── snippets.js    ← ejemplos de código PHP compartidos
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

El workflow `.github/workflows/deploy-landing.yml` despliega automáticamente en cada push a `main` que modifique archivos en `landing/**`.

Requiere dos secrets en el repositorio de GitHub:
- `CLOUDFLARE_API_TOKEN`
- `CLOUDFLARE_ACCOUNT_ID`

## Modificar contenido

### Copy / traducciones

Edita `src/i18n/es.js` o `src/i18n/en.js`. Cada archivo exporta un objeto con todas las claves de texto de la landing.

### Ejemplos de código

Los snippets PHP del call site están en `src/snippets.js`. Son compartidos por ambos idiomas.

### CSS

Edita `src/css.js`. El CSS se incrusta inline en el HTML generado.

### JavaScript del navegador

Edita `src/client.js`. Contiene la lógica de los tabs de código, el pipeline animado y el botón de copiar.
