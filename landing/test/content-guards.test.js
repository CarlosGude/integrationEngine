import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { getHTML } from '../src/html.js';

/**
 * Guard against hardcoded incorrect claims in the landing page.
 * Prevents outdated version numbers, incorrect patterns, and broken snippets from shipping.
 */

const SRC_DIR = join(import.meta.dirname, '..', 'src');

/**
 * A PHP namespace rendered without its backslashes, e.g. `App\Integration\Shopify\...`
 * showing up as `AppIntegrationShopify...`. Inside a JS template literal `\I` is an
 * invalid escape that silently drops the backslash, so namespaces must be written as `\\`.
 */
const STRIPPED_NAMESPACE = new RegExp(
  [
    String.raw`IntegrationEngine(?:Core|Infrastructure|Bundle|Contract)[A-Z]\w*`,
    String.raw`App(?:Integration|Infrastructure|Engine|Traditional|Shopify)[A-Z]\w*`,
    String.raw`Symfony(?:Component|Bundle|Contracts)[A-Z]\w*`,
    String.raw`Psr(?:Log|Cache|Http|Container|EventDispatcher)[A-Z]\w*`,
    String.raw`Sentry[a-z]\w*`,
  ].join('|'),
  'g',
);

function getAllJsFiles(dir) {
  const files = [];
  for (const file of readdirSync(dir, { recursive: true })) {
    if (file.endsWith('.js')) {
      files.push(file);
    }
  }
  return files;
}

const forbidden = [
  ['incorrect email domain', /integration\.dev/],
  ['nonexistent request factory', /EngineRequest\s*::\s*create/],
  ['outdated Symfony minimum', /Symfony 7\+/],
  ['unsupported experience claim', /three years|tres años/i],
  ['unverified provider example', /stripe/i],
  ['unmeasured benchmark', /0[.,]8s|4[.,]2s|5[–-]13x|0[.,]06ms/],
  ['plural contact copy', /Drop us a line|Send us an email|Escríbenos|Envíanos/],
  ['company-name claim', /SAP, Salesforce/],
  ['old demo repository', /integrationEngine-use-example/],
  ['outdated quality claim', /100% mutation|100% de mutation|646 tests/],
  ['removed lifecycle events', /ActionStarted|ActionCompleted|ActionFailed/],
  ['removed timing API', /httpDurationMs|mappingDurationMs|totalDurationMs/],
  ['removed webhook idempotency API', /WebhookIdempotencyService|WebhookFingerprinter|WebhookIdempotencyPort/],
  ['legacy wiki documentation links', /github\.com\/CarlosGude\/integrationEngine\/wiki\//],
];

for (const [name, pattern] of forbidden) {
  test(`content: no ${name}`, () => {
    // Strip syntax-highlighting tags too: HTML spans cannot hide invalid PHP.
    assert.doesNotMatch(readAllSrcContent().replace(/<[^>]*>/g, ''), pattern);
  });
}

for (const [lang, address] of [['en', 'hi@integrationengine.dev'], ['es', 'hola@integrationengine.dev']]) {
  test(`rendered html (${lang}): contact address and mailto agree`, () => {
    const html = getHTML(lang);
    assert.ok(html.includes(`href="mailto:${address}"`));
    assert.ok(html.includes(`>${address}</span>`));
  });

  test(`rendered html (${lang}): roadmap, demo source and valid internal anchors`, () => {
    const html = getHTML(lang);
    assert.ok(html.includes('id="roadmap"'));
    assert.ok(html.indexOf('id="roadmap"') < html.indexOf('id="contact"'));
    assert.ok(html.includes('https://github.com/CarlosGude/integrationEngine-demo'));
    assert.ok(html.includes('https://github.com/CarlosGude/integrationEngine/blob/main/docs/ROADMAP.md'));
    const ids = new Set([...html.matchAll(/\bid="([^"]+)"/g)].map(match => match[1]));
    for (const [, anchor] of html.matchAll(/href="#([^"]+)"/g)) {
      assert.ok(ids.has(anchor), `Missing section #${anchor}`);
    }
    assert.doesNotMatch(html, /\bundefined\b/);
  });

  test(`rendered html (${lang}): batch examples use the real constructor`, () => {
    const code = getHTML(lang).replace(/<[^>]*>/g, '');
    assert.equal([...code.matchAll(/new EngineRequest\(/g)].length, 2);
    assert.ok(code.includes('GetMovieAction::getName()'));
    assert.doesNotMatch(code, /EngineRequest\s*::\s*create/);
  });

  test(`rendered html (${lang}): repository documentation links exist`, () => {
    const html = getHTML(lang);
    for (const [, path] of html.matchAll(/https:\/\/github\.com\/CarlosGude\/integrationEngine\/blob\/main\/([^"#?]+)/g)) {
      assert.ok(existsSync(join(SRC_DIR, '..', '..', path)), `Missing repository file: ${path}`);
    }
  });
}

for (const lang of ['en', 'es']) {
  test(`rendered html (${lang}): PHP namespaces keep their backslashes`, () => {
    const found = getHTML(lang).match(STRIPPED_NAMESPACE) ?? [];

    assert.deepStrictEqual(
      found,
      [],
      'Found PHP namespaces rendered without backslashes. Inside template literals write them as "\\\\" ' +
        '(e.g. App\\\\Integration\\\\Shopify), otherwise "\\I" renders as "I".',
    );
  });

  test(`rendered html (${lang}): no span tag missing its closing ">"`, () => {
    // e.g. <span class="str"'shopify'</span> — the browser swallows 'shopify' as an attribute name.
    const found = getHTML(lang).match(/<span class="[\w-]+"[^\s>/][^<]*/g) ?? [];

    assert.deepStrictEqual(found, [], 'Found <span> tags missing their closing ">"; their text is not rendered.');
  });
}

function readAllSrcContent() {
  const files = getAllJsFiles(SRC_DIR);
  let content = '';

  for (const file of files) {
    content += readFileSync(join(SRC_DIR, file), 'utf8') + '\n';
  }

  return content;
}
