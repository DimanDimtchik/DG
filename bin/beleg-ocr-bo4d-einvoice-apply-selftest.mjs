#!/usr/bin/env node
/**
 * BO4d — Guard + Smoke: E-Rechnung mit Zeilen → Apply-Normalisierung + Status.
 * Usage: node bin/beleg-ocr-bo4d-einvoice-apply-selftest.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parseEInvoiceXml, looksLikeEInvoiceXml } from '../assets/js/vendor/dg-einvoice-xml.mjs';
import {
  normalizeOcrExtractLines,
  buildApplyStatus,
} from '../assets/js/vendor/dg-voucher-ocr-apply.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const fixtureDir = join(root, 'docs/testdata/beleg-ocr');
const belege = readFileSync(join(root, 'assets/js/buchhaltung-belege.js'), 'utf8');
const importJs = readFileSync(join(root, 'assets/js/buchhaltung-import.mjs'), 'utf8');
const layout = readFileSync(join(root, 'views/layout/app.php'), 'utf8');
const view = readFileSync(join(root, 'views/modules/buchhaltung-beleg-form.php'), 'utf8');

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

// --- Guard: Verdrahtung ---
if (!layout.includes('dg-voucher-ocr-apply.mjs')) {
  fail('layout must load dg-voucher-ocr-apply.mjs before belege.js');
}
const applyScriptPos = layout.indexOf('dg-voucher-ocr-apply.mjs');
const belegeScriptPos = layout.indexOf('buchhaltung-belege.js');
if (applyScriptPos < 0 || belegeScriptPos < 0 || applyScriptPos > belegeScriptPos) {
  fail('ocr-apply module must be listed before buchhaltung-belege.js');
}
if (!belege.includes('window.dgVoucherOcrApply')) {
  fail('belege.js must use window.dgVoucherOcrApply');
}
if (!importJs.includes("from './vendor/dg-voucher-ocr-apply.mjs'")) {
  fail('import.mjs must import buildApplyStatus from vendor');
}
if (!importJs.includes('buildApplyStatus')) {
  fail('import.mjs must call buildApplyStatus');
}
if (!belege.includes('applyOcrExtractLines')) {
  fail('missing applyOcrExtractLines');
}
if (/BO4d|Gate G3|Stufe C/.test(view)) {
  fail('plan codes in end-user view');
}

// --- Smoke: CII-E-Rechnung mit Artikel+Leistung ---
const ciiXml = readFileSync(join(fixtureDir, 'cii-invoice.xml'), 'utf8');
if (!looksLikeEInvoiceXml(ciiXml)) fail('CII fixture not recognized');

const parsed = parseEInvoiceXml(ciiXml, 'cii-invoice.xml');
const extract = parsed.extract;
if (!extract || extract.schema_version !== 1) fail('CII extract schema');
if (!Array.isArray(extract.lines) || extract.lines.length < 2) {
  fail(`CII expected >=2 lines, got ${extract.lines?.length}`);
}

const normalized = normalizeOcrExtractLines(extract.lines);
if (normalized.lines.length !== 2) {
  fail(`normalize expected 2 applyable lines, got ${normalized.lines.length}`);
}
if (normalized.skipped !== undefined) {
  // skipped is sum on apply result, not normalize — ok
}
if (normalized.skippedNoAmount !== 0 || normalized.skippedEmptyTitle !== 0) {
  fail(`unexpected skips: ${JSON.stringify(normalized)}`);
}
if (normalized.lines[0].catalog_kind !== 'product') fail('line0 product');
if (normalized.lines[1].catalog_kind !== 'service') fail('line1 service');
if (!(normalized.lines[0].gross_amount > 0) || !(normalized.lines[1].gross_amount > 0)) {
  fail('both lines need gross_amount');
}
if (!normalized.lines[0].title || !normalized.lines[1].title) fail('titles required');

const okStatus = buildApplyStatus(
  { extract },
  {
    mode: 'splits',
    count: normalized.lines.length,
    skipped: 0,
    skippedNoAmount: 0,
    skippedEmptyTitle: 0,
    skippedTitles: [],
  },
  extract.lines.length,
);
if (okStatus.kind !== 'ok') fail(`expected ok status, got ${okStatus.kind}: ${okStatus.message}`);
if (!okStatus.message.includes('Kopfdaten') || !okStatus.message.includes('2 Positionen')) {
  fail(`unexpected ok message: ${okStatus.message}`);
}

const skipNorm = normalizeOcrExtractLines([
  ...extract.lines,
  { title: 'Ohne Betrag', catalog_kind: 'service', quantity: '1' },
  { title: '', line_net: '10.00' },
]);
if (skipNorm.skippedNoAmount < 1 || skipNorm.skippedEmptyTitle < 1) {
  fail('skip counters for empty title / no amount');
}
const warnStatus = buildApplyStatus(
  {
    extract: {
      ...extract,
      amount_check: { ok: false, detail: 'Zeilensumme weicht ab.' },
      warnings: ['Demo-Hinweis'],
    },
  },
  {
    mode: 'splits',
    count: skipNorm.lines.length,
    skipped: skipNorm.skippedNoAmount + skipNorm.skippedEmptyTitle,
    skippedNoAmount: skipNorm.skippedNoAmount,
    skippedEmptyTitle: skipNorm.skippedEmptyTitle,
    skippedTitles: skipNorm.skippedTitles,
  },
  extract.lines.length + 2,
);
if (warnStatus.kind !== 'warn') fail('expected warn status');
if (!warnStatus.message.includes('übersprungen')) fail('warn must mention skips');
if (!warnStatus.message.includes('Summenprüfung')) fail('warn must mention amount check');

console.log('OK BO4d e-invoice apply guard+smoke (CII → 2 lines)');
