import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import en from '../src/i18n/en.js';
import es from '../src/i18n/es.js';

/**
 * Verify that all keys in en.js exist in es.js and vice versa.
 * Both language files must have identical key structure.
 */
test('i18n parity: all keys in en exist in es', () => {
  const enKeys = getAllKeys(en);
  const esKeys = getAllKeys(es);

  const missingInEs = enKeys.filter((key) => !esKeys.includes(key));
  const missingInEn = esKeys.filter((key) => !enKeys.includes(key));

  if (missingInEs.length > 0) {
    throw new Error(`Keys missing in es.js: ${missingInEs.join(', ')}`);
  }

  if (missingInEn.length > 0) {
    throw new Error(`Keys missing in en.js: ${missingInEn.join(', ')}`);
  }

  assert.deepEqual(enKeys.sort(), esKeys.sort(), 'All keys must match between en.js and es.js');
});

/**
 * Recursively collect all keys from a nested object.
 */
function getAllKeys(obj, prefix = '') {
  const keys = [];

  for (const [key, value] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key;

    if (typeof value === 'object' && value !== null) {
      // Recurse into nested objects
      keys.push(...getAllKeys(value, fullKey));
    } else {
      // Collect the key
      keys.push(fullKey);
    }
  }

  return keys;
}
