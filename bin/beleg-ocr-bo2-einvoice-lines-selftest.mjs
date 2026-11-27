#!/usr/bin/env node
/**
 * BO2b — E-Rechnung-XML-Zeilen → BO2-Extrakt-Schema.
 * Usage: node bin/beleg-ocr-bo2-einvoice-lines-selftest.mjs
 */
import {
  parseEInvoiceXml,
  mapEInvoiceToBo2Extract,
  looksLikeEInvoiceXml,
} from '../assets/js/vendor/dg-einvoice-xml.mjs';

function fail(msg) {
  console.error(`FAIL: ${msg}`);
  process.exit(1);
}

const ciiXml = `<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
 xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
  <rsm:ExchangedDocument>
    <ram:ID>RE-1001</ram:ID>
    <ram:IssueDateTime><ram:DateTimeString>20261115</ram:DateTimeString></ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:IncludedSupplyChainTradeLineItem>
      <ram:SpecifiedTradeProduct>
        <ram:SellerAssignedID>ART-9</ram:SellerAssignedID>
        <ram:Name>Kabeltrommel 25m</ram:Name>
      </ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeAgreement>
        <ram:NetPriceProductTradePrice><ram:ChargeAmount>40.00</ram:ChargeAmount></ram:NetPriceProductTradePrice>
      </ram:SpecifiedLineTradeAgreement>
      <ram:SpecifiedLineTradeDelivery>
        <ram:BilledQuantity unitCode="C62">2</ram:BilledQuantity>
      </ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement>
        <ram:ApplicableTradeTax><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>
        <ram:SpecifiedTradeSettlementLineMonetarySummation>
          <ram:LineTotalAmount>80.00</ram:LineTotalAmount>
        </ram:SpecifiedTradeSettlementLineMonetarySummation>
      </ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:IncludedSupplyChainTradeLineItem>
      <ram:SpecifiedTradeProduct>
        <ram:Name>Montage vor Ort</ram:Name>
      </ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeAgreement>
        <ram:NetPriceProductTradePrice><ram:ChargeAmount>50.00</ram:ChargeAmount></ram:NetPriceProductTradePrice>
      </ram:SpecifiedLineTradeAgreement>
      <ram:SpecifiedLineTradeDelivery>
        <ram:BilledQuantity unitCode="HUR">1</ram:BilledQuantity>
      </ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement>
        <ram:ApplicableTradeTax><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>
        <ram:SpecifiedTradeSettlementLineMonetarySummation>
          <ram:LineTotalAmount>50.00</ram:LineTotalAmount>
        </ram:SpecifiedTradeSettlementLineMonetarySummation>
      </ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty><ram:Name>Demo Lieferant GmbH</ram:Name></ram:SellerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:ApplicableTradeTax><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:TaxBasisTotalAmount>130.00</ram:TaxBasisTotalAmount>
        <ram:TaxTotalAmount>24.70</ram:TaxTotalAmount>
        <ram:GrandTotalAmount>154.70</ram:GrandTotalAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>`;

const ublXml = `<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
 xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
 xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:ID>XR-77</cbc:ID>
  <cbc:IssueDate>2026-11-20</cbc:IssueDate>
  <cac:AccountingSupplierParty>
    <cac:Party><cac:PartyName><cbc:Name>UBL Lieferant</cbc:Name></cac:PartyName></cac:Party>
  </cac:AccountingSupplierParty>
  <cac:InvoiceLine>
    <cbc:ID>1</cbc:ID>
    <cbc:InvoicedQuantity unitCode="C62">3</cbc:InvoicedQuantity>
    <cbc:LineExtensionAmount>30.00</cbc:LineExtensionAmount>
    <cac:Item>
      <cbc:Name>Schrauben M6</cbc:Name>
      <cac:SellersItemIdentification><cbc:ID>SK-6</cbc:ID></cac:SellersItemIdentification>
      <cac:ClassifiedTaxCategory><cbc:Percent>19</cbc:Percent></cac:ClassifiedTaxCategory>
    </cac:Item>
    <cac:Price><cbc:PriceAmount>10.00</cbc:PriceAmount></cac:Price>
  </cac:InvoiceLine>
  <cac:LegalMonetaryTotal>
    <cbc:TaxExclusiveAmount>30.00</cbc:TaxExclusiveAmount>
    <cbc:TaxInclusiveAmount>35.70</cbc:TaxInclusiveAmount>
    <cbc:PayableAmount>35.70</cbc:PayableAmount>
  </cac:LegalMonetaryTotal>
</Invoice>`;

if (!looksLikeEInvoiceXml(ciiXml) || !looksLikeEInvoiceXml(ublXml)) {
  fail('fixture not recognized as e-invoice');
}

const cii = parseEInvoiceXml(ciiXml, 'cii-demo.xml');
if (!cii.extract || cii.extract.schema_version !== 1) fail('CII missing BO2 extract');
if (cii.extract.source !== 'einvoice_xml') fail('CII extract source');
if (cii.extract.lines.length !== 2) fail(`CII expected 2 lines, got ${cii.extract.lines.length}`);
const [product, service] = cii.extract.lines;
if (product.catalog_kind !== 'product') fail(`CII line1 kind=${product.catalog_kind}`);
if (product.sku !== 'ART-9') fail(`CII line1 sku=${product.sku}`);
if (product.quantity !== '2') fail(`CII line1 qty=${product.quantity}`);
if (product.line_net !== '80.00') fail(`CII line1 net=${product.line_net}`);
if (service.catalog_kind !== 'service') fail(`CII line2 kind=${service.catalog_kind}`);
if (service.title !== 'Montage vor Ort') fail('CII line2 title');
if (cii.extract.amount_check.ok !== true) fail(`CII amount_check ${JSON.stringify(cii.extract.amount_check)}`);

const mapped = mapEInvoiceToBo2Extract(cii);
if (mapped.lines.length !== 2) fail('mapEInvoiceToBo2Extract lines');

const ubl = parseEInvoiceXml(ublXml, 'ubl-demo.xml');
if (ubl.extract.lines.length !== 1) fail(`UBL expected 1 line, got ${ubl.extract.lines.length}`);
const ublLine = ubl.extract.lines[0];
if (ublLine.catalog_kind !== 'product') fail(`UBL kind=${ublLine.catalog_kind}`);
if (ublLine.sku !== 'SK-6') fail(`UBL sku=${ublLine.sku}`);
if (ublLine.quantity !== '3') fail(`UBL qty=${ublLine.quantity}`);
if (ublLine.line_net !== '30.00') fail(`UBL net=${ublLine.line_net}`);

console.log('OK BO2b e-invoice lines → BO2 extract');
process.exit(0);
