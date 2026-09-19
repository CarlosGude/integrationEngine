import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { readFileSync, readdirSync } from 'node:fs';
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

test('content: no "integration.dev" without "engine"', () => {
  const violations = findLineViolations(
    // Match "integration.dev" but ensure it's followed by "engine"
    (line) => line.includes('integration.dev') && !line.includes('integrationengine.dev'),
  );

  assert.deepStrictEqual(
    violations,
    [],
    'Found "integration.dev" without "engine". Correct domain is "integrationengine.dev".',
  );
});

test('content: no raw EngineRequest::create outside HTML tags', () => {
  // Check for raw unescaped code snippets (not in proper HTML span tags)
  // Valid: <span class="cls">EngineRequest</span>::<span class="fn">create</span>
  // Invalid: bare EngineRequest::create in text content
  const violations = findLineViolations(
    (line) =>
      // Lines inside proper HTML code blocks with span tags use correct syntax
      !line.includes('<span class="cls">EngineRequest</span>') &&
      line.includes('EngineRequest::create') &&
      !line.includes('<span'),
  );

  assert.deepStrictEqual(
    violations,
    [],
    'Found raw "EngineRequest::create" without HTML tags. Code examples must use proper syntax highlighting.',
  );
});

test('content: no outdated "Symfony 7+" claims', () => {
  const content = readAllSrcContent();

  assert.doesNotMatch(
    content,
    /Symfony 7\+/,
    'Found "Symfony 7+" in content. Should reference actual supported versions (6.4+).',
  );
});

test('content: no "three years" or "tres años" (outdated claims)', () => {
  const content = readAllSrcContent();

  assert.doesNotMatch(content, /three years/, 'Found outdated "three years" claim. Update to reflect current timeline.');
  assert.doesNotMatch(content, /tres años/, 'Found outdated "tres años" claim. Update to reflect current timeline.');
});

test('content: no Stripe benchmark or benchmark claims', () => {
  const content = readAllSrcContent();

  // Watch for benchmark-related claims
  assert.doesNotMatch(
    content,
    /Stripe.*benchmark/i,
    'Found Stripe-related benchmark claim. Benchmarks should be measured, not claimed.',
  );
  assert.doesNotMatch(
    content,
    /benchmark.*Stripe/i,
    'Found Stripe-related benchmark claim. Benchmarks should be measured, not claimed.',
  );

  // Also catch if benchmark examples exist without measurement
  assert.ok(
    !(content.includes('Stripe integration') && content.includes('benchmark')),
    'Found potentially false benchmark claim tied to Stripe.',
  );
});

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

/**
 * Returns "file:line: text" for every source line matching the predicate.
 */
function findLineViolations(isViolation) {
  const violations = [];

  for (const file of getAllJsFiles(SRC_DIR)) {
    const lines = readFileSync(join(SRC_DIR, file), 'utf8').split('\n');
    lines.forEach((line, index) => {
      if (isViolation(line)) {
        violations.push(`${file}:${index + 1}: ${line.trim()}`);
      }
    });
  }

  return violations;
}

function readAllSrcContent() {
  const files = getAllJsFiles(SRC_DIR);
  let content = '';

  for (const file of files) {
    content += readFileSync(join(SRC_DIR, file), 'utf8') + '\n';
  }

  return content;
}
