# Landing — Cloudflare Worker

Landing page de [integrationengine.dev](https://integrationengine.dev), desplegada como un Cloudflare Worker de un solo archivo.

## Estructura

```
landing/
├── worker.js   ← todo el HTML, CSS y JS generado en el servidor
└── README.md
```

El worker no tiene dependencias ni proceso de build. Todo el HTML se genera en tiempo de request dentro del propio `worker.js`.

## Idiomas

La landing está disponible en español e inglés. El idioma por defecto es el español.

| URL | Idioma |
|-----|--------|
| `integrationengine.dev` | Español |
| `integrationengine.dev?lang=en` | English |

El selector de idioma está en la barra de navegación fija (banderas 🇪🇸 / 🇬🇧).

## Despliegue

### Requisitos

- Cuenta en [Cloudflare](https://cloudflare.com) (plan gratuito suficiente)
- El dominio gestionado por Cloudflare (nameservers apuntando a Cloudflare)

### Desde el dashboard de Cloudflare

1. Ve a **Workers & Pages → Create → Worker**
2. Ponle nombre al worker (ej. `integrationengine-landing`)
3. Haz clic en **Edit code**
4. Borra el contenido por defecto y pega el contenido de `worker.js`
5. Haz clic en **Deploy**

Para asociar el dominio custom:

6. Ve a la pestaña **Settings → Domains & Routes**
7. En **Custom Domains**, añade `integrationengine.dev`
8. Cloudflare crea automáticamente el registro DNS de tipo `Worker` en el panel DNS del dominio

### Desde Wrangler (CLI)

```bash
npm install -g wrangler
wrangler login
wrangler deploy landing/worker.js --name integrationengine-landing
```

Para añadir el dominio custom desde CLI, añade un `wrangler.toml` junto al worker:

```toml
name = "integrationengine-landing"
main = "worker.js"
compatibility_date = "2025-01-01"

routes = [
  { pattern = "integrationengine.dev/*", custom_domain = true }
]
```

Y despliega con:

```bash
wrangler deploy
```

## Modificar contenido

Todo el texto de la landing está en el objeto `T` al principio de `worker.js`, separado por idioma (`es` / `en`). Para cambiar cualquier copy basta con editar ese objeto y redesplegar.

```js
const T = {
  es: {
    heroH1: 'Deja de escribir el mismo cliente HTTP una y otra vez',
    // ...
  },
  en: {
    heroH1: 'Stop writing the same HTTP client over and over again',
    // ...
  },
};
```

El CSS está en la constante `CSS` y el JavaScript del cliente en la constante `JS`, ambas al final del archivo.