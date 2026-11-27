#!/usr/bin/env node
/**
 * BO3d — Client ruft voucher_extract nach OCR/Textlayer; UI ohne Plan-Codes.
 * Usage: node bin/beleg-ocr-bo3d-assistenz-selftest.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const js = readFileSync(join(root, 'assets/js/buchhaltung-import.mjs'), 'utf8');
const view = readFileSync(join(root, 'views/modules/buchhaltung-beleg-form.php'), 'utf8');

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

if (!js.includes("action=voucher_extract")) {
  fail('missing fetch to voucher_extract');
}
if (!js.includes('enrichWithAssistenz')) {
  fail('missing enrichWithAssistenz');
}
if (!js.includes('Assistenz ordnet den Text')) {
  fail('missing working status for assistenz');
}
if (!js.includes('geordnet durch Assistenz')) {
  fail('missing ordered-by-assistenz status/label');
}
if (!js.includes('hasCompleteEInvoiceLines')) {
  fail('missing E-Rechnung skip guard');
}
if (!js.includes('hint_extract')) {
  fail('missing hint_extract in request');
}
if (!js.includes('Text-Hinweise behalten')) {
  fail('missing soft-fail keep-hints wording');
}
if (!js.includes('async function finalizeSuggestion')) {
  fail('finalizeSuggestion must be async for assistenz');
}

if (/BO3[a-e]|BO0\b|Gate G3|Stufe C/.test(view)) {
  fail('plan codes must not appear in end-user view');
}

const userFacingNeedles = [
  'Assistenz ordnet den Text',
  'geordnet durch Assistenz',
  'Text geordnet durch Assistenz',
  'Text-Hinweise behalten',
];
for (const needle of userFacingNeedles) {
  if (!js.includes(needle)) continue;
  if (/BO\d|Gate G|Stufe [A-Z]/.test(needle)) {
    fail(`plan code in user-facing needle: ${needle}`);
  }
}

console.log('OK BO3d assistenz client selftest');
