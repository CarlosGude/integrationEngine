import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Guard against hardcoded incorrect claims in the landing page.
 * Prevents outdated version numbers, incorrect patterns, and broken snippets from shipping.
 */

const SRC_DIR = join(import.meta.dirname, '..', 'src');

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
  const content = readAllSrcContent();
  const lines = content.split('\n');

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    // Match "integration.dev" but ensure it's followed by "engine"
    if (line.includes('integration.dev') && !line.includes('integrationengine.dev')) {
      throw new Error(
        `Line ${i + 1}: Found "integration.dev" without "engine". ` +
        `Correct domain is "integrationengine.dev". Line: ${line.trim()}`
      );
    }
  }
});

test('content: no raw EngineRequest::create outside HTML tags', () => {
  const content = readAllSrcContent();

  // Check for raw unescaped code snippets (not in proper HTML span tags)
  // Valid: <span class="cls">EngineRequest</span>::<span class="fn">create</span>
  // Invalid: bare EngineRequest::create in text content

  const lines = content.split('\n');
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    // Skip lines that are inside proper HTML code blocks with span tags
    if (line.includes('<span class="cls">EngineRequest</span>')) {
      // This is correct HTML syntax; skip it
      continue;
    }

    // Check if there's a raw EngineRequest::create not in HTML
    if (line.includes('EngineRequest::create') && !line.includes('<span')) {
      throw new Error(
        `Line ${i + 1}: Found raw "EngineRequest::create" without HTML tags. ` +
        `Code examples must use proper syntax highlighting. Line: ${line.trim()}`
      );
    }
  }
});

test('content: no outdated "Symfony 7+" claims', () => {
  const content = readAllSrcContent();

  if (content.includes('Symfony 7+')) {
    throw new Error('Found "Symfony 7+" in content. Should reference actual supported versions (6.4+).');
  }
});

test('content: no "three years" or "tres años" (outdated claims)', () => {
  const content = readAllSrcContent();

  if (content.includes('three years')) {
    throw new Error('Found outdated "three years" claim. Update to reflect current timeline.');
  }

  if (content.includes('tres años')) {
    throw new Error('Found outdated "tres años" claim. Update to reflect current timeline.');
  }
});

test('content: no Stripe benchmark or benchmark claims', () => {
  const content = readAllSrcContent();

  // Watch for benchmark-related claims
  if (content.match(/Stripe.*benchmark/i) || content.match(/benchmark.*Stripe/i)) {
    throw new Error('Found Stripe-related benchmark claim. Benchmarks should be measured, not claimed.');
  }

  // Also catch if benchmark examples exist without measurement
  if (content.includes('Stripe integration') && content.includes('benchmark')) {
    throw new Error('Found potentially false benchmark claim tied to Stripe.');
  }
});

function readAllSrcContent() {
  const files = getAllJsFiles(SRC_DIR);
  let content = '';

  for (const file of files) {
    const filePath = join(SRC_DIR, file);
    try {
      content += readFileSync(filePath, 'utf8') + '\n';
    } catch (e) {
      // Ignore read errors for now
    }
  }

  return content;
}
