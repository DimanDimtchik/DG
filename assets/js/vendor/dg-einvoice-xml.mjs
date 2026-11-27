/**
 * E-Rechnung (ZUGFeRD / Factur-X / XRechnung / UBL) XML -> strukturierte Felder.
 * Portiert aus der WG-App (pdf-extract-core.mjs), reine Regex-Logik ohne Node-Abhängigkeiten.
 */
import { analyzeInvoiceText, parseReceiptAmount } from './receipt-parse-core.mjs';

function decodeXmlEntities(value) {
  return value
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

export function getXmlValues(xml, localName) {
  const pattern = new RegExp(
    `<(?:[A-Za-z0-9_-]+:)?${localName}\\b[^>]*>([\\s\\S]*?)<\\/(?:[A-Za-z0-9_-]+:)?${localName}>`,
    'gi',
  );
  const values = [];
  let match = pattern.exec(xml);
  while (match) {
    values.push(decodeXmlEntities(match[1]));
    match = pattern.exec(xml);
  }
  return values.filter(Boolean);
}

export function getXmlFirstValue(xml, localNames) {
  for (const localName of localNames) {
    const value = getXmlValues(xml, localName)[0];
    if (value) return value;
  }
  return '';
}

export function getXmlSections(xml, localName) {
  const pattern = new RegExp(
    `<(?:[A-Za-z0-9_-]+:)?${localName}\\b[^>]*>([\\s\\S]*?)<\\/(?:[A-Za-z0-9_-]+:)?${localName}>`,
    'gi',
  );
  const sections = [];
  let match = pattern.exec(xml);
  while (match) {
    sections.push(match[1]);
    match = pattern.exec(xml);
  }
  return sections;
}

export function getXmlAmount(xml, localNames) {
  const value = getXmlFirstValue(xml, localNames);
  const amount = value ? parseReceiptAmount(value) : null;
  return amount === null ? '' : amount.toFixed(2);
}

/** Erkennt, ob ein Text ein E-Rechnungs-XML (CII oder UBL) ist. */
export function looksLikeEInvoiceXml(text) {
  return /CrossIndustryInvoice|<(?:[A-Za-z0-9_-]+:)?Invoice\b|urn:cen\.eu:en16931|urn:ferd:|factur-x|zugferd|xrechnung/i.test(text);
}

/** Liefert das Element ExchangedDocument (nicht ExchangedDocumentContext). */
function getExchangedDocumentSection(xml) {
  const pattern = /<(?:[A-Za-z0-9_-]+:)?ExchangedDocument\b([^>]*)>([\s\S]*?)<\/(?:[A-Za-z0-9_-]+:)?ExchangedDocument>/gi;
  let match = pattern.exec(xml);
  while (match) {
    const attrs = match[1] || '';
    if (!/Context/i.test(attrs)) {
      return match[2];
    }
    match = pattern.exec(xml);
  }
  return '';
}

function isPlausibleInvoiceNumber(value) {
  const v = String(value || '').trim();
  if (v.length < 3 || v.length > 40) return false;
  if (/^urn:/i.test(v) || /factur|zugferd|xrechnung|en16931/i.test(v)) return false;
  return /^[A-Z0-9][A-Z0-9\-/.]{2,}$/i.test(v);
}

function findInvoiceNumberInXml(xml) {
  const exchanged = getExchangedDocumentSection(xml);
  const fromDoc = getXmlFirstValue(exchanged, ['ID']);
  if (isPlausibleInvoiceNumber(fromDoc)) return fromDoc;
  for (const value of getXmlValues(xml, 'ID')) {
    if (isPlausibleInvoiceNumber(value)) return value;
  }
  return '';
}

function findTaxNumberInXml(xml) {
  const sections = getXmlSections(xml, 'SpecifiedTaxRegistration');
  for (const section of sections) {
    if (/schemeID\s*=\s*["']FC["']/i.test(section)) {
      const id = getXmlFirstValue(section, ['ID']);
      if (id) return id;
    }
  }
  for (const section of sections) {
    const id = getXmlFirstValue(section, ['ID']);
    if (id && /[0-9/]/.test(id) && !/^DE[0-9]{9}$/i.test(id.replace(/\s/g, ''))) {
      return id;
    }
  }
  return '';
}

function parseSellerAddress(sellerSection) {
  const addrSection = getXmlSections(sellerSection, 'PostalTradeAddress')[0] || '';
  return {
    street: getXmlFirstValue(addrSection, ['LineOne', 'StreetName']),
    city: getXmlFirstValue(addrSection, ['CityName']),
    postal: getXmlFirstValue(addrSection, ['PostcodeCode', 'PostalZone']),
    country: getXmlFirstValue(addrSection, ['CountryID']),
  };
}

function getSellerPhone(sellerSection) {
  for (const section of getXmlSections(sellerSection, 'DefinedTradeContact')) {
    const phone = getXmlFirstValue(section, ['CompleteNumber']);
    if (phone && phone.replace(/\D/g, '').length >= 6) return phone;
  }
  return '';
}

export function findCompanyPhoneInText(text) {
  if (!text) return '';
  const noteMatch = text.match(/\bT\s*(\+?[\d\s()\-/.]+?)(?:\s*-\s*F\b|\s*-\s*info@|\s+info@|$)/i);
  if (noteMatch?.[1]) return noteMatch[1].replace(/[\s\-–]+$/g, '').trim();
  const telMatch = text.match(/(?:telefon|tel\.?)[:\s]+([+\d\s()\-/.]+?)(?:\s*-\s*F\b|$)/i);
  if (telMatch?.[1]) return telMatch[1].replace(/[\s\-–]+$/g, '').trim();
  return '';
}

export function findWebsiteInText(text) {
  if (!text) return '';
  const matches = text.match(/(?:https?:\/\/)?(?:www\.)[a-z0-9][-a-z0-9.]*\.[a-z]{2,}/gi) || [];
  for (const raw of matches) {
    if (/w3\.org|ferd-net|zeppelin|cen\.eu|xml\.org/i.test(raw)) continue;
    return /^https?:\/\//i.test(raw) ? raw : `https://${raw}`;
  }
  return '';
}

export function findCommercialRegisterInText(text) {
  if (!text) return '';
  const match = text.match(/Amtsgericht\s+[^,;\n]+?\s+(?:HRA|HRB)\s+\d+/i);
  return match ? match[0].replace(/\s+/g, ' ').trim() : '';
}

export function findWeeeInText(text) {
  if (!text) return '';
  const match = text.match(/WEEE[\s\-]*(?:Registrierungsnr\.?|Reg\.?\s*Nr\.?)[:\s]*([A-Z]{2}\s?\d[\d\s]{4,})/i);
  return match?.[1] ? match[1].replace(/\s+/g, ' ').trim() : '';
}

export function findStreetInText(text) {
  if (!text) return '';
  const haus = text.match(/hausanschrift[:\s]+([^,\n-]+)/i);
  if (haus?.[1]) return haus[1].trim();
  const streetLine = text.match(/(?:straße|str\.|strasse|weg|platz|allee)\s*\d+[^,\n]*/i);
  return streetLine ? streetLine[0].trim() : '';
}

export function findCityPostalInText(text) {
  if (!text) return { city: '', postal: '' };
  const haus = text.match(/hausanschrift[^0-9]*(\d{5})\s+([A-Za-zÄÖÜäöüß][A-Za-zÄÖÜäöüß\-]+(?:[\s-][A-Za-zÄÖÜäöüß\-]+)*)/i);
  if (haus) {
    return { postal: haus[1], city: haus[2].trim() };
  }
  const plzCity = text.match(/\b(\d{5})\s+([A-Za-zÄÖÜäöüß][A-Za-zÄÖÜäöüß\-]+(?:[\s-][A-Za-zÄÖÜäöüß\-]+)*)/);
  if (plzCity) {
    return { postal: plzCity[1], city: plzCity[2].trim() };
  }
  return { city: '', postal: '' };
}

export function findCustomerNumberInText(text) {
  if (!text) return '';
  const patterns = [
    /kunden[\s.\-]*(?:nummer|nr\.?)[:\s]*([A-Z0-9][A-Z0-9\-/.]{2,})/i,
    /kunden[\s.\-]*nr\.?[:\s]*([0-9]{5,})/i,
    /(?:ihre\s+)?kunden[\s.\-]*(?:nummer|nr\.?)[:\s]*([0-9]{5,})/i,
  ];
  for (const pattern of patterns) {
    const match = text.match(pattern);
    if (match?.[1]) return match[1].replace(/\s/g, '').trim();
  }
  return '';
}

function findCustomerNumberInXml(xml) {
  const buyerSection =
    getXmlSections(xml, 'BuyerTradeParty')[0] ||
    getXmlSections(xml, 'AccountingCustomerParty')[0] ||
    '';
  for (const value of getXmlValues(buyerSection, 'ID')) {
    const compact = value.replace(/\s/g, '');
    if (/^\d{5,}$/.test(compact)) {
      return compact.replace(/^0+/, '') || compact;
    }
  }
  return findCustomerNumberInText(xml);
}

export function extractContactMasterFromText(text) {
  const { city, postal } = findCityPostalInText(text);
  const ibanMatch = text.match(/\b([A-Z]{2}\s?[0-9]{2}(?:\s?[0-9A-Z]{4}){2,7}\s?[0-9A-Z]{0,4})\b/i);
  const bicMatch = text.match(/\b([A-Z]{4}[A-Z]{2}[A-Z0-9]{2}(?:[A-Z0-9]{3})?)\b/);
  return {
    taxNumber: text.match(/(?:steuer(?:nummer|-nr\.?)|st\.?\s*nr\.?)[:\s]*([0-9/ \-]{5,})/i)?.[1]?.trim() || '',
    customerNumber: findCustomerNumberInText(text),
    phone: findCompanyPhoneInText(text),
    website: findWebsiteInText(text),
    commercialRegister: findCommercialRegisterInText(text),
    weeeRegistration: findWeeeInText(text),
    street: findStreetInText(text),
    city,
    postal,
    iban: ibanMatch?.[1] ? ibanMatch[1].replace(/\s+/g, '').toUpperCase() : '',
    bic: bicMatch?.[1] ? bicMatch[1].toUpperCase() : '',
  };
}

function parseNotesMasterData(xml) {
  const notes = getXmlValues(xml, 'Content');
  const joined = notes.join('\n');
  return {
    phone: findCompanyPhoneInText(joined),
    website: findWebsiteInText(joined),
    commercialRegister: findCommercialRegisterInText(joined),
    weeeRegistration: findWeeeInText(joined),
  };
}

function findVatPercentInXml(xml) {
  const sections = getXmlSections(xml, 'ApplicableTradeTax');
  for (const section of sections) {
    const raw = getXmlFirstValue(section, ['RateApplicablePercent', 'Percent']);
    if (raw) {
      const n = Math.round(parseFloat(raw.replace(',', '.')));
      if (Number.isFinite(n)) return String(n);
    }
  }
  return '';
}

function moneyOrEmpty(value) {
  if (value === '' || value == null) return '';
  const n = typeof value === 'number' ? value : parseReceiptAmount(String(value));
  return n === null ? '' : n.toFixed(2);
}

function quantityOrEmpty(value) {
  if (value === '' || value == null) return '';
  const n = parseFloat(String(value).replace(',', '.').replace(/[^\d.-]/g, ''));
  if (!Number.isFinite(n) || n === 0) return '';
  return String(n);
}

function unitFromCode(unitCode, fallback = 'Stück') {
  const code = String(unitCode || '').toUpperCase();
  const map = {
    C62: 'Stück',
    H87: 'Stück',
    EA: 'Stück',
    KGM: 'kg',
    GRM: 'g',
    LTR: 'l',
    MTR: 'm',
    MTK: 'm²',
    MTQ: 'm³',
    HUR: 'Stunden',
    MIN: 'Minuten',
    DAY: 'Tage',
    MON: 'Monate',
    SET: 'Set',
    PR: 'Paar',
  };
  return map[code] || fallback;
}

/** Heuristik Artikel vs. Leistung (BO2 catalog_kind). */
export function inferCatalogKind({ sku, title, unitCode, unit }) {
  const text = `${title || ''} ${sku || ''}`.toLowerCase();
  const code = String(unitCode || '').toUpperCase();
  if (['HUR', 'MIN', 'DAY', 'MON'].includes(code)) return 'service';
  if (/dienstleistung|arbeitszeit|stunde|beratung|montage|pauschale|honorar|service/.test(text)) {
    return 'service';
  }
  if (sku || ['C62', 'H87', 'KGM', 'GRM', 'LTR', 'MTR', 'MTK', 'MTQ', 'SET', 'PR'].includes(code)) {
    return 'product';
  }
  if (/artikel|material|ware|produkt|stück/.test(text)) return 'product';
  if (unit && /stunde|tag|monat/i.test(unit)) return 'service';
  return 'service';
}

function taxPercentFromLineSection(section) {
  const raw =
    getXmlFirstValue(section, ['RateApplicablePercent', 'Percent']) ||
    getXmlFirstValue(getXmlSections(section, 'ApplicableTradeTax')[0] || '', ['RateApplicablePercent', 'Percent']) ||
    getXmlFirstValue(getXmlSections(section, 'ClassifiedTaxCategory')[0] || '', ['Percent']);
  if (!raw) return null;
  const n = Math.round(parseFloat(String(raw).replace(',', '.')));
  return Number.isFinite(n) ? n : null;
}

function parseQuantityWithUnit(section) {
  const billedMatch = section.match(
    /<(?:[A-Za-z0-9_-]+:)?(?:BilledQuantity|InvoicedQuantity)\b([^>]*)>([\s\S]*?)<\/(?:[A-Za-z0-9_-]+:)?(?:BilledQuantity|InvoicedQuantity)>/i,
  );
  let qty = '';
  let unitCode = '';
  if (billedMatch) {
    const attrs = billedMatch[1] || '';
    const unitAttr = attrs.match(/unitCode\s*=\s*["']([^"']+)["']/i);
    unitCode = unitAttr ? unitAttr[1] : '';
    qty = quantityOrEmpty(decodeXmlEntities(billedMatch[2]));
  }
  if (!qty) {
    qty = quantityOrEmpty(getXmlFirstValue(section, ['BilledQuantity', 'InvoicedQuantity', 'BasisQuantity']));
  }
  if (!unitCode) {
    const basis = section.match(
      /<(?:[A-Za-z0-9_-]+:)?BasisQuantity\b([^>]*)>/i,
    );
    if (basis) {
      const unitAttr = (basis[1] || '').match(/unitCode\s*=\s*["']([^"']+)["']/i);
      if (unitAttr) unitCode = unitAttr[1];
    }
  }
  return { quantity: qty || '1', unitCode };
}

/**
 * Eine CII-/UBL-Positionssektion → BO2-Zeile.
 * @returns {object|null}
 */
export function parseEInvoiceLineSection(section, index) {
  const productSection =
    getXmlSections(section, 'SpecifiedTradeProduct')[0] ||
    getXmlSections(section, 'Item')[0] ||
    section;
  const title =
    getXmlFirstValue(productSection, ['Name', 'Description']) ||
    getXmlFirstValue(section, ['Name', 'Description']);
  if (!title) return null;

  const sku =
    getXmlFirstValue(productSection, ['SellerAssignedID', 'BuyerAssignedID']) ||
    getXmlFirstValue(getXmlSections(productSection, 'SellersItemIdentification')[0] || '', ['ID']) ||
    getXmlFirstValue(getXmlSections(productSection, 'BuyersItemIdentification')[0] || '', ['ID']) ||
    '';

  const { quantity, unitCode } = parseQuantityWithUnit(section);
  const unit = unitFromCode(unitCode);

  const lineNetRaw =
    parseReceiptAmount(getXmlFirstValue(section, ['LineTotalAmount', 'LineExtensionAmount']));
  const unitPriceRaw =
    parseReceiptAmount(getXmlFirstValue(section, ['ChargeAmount', 'PriceAmount']));
  const taxPercent = taxPercentFromLineSection(section);
  const lineNet = lineNetRaw !== null ? lineNetRaw : (unitPriceRaw !== null && quantity
    ? unitPriceRaw * parseFloat(quantity)
    : null);
  let unitPriceNet = unitPriceRaw;
  if (unitPriceNet === null && lineNet !== null && quantity) {
    const q = parseFloat(quantity);
    if (q) unitPriceNet = lineNet / q;
  }

  let lineGross = '';
  if (lineNet !== null) {
    const rate = taxPercent != null ? taxPercent : 19;
    lineGross = (lineNet * (1 + rate / 100)).toFixed(2);
  }

  const catalogKind = inferCatalogKind({ sku, title, unitCode, unit });
  const line = {
    line_no: index + 1,
    catalog_kind: catalogKind,
    sku: sku || '',
    title,
    quantity: quantity || '1',
    unit,
    unit_price_net: moneyOrEmpty(unitPriceNet),
    line_net: moneyOrEmpty(lineNet),
    line_gross: lineGross,
    tax_percent: taxPercent != null ? taxPercent : 19,
    confidence: sku || lineNet !== null ? 0.9 : 0.7,
    match_article_id: null,
  };

  if (!line.title) return null;
  if (line.line_net === '' && line.unit_price_net === '') return null;
  return line;
}

export function parseEInvoiceLines(xml) {
  const compactXml = String(xml || '').replace(/\r/g, '');
  const lineSections = [
    ...getXmlSections(compactXml, 'IncludedSupplyChainTradeLineItem'),
    ...getXmlSections(compactXml, 'InvoiceLine'),
  ];
  const lines = [];
  lineSections.forEach((section, index) => {
    const line = parseEInvoiceLineSection(section, index);
    if (line) lines.push(line);
  });
  // line_no neu durchnummerieren (nach Filter)
  return lines.map((line, idx) => ({ ...line, line_no: idx + 1 }));
}

function amountCheckForLines(lines, netTotal) {
  const warnings = [];
  const sumNet = lines.reduce((acc, line) => {
    const n = parseReceiptAmount(String(line.line_net || ''));
    return n === null ? acc : acc + n;
  }, 0);
  const headNet = parseReceiptAmount(String(netTotal || ''));
  if (headNet === null || lines.length === 0) {
    return { ok: true, detail: '', warnings };
  }
  const delta = Math.abs(sumNet - headNet);
  if (delta > 0.05) {
    const detail = `Zeilensumme Netto ${sumNet.toFixed(2)} weicht vom Kopf ${headNet.toFixed(2)} ab.`;
    warnings.push(detail);
    return { ok: false, detail, warnings };
  }
  return { ok: true, detail: '', warnings };
}

/**
 * BO2-Extrakt aus geparster E-Rechnung (Kopf + lines[]).
 * @param {object} parsed Ergebnis von parseEInvoiceXml
 */
export function mapEInvoiceToBo2Extract(parsed) {
  const lines = Array.isArray(parsed?.lines) && parsed.lines.length
    ? parsed.lines
    : (Array.isArray(parsed?.items)
      ? parsed.items.map((item, idx) => ({
          line_no: idx + 1,
          catalog_kind: inferCatalogKind({ title: item.description || item.title || '' }),
          sku: item.sku || '',
          title: item.description || item.title || '',
          quantity: item.quantity || '1',
          unit: item.unit || 'Stück',
          unit_price_net: moneyOrEmpty(item.unit_price_net),
          line_net: moneyOrEmpty(item.amount ?? item.line_net),
          line_gross: moneyOrEmpty(item.line_gross),
          tax_percent: Number(item.tax_percent) || Number(parsed.taxPercent) || 19,
          confidence: 0.5,
          match_article_id: null,
        }))
      : []);
  const check = amountCheckForLines(lines, parsed?.netAmount || '');
  return {
    schema_version: 1,
    source: 'einvoice_xml',
    supplier_name: parsed?.supplier || '',
    invoice_number: parsed?.invoiceNumber || '',
    invoice_date: parsed?.invoiceDate || '',
    currency: 'EUR',
    gross_total: moneyOrEmpty(parsed?.grossAmount) || String(parsed?.grossAmount || ''),
    net_total: moneyOrEmpty(parsed?.netAmount) || String(parsed?.netAmount || ''),
    vat_total: moneyOrEmpty(parsed?.vatAmount) || String(parsed?.vatAmount || ''),
    tax_rates: parsed?.taxPercent
      ? [{ percent: Number(parsed.taxPercent) || 19, net: String(parsed.netAmount || ''), vat: String(parsed.vatAmount || '') }]
      : [],
    iban: parsed?.iban || '',
    bic: parsed?.bic || '',
    vat_id: parsed?.vatId || '',
    tax_number: parsed?.taxNumber || '',
    lines,
    warnings: check.warnings || [],
    amount_check: { ok: check.ok, detail: check.detail || '' },
  };
}

export function parseEInvoiceXml(xml, fileName) {
  const compactXml = xml.replace(/\r/g, '');
  const exchangedDoc = getExchangedDocumentSection(compactXml);
  const sellerSection =
    getXmlSections(compactXml, 'SellerTradeParty')[0] ||
    getXmlSections(compactXml, 'AccountingSupplierParty')[0] ||
    '';
  const buyerSection =
    getXmlSections(compactXml, 'BuyerTradeParty')[0] ||
    getXmlSections(compactXml, 'AccountingCustomerParty')[0] ||
    '';
  const monetarySection =
    getXmlSections(compactXml, 'SpecifiedTradeSettlementHeaderMonetarySummation')[0] ||
    getXmlSections(compactXml, 'LegalMonetaryTotal')[0] ||
    compactXml;
  const paymentSection = getXmlSections(compactXml, 'PayeePartyCreditorFinancialAccount')[0] || compactXml;
  const sellerName = getXmlFirstValue(sellerSection, ['Name', 'RegistrationName']);
  const buyerName = getXmlFirstValue(buyerSection, ['Name', 'RegistrationName']);
  const sellerAddress = parseSellerAddress(sellerSection);
  const notesMaster = parseNotesMasterData(compactXml);
  const sellerPhone = notesMaster.phone || getSellerPhone(sellerSection);
  const invoiceNumber = findInvoiceNumberInXml(compactXml);
  const invoiceDate =
    getXmlFirstValue(exchangedDoc, ['DateTimeString', 'IssueDate']) ||
    getXmlFirstValue(compactXml, ['IssueDate', 'DateTimeString']);
  const iban = getXmlFirstValue(paymentSection, ['IBANID', 'ID']);
  const bic = getXmlFirstValue(compactXml, ['BICID']);
  const vatCandidates = getXmlValues(compactXml, 'ID').filter((value) => /^DE[0-9]{9}$/i.test(value.replace(/\s+/g, '')));
  const vatId = vatCandidates[0]?.replace(/\s+/g, '') ?? '';
  const taxNumber = findTaxNumberInXml(compactXml);
  const customerNumber = findCustomerNumberInXml(compactXml);
  const grossAmount = getXmlAmount(monetarySection, ['GrandTotalAmount', 'DuePayableAmount', 'PayableAmount', 'TaxInclusiveAmount']);
  const netAmount = getXmlAmount(monetarySection, ['TaxBasisTotalAmount', 'LineTotalAmount', 'TaxExclusiveAmount']);
  const vatAmount = getXmlAmount(monetarySection, ['TaxTotalAmount']) || getXmlAmount(compactXml, ['TaxTotalAmount', 'CalculatedAmount']);
  const taxPercent = findVatPercentInXml(compactXml);

  const lines = parseEInvoiceLines(compactXml);
  const recognizedItems = lines.map((line) => ({
    id: `einvoice-${line.line_no}`,
    description: line.title,
    amount: parseReceiptAmount(line.line_net) ?? 0,
    sku: line.sku,
    quantity: line.quantity,
    unit: line.unit,
    unit_price_net: line.unit_price_net,
    line_net: line.line_net,
    line_gross: line.line_gross,
    tax_percent: line.tax_percent,
    catalog_kind: line.catalog_kind,
  }));

  const normalizedLines = [
    `eRechnung: ${fileName}`,
    sellerName ? `Aussteller: ${sellerName}` : '',
    buyerName ? `Empfaenger: ${buyerName}` : '',
    invoiceNumber ? `Rechnungsnummer: ${invoiceNumber}` : '',
    invoiceDate ? `Rechnungsdatum: ${invoiceDate}` : '',
    taxNumber ? `Steuernummer: ${taxNumber}` : '',
    customerNumber ? `Kundennummer: ${customerNumber}` : '',
    vatId ? `USt-ID: ${vatId}` : '',
    sellerPhone ? `Telefon: ${sellerPhone}` : '',
    notesMaster.website ? `Website: ${notesMaster.website}` : '',
    sellerAddress.street ? `Straße: ${sellerAddress.street}` : '',
    sellerAddress.postal || sellerAddress.city
      ? `Ort: ${[sellerAddress.postal, sellerAddress.city].filter(Boolean).join(' ')}`
      : '',
    notesMaster.commercialRegister ? `Handelsregister: ${notesMaster.commercialRegister}` : '',
    notesMaster.weeeRegistration ? `WEEE: ${notesMaster.weeeRegistration}` : '',
    iban ? `IBAN: ${iban}` : '',
    bic ? `BIC: ${bic}` : '',
    netAmount ? `Netto: ${netAmount}` : '',
    vatAmount ? `MwSt: ${vatAmount}` : '',
    grossAmount ? `Gesamt: ${grossAmount}` : '',
    ...lines.map((line) => `${line.title} ${line.line_net || line.unit_price_net || ''}`.trim()),
  ].filter(Boolean);

  const analysis = analyzeInvoiceText(normalizedLines.join('\n'));

  const parsed = {
    source: 'einvoice',
    supplier: sellerName || analysis.supplier || '',
    invoiceNumber: invoiceNumber || analysis.invoiceNumber || '',
    invoiceDate: normalizeIsoDate(invoiceDate) || analysis.invoiceDate || '',
    grossAmount: grossAmount || analysis.grossAmount || '',
    netAmount: netAmount || '',
    vatAmount: vatAmount || '',
    taxPercent: taxPercent ? String(Math.round(parseFloat(taxPercent.replace(',', '.')))) : '',
    iban: cleanIban(iban),
    bic: bic || '',
    vatId,
    taxNumber,
    customerNumber,
    phone: sellerPhone,
    website: notesMaster.website,
    street: sellerAddress.street,
    city: sellerAddress.city,
    postal: sellerAddress.postal,
    commercialRegister: notesMaster.commercialRegister,
    weeeRegistration: notesMaster.weeeRegistration || findWeeeInText(compactXml),
    items: recognizedItems,
    lines,
    itemCount: lines.length,
    rawText: normalizedLines.join('\n'),
  };
  parsed.extract = mapEInvoiceToBo2Extract(parsed);
  return parsed;
}

function normalizeIsoDate(value) {
  if (!value) return '';
  const compact = value.replace(/[^0-9]/g, '');
  if (/^\d{8}$/.test(compact)) {
    return `${compact.slice(0, 4)}-${compact.slice(4, 6)}-${compact.slice(6, 8)}`;
  }
  const iso = value.match(/(\d{4})-(\d{2})-(\d{2})/);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
  const de = value.match(/(\d{1,2})[.\/](\d{1,2})[.\/](\d{2,4})/);
  if (de) {
    const year = de[3].length === 2 ? `20${de[3]}` : de[3];
    return `${year}-${de[2].padStart(2, '0')}-${de[1].padStart(2, '0')}`;
  }
  return '';
}

function cleanIban(value) {
  if (!value) return '';
  const cleaned = value.replace(/\s+/g, '').toUpperCase();
  return /^[A-Z]{2}[0-9A-Z]{13,32}$/.test(cleaned) ? cleaned : '';
}
