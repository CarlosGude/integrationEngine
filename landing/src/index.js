import { getHTML } from './html.js';

export default {
    async fetch(request, env, ctx) {
        const url  = new URL(request.url);
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
