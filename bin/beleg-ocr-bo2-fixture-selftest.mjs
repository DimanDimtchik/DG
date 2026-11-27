#!/usr/bin/env node
/**
 * BO2d — Fixture-XML → BO2-Schema-Felder (eine Selftest-Datei, Fixtures unter docs/testdata/beleg-ocr/).
 * Usage: node bin/beleg-ocr-bo2-fixture-selftest.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  parseEInvoiceXml,
  looksLikeEInvoiceXml,
} from '../assets/js/vendor/dg-einvoice-xml.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const fixtureDir = join(root, 'docs/testdata/beleg-ocr');

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

function load(name) {
  return readFileSync(join(fixtureDir, name), 'utf8');
}

function assertBo2Shape(extract, label) {
  if (!extract || extract.schema_version !== 1) fail(`${label}: schema_version`);
  if (extract.source !== 'einvoice_xml') fail(`${label}: source=${extract.source}`);
  for (const key of [
    'supplier_name',
    'invoice_number',
    'invoice_date',
    'currency',
    'gross_total',
    'net_total',
    'lines',
    'warnings',
    'amount_check',
  ]) {
    if (!(key in extract)) fail(`${label}: missing ${key}`);
  }
  if (!Array.isArray(extract.lines) || extract.lines.length < 1) {
    fail(`${label}: lines empty`);
  }
  for (const line of extract.lines) {
    for (const key of [
      'line_no',
      'catalog_kind',
      'title',
      'quantity',
      'unit',
      'line_net',
      'tax_percent',
      'confidence',
    ]) {
      if (!(key in line)) fail(`${label}: line missing ${key}`);
    }
    if (!['product', 'service'].includes(line.catalog_kind)) {
      fail(`${label}: bad catalog_kind ${line.catalog_kind}`);
    }
  }
  if (typeof extract.amount_check?.ok !== 'boolean') {
    fail(`${label}: amount_check.ok`);
  }
}

const ciiXml = load('cii-invoice.xml');
const ublXml = load('ubl-invoice.xml');

if (!looksLikeEInvoiceXml(ciiXml) || !looksLikeEInvoiceXml(ublXml)) {
  fail('fixtures not recognized as e-invoice');
}

const cii = parseEInvoiceXml(ciiXml, 'cii-invoice.xml');
assertBo2Shape(cii.extract, 'CII');
if (cii.extract.invoice_number !== 'RE-1001') fail(`CII invoice_number=${cii.extract.invoice_number}`);
if (cii.extract.supplier_name !== 'Demo Lieferant GmbH') fail('CII supplier');
if (cii.extract.invoice_date !== '2026-11-15') fail(`CII date=${cii.extract.invoice_date}`);
if (cii.extract.net_total !== '130.00') fail(`CII net=${cii.extract.net_total}`);
if (cii.extract.gross_total !== '154.70') fail(`CII gross=${cii.extract.gross_total}`);
if (cii.extract.lines.length !== 2) fail(`CII lines=${cii.extract.lines.length}`);
if (cii.extract.lines[0].catalog_kind !== 'product' || cii.extract.lines[0].sku !== 'ART-9') {
  fail('CII product line');
}
if (cii.extract.lines[1].catalog_kind !== 'service') fail('CII service line');
if (cii.extract.amount_check.ok !== true) fail('CII amount_check');

const ubl = parseEInvoiceXml(ublXml, 'ubl-invoice.xml');
assertBo2Shape(ubl.extract, 'UBL');
if (ubl.extract.invoice_number !== 'XR-77') fail(`UBL invoice_number=${ubl.extract.invoice_number}`);
if (ubl.extract.lines.length !== 1) fail(`UBL lines=${ubl.extract.lines.length}`);
if (ubl.extract.lines[0].sku !== 'SK-6' || ubl.extract.lines[0].quantity !== '3') {
  fail('UBL line fields');
}
if (ubl.extract.lines[0].line_net !== '30.00') fail(`UBL net=${ubl.extract.lines[0].line_net}`);

console.log('OK BO2d fixture XML → BO2 schema');
process.exit(0);
