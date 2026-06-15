import { getHTML } from './html.js';

export default {
    async fetch(request, env, ctx) {
        const url  = new URL(request.url);
        const lang = url.searchParams.get('lang') === 'es' ? 'es' : 'en';
        return new Response(getHTML(lang), {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
};
