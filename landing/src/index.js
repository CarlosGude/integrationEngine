import { getHTML } from './html.js';

const SITE = 'https://integrationengine.dev';

const ROBOTS_TXT = `User-agent: *\nAllow: /\n\nSitemap: ${SITE}/sitemap.xml\n`;

const SITEMAP_XML = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>${SITE}/</loc>
    <xhtml:link rel="alternate" hreflang="en" href="${SITE}/?lang=en" />
    <xhtml:link rel="alternate" hreflang="es" href="${SITE}/?lang=es" />
    <xhtml:link rel="alternate" hreflang="x-default" href="${SITE}/?lang=en" />
  </url>
  <url>
    <loc>${SITE}/?lang=es</loc>
    <xhtml:link rel="alternate" hreflang="en" href="${SITE}/?lang=en" />
    <xhtml:link rel="alternate" hreflang="es" href="${SITE}/?lang=es" />
    <xhtml:link rel="alternate" hreflang="x-default" href="${SITE}/?lang=en" />
  </url>
</urlset>
`;

export default {
    async fetch(request, env, ctx) {
        const url = new URL(request.url);

        if ('/robots.txt' === url.pathname) {
            return new Response(ROBOTS_TXT, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
        }

        if ('/sitemap.xml' === url.pathname) {
            return new Response(SITEMAP_XML, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
        }

        const lang = url.searchParams.get('lang') === 'es' ? 'es' : 'en';
        return new Response(getHTML(lang), {
            headers: {
                'Content-Type': 'text/html; charset=utf-8',
                'Cache-Control': 'public, max-age=300, s-maxage=86400',
                'X-Content-Type-Options': 'nosniff',
                'X-Frame-Options': 'DENY',
                'Referrer-Policy': 'strict-origin-when-cross-origin',
            },
        });
    }
};
