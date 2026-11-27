#!/usr/bin/env node
/**
 * BO4c — Kopfdaten-Apply bleibt; Konflikte/leere Zeilen klar im Status.
 * Usage: node bin/beleg-ocr-bo4c-apply-status-selftest.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const importJs = readFileSync(join(root, 'assets/js/buchhaltung-import.mjs'), 'utf8');
const belege = readFileSync(join(root, 'assets/js/buchhaltung-belege.js'), 'utf8');
const view = readFileSync(join(root, 'views/modules/buchhaltung-beleg-form.php'), 'utf8');

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

if (!importJs.includes('function buildApplyStatus')) {
  fail('missing buildApplyStatus');
}
if (!importJs.includes('Kopfdaten übernommen')) {
  fail('status must mention Kopfdaten');
}
if (!importJs.includes('übersprungen')) {
  fail('status must mention skipped lines');
}
if (!importJs.includes('Summenprüfung')) {
  fail('status must surface amount_check conflicts');
}
if (!importJs.includes("amountCheck.ok === false")) {
  fail('must check amount_check.ok === false');
}
if (!importJs.includes('Kopfdaten immer zuerst')) {
  fail('missing comment/order guarantee for header-first apply');
}

// Header fields still applied in applySuggestion
for (const needle of [
  'dg-voucher-invoice-number',
  'dg-voucher-date',
  'dg-voucher-supplier-name',
]) {
  if (!importJs.includes(needle)) fail(`header apply missing ${needle}`);
}

// Header before line apply: invoice number setField appears before applyOcrExtractLines call in apply()
const applyStart = importJs.indexOf('const apply = async () =>');
const headerPos = importJs.indexOf('dg-voucher-supplier-name', applyStart);
const linesPos = importJs.indexOf('applyOcrExtractLines', applyStart);
if (headerPos < 0 || linesPos < 0 || headerPos > linesPos) {
  fail('header apply must run before position apply');
}

if (!belege.includes('skippedNoAmount')) {
  fail('normalize must track skippedNoAmount');
}
if (!belege.includes('skippedEmptyTitle')) {
  fail('normalize must track skippedEmptyTitle');
}
if (!belege.includes('skippedTitles')) {
  fail('skippedTitles for status detail');
}

if (/BO4c|Gate G3|Stufe C/.test(view)) {
  fail('plan codes in view');
}
if (importJs.includes('BO4c') && /['"`][^'"`]*BO4c/.test(importJs.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, ''))) {
  // allow BO4c only in comments — strip comments already; if still in strings fail
  const stringHits = [...importJs.matchAll(/['"`]([^'"`]{0,80}BO4c[^'"`]{0,40})['"`]/g)];
  if (stringHits.length) fail(`plan code in string: ${stringHits[0][1]}`);
}

console.log('OK BO4c apply status selftest');
