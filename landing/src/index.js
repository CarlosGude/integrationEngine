import { getHTML } from './html.js';

export default {
    async fetch(request, env, ctx) {
        return new Response(getHTML(), {
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        });
    }
};
