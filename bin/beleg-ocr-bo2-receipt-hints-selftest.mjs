#!/usr/bin/env node
/**
 * BO2c — Regex/OCR-Zeilen-Hints → BO2-Schema (ohne LLM).
 * Usage: node bin/beleg-ocr-bo2-receipt-hints-selftest.mjs
 */
import { analyzeInvoiceText } from '../assets/js/vendor/receipt-parse-core.mjs';
import {
  mapReceiptHintsToBo2Extract,
  parseEInvoiceXml,
} from '../assets/js/vendor/dg-einvoice-xml.mjs';

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

const sampleText = `
Muster Lieferant AG
Rechnung Nr. R-2026-55
Rechnungsdatum 10.11.2026
2,00 Stück Kupferkabel 5m 12,50 25,00
1,00 Stück Montagepauschale Beratung 40,00 40,00
Netto 65,00
MwSt 19% 12,35
Gesamtbetrag 77,35 €
IBAN DE89370400440532013000
`;

const analysis = analyzeInvoiceText(sampleText);
if (!Array.isArray(analysis.items) || analysis.items.length < 2) {
  fail(`expected >=2 hint items, got ${analysis.items?.length}`);
}

const suggestion = {
  source: 'textlayer',
  supplier: analysis.supplier || 'Muster Lieferant AG',
  invoiceNumber: analysis.invoiceNumber || '',
  invoiceDate: '2026-11-10',
  grossAmount: analysis.grossAmount || '77.35',
  netAmount: '65.00',
  vatAmount: '12.35',
  taxPercent: '19',
  items: analysis.items,
};

const extract = mapReceiptHintsToBo2Extract(suggestion, sampleText, 'pdf_text');
if (extract.schema_version !== 1) fail('schema_version');
if (extract.source !== 'pdf_text') fail(`source=${extract.source}`);
if (extract.lines.length < 2) fail(`lines=${extract.lines.length}`);
const titles = extract.lines.map((l) => l.title).join(' | ');
if (!/Kupferkabel/i.test(titles)) fail(`missing Kupferkabel in ${titles}`);
if (!extract.lines.some((l) => l.quantity === '2' || l.quantity === '2.00')) {
  fail(`expected qty 2 on cable line: ${JSON.stringify(extract.lines[0])}`);
}

const ocrExtract = mapReceiptHintsToBo2Extract({ ...suggestion, source: 'ocr' }, sampleText, 'ocr');
if (ocrExtract.source !== 'ocr') fail('ocr source');

// E-Rechnung mit Zeilen darf nicht von Hints überschrieben werden
const ciiXml = `<?xml version="1.0"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
 xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
  <rsm:ExchangedDocument><ram:ID>X-1</ram:ID></rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:IncludedSupplyChainTradeLineItem>
      <ram:SpecifiedTradeProduct><ram:SellerAssignedID>A1</ram:SellerAssignedID><ram:Name>Nur XML Position</ram:Name></ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeDelivery><ram:BilledQuantity unitCode="C62">1</ram:BilledQuantity></ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement>
        <ram:SpecifiedTradeSettlementLineMonetarySummation><ram:LineTotalAmount>10.00</ram:LineTotalAmount></ram:SpecifiedTradeSettlementLineMonetarySummation>
      </ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:ApplicableHeaderTradeAgreement><ram:SellerTradeParty><ram:Name>XML GmbH</ram:Name></ram:SellerTradeParty></ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:TaxBasisTotalAmount>10.00</ram:TaxBasisTotalAmount>
        <ram:GrandTotalAmount>11.90</ram:GrandTotalAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>`;

const parsed = parseEInvoiceXml(ciiXml, 'x.xml');
const guarded = mapReceiptHintsToBo2Extract(parsed, sampleText, 'pdf_text');
if (guarded.source !== 'einvoice_xml') fail(`e-invoice must stay einvoice_xml, got ${guarded.source}`);
if (!guarded.lines.some((l) => /Nur XML Position/i.test(l.title))) {
  fail('e-invoice lines must not be replaced by OCR hints');
}

console.log('OK BO2c receipt/OCR hints → BO2 extract');
process.exit(0);
