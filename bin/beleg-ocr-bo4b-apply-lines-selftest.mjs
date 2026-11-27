#!/usr/bin/env node
/**
 * BO4b — Apply füllt Positionen (items/splits) aus Extrakt.
 * Usage: node bin/beleg-ocr-bo4b-apply-lines-selftest.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const belege = readFileSync(join(root, 'assets/js/buchhaltung-belege.js'), 'utf8');
const importJs = readFileSync(join(root, 'assets/js/buchhaltung-import.mjs'), 'utf8');
const view = readFileSync(join(root, 'views/modules/buchhaltung-beleg-form.php'), 'utf8');

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

if (!belege.includes('function applyOcrExtractLines')) {
  fail('missing applyOcrExtractLines');
}
if (!belege.includes('fillInvoiceItemsFromOcr')) {
  fail('missing fillInvoiceItemsFromOcr');
}
if (!belege.includes('fillBookingSplitsFromOcr')) {
  fail('missing fillBookingSplitsFromOcr');
}
if (!belege.includes('applyOcrExtractLines: applyOcrExtractLines')) {
  fail('applyOcrExtractLines not exported on dgVoucherFormApi');
}
if (!belege.includes('catalog_kind')) {
  fail('catalog_kind must be written for items');
}
if (!belege.includes("kindField.value = line.catalog_kind")) {
  fail('items path must set catalog_kind');
}

if (!importJs.includes('applyOcrExtractLines')) {
  fail('import applySuggestion must call applyOcrExtractLines');
}
const applyVendor = readFileSync(join(root, 'assets/js/vendor/dg-voucher-ocr-apply.mjs'), 'utf8');
if (!applyVendor.includes('Positionen als Buchungszeilen übernommen')
  && !applyVendor.includes('als Buchungszeilen übernommen')) {
  fail('missing user status for applied split lines');
}
if (!applyVendor.includes('Rechnungspositionen übernommen')) {
  fail('missing user status for applied item lines');
}
if (/BO4b|Gate G3|Stufe C/.test(view)) {
  fail('plan codes must not appear in end-user view');
}
for (const bad of ['BO4b übernommen', 'Gate G3']) {
  if (importJs.includes(bad)) fail(`plan code in client string: ${bad}`);
}

// Expense path must not invent accounts
const splitsFn = belege.slice(
  belege.indexOf('function fillBookingSplitsFromOcr'),
  belege.indexOf('window.dgVoucherFormApi'),
);
if (/accountField\.value\s*=\s*['"][0-9]/.test(splitsFn)) {
  fail('expense splits must not hardcode account numbers');
}
if (!splitsFn.includes("accountField.value = ''")) {
  fail('expense splits must clear account for manual choice');
}

console.log('OK BO4b apply extract lines selftest');
