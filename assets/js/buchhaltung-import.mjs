/**
 * Beleg-Import: analysiert eine hochgeladene Datei im Browser und schlägt Belegfelder vor.
 *  - E-Rechnung (ZUGFeRD/Factur-X/XRechnung/UBL): eingebettetes XML lesen (kein OCR)
 *  - Digitales PDF mit Textlayer: Text auslesen (kein OCR)
 *  - Scan-PDF / Foto: OCR via Tesseract.js
 * Die Originaldatei wird beim Hochladen sofort am Beleg gespeichert (Beleg-Entwurf).
 */
import { analyzeInvoiceText, parseReceiptAmount } from './vendor/receipt-parse-core.mjs';
import {
  parseEInvoiceXml,
  looksLikeEInvoiceXml,
  extractContactMasterFromText,
} from './vendor/dg-einvoice-xml.mjs';

const PDFJS_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.6.82/build/pdf.min.mjs';
const PDFJS_WORKER_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.6.82/build/pdf.worker.min.mjs';
const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
const importConfig = window.dgBuchhaltungBelege || {};

function getVoucherId() {
  const hidden = document.querySelector('#dg-voucher-form input[name="id"]');
  const fromField = Number(hidden?.value || 0);
  if (fromField > 0) return fromField;
  return Number(importConfig.voucherId || 0);
}

function setVoucherId(voucherId) {
  const form = $('dg-voucher-form');
  if (!form || voucherId < 1) return;
  let hidden = form.querySelector('input[name="id"]');
  if (!hidden) {
    hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'id';
    form.appendChild(hidden);
  }
  hidden.value = String(voucherId);
  let draftHidden = form.querySelector('input[name="draft_voucher_id"]');
  if (!draftHidden) {
    draftHidden = document.createElement('input');
    draftHidden.type = 'hidden';
    draftHidden.name = 'draft_voucher_id';
    draftHidden.id = 'dg-voucher-draft-id';
    form.appendChild(draftHidden);
  }
  draftHidden.value = String(voucherId);
  importConfig.voucherId = voucherId;
  if (window.dgBuchhaltungBelege) {
    window.dgBuchhaltungBelege.voucherId = voucherId;
  }
  const returnBase = '/app?page=buchhaltung-beleg-form&action=edit&id=' + voucherId;
  const newContactLink = $('dg-voucher-new-contact-link');
  if (newContactLink) {
    newContactLink.href = '/app?page=kontakte&action=new&return_to=' + encodeURIComponent(returnBase);
  }
  if (window.history && window.history.replaceState) {
    window.history.replaceState(null, '', returnBase);
  }
}

function renderAttachedFiles(files) {
  const container = $('dg-voucher-attachments-live');
  if (!container) return;
  if (!files || files.length === 0) {
    container.hidden = true;
    container.innerHTML = '';
    return;
  }
  container.hidden = false;
  container.innerHTML = '<h3 class="dg-subsection-title">Gespeicherte Dateien</h3><ul class="dg-voucher-attachments__list">'
    + files.map((file) => {
      const name = escapeHtml(file.original_name || 'Datei');
      const size = escapeHtml(file.size_label || '');
      const viewUrl = escapeHtml(file.view_url || '#');
      const downloadUrl = escapeHtml(file.download_url || viewUrl);
      const thumb = file.is_image
        ? `<a href="${viewUrl}" target="_blank" rel="noopener" class="dg-voucher-attachments__thumb"><img src="${viewUrl}" alt="${name}" loading="lazy"></a>`
        : `<a href="${viewUrl}" target="_blank" rel="noopener" class="dg-voucher-attachments__thumb dg-voucher-attachments__thumb--file">PDF</a>`;
      return `<li class="dg-voucher-attachments__item">${thumb}<div class="dg-voucher-attachments__meta"><a href="${viewUrl}" target="_blank" rel="noopener" class="dg-voucher-attachments__name">${name}</a><span class="dg-muted">${size}</span><span class="dg-voucher-attachments__links"><a href="${downloadUrl}">Herunterladen</a></span></div></li>`;
    }).join('')
    + '</ul>';
}

async function uploadFileImmediate(file) {
  const csrf = importConfig.csrf || document.querySelector('#dg-voucher-form input[name="_csrf"]')?.value || '';
  const body = new FormData();
  body.set('_csrf', csrf);
  body.set('file', file);
  const voucherId = getVoucherId();
  if (voucherId > 0) {
    body.set('voucher_id', String(voucherId));
  }
  const response = await fetch('/api/voucher?action=file_upload', {
    method: 'POST',
    body,
    credentials: 'same-origin',
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.success) {
    throw new Error(payload.message || 'Datei konnte nicht gespeichert werden.');
  }
  const data = payload.data || {};
  if (data.voucher_id) {
    setVoucherId(Number(data.voucher_id));
  }
  if (Array.isArray(data.files)) {
    renderAttachedFiles(data.files);
  }
  return data;
}

function showServerFilePreview(fileMeta, fileName) {
  const previewEl = $('dg-voucher-extract-preview');
  const panel = $('dg-voucher-extract');
  if (!previewEl || !panel || !fileMeta) return;
  panel.hidden = false;
  previewEl.hidden = false;
  const name = fileName || fileMeta.original_name || 'beleg';
  const viewUrl = fileMeta.view_url || '';
  const head = `<div class="dg-voucher-extract__preview-head"><span class="dg-voucher-extract__preview-name">${escapeHtml(name)}</span><a href="${escapeHtml(viewUrl)}" target="_blank" rel="noopener">Vollbild</a></div>`;
  if (fileMeta.is_pdf && viewUrl) {
    previewEl.innerHTML = head + `<iframe class="dg-voucher-extract__preview-frame" src="${escapeHtml(viewUrl)}" title="Belegvorschau: ${escapeHtml(name)}"></iframe>`;
    return;
  }
  if (fileMeta.is_image && viewUrl) {
    previewEl.innerHTML = head + `<img class="dg-voucher-extract__preview-img" src="${escapeHtml(viewUrl)}" alt="${escapeHtml(name)}">`;
    return;
  }
  previewEl.innerHTML = head + '<p class="dg-voucher-extract__preview-hint dg-field-hint">Datei gespeichert — Vorschau im Browser nicht verfügbar.</p>';
}

async function fetchFileBlobFromServer(fileMeta) {
  const viewUrl = fileMeta?.view_url;
  if (!viewUrl) return null;
  const response = await fetch(viewUrl, { credentials: 'same-origin' });
  if (!response.ok) return null;
  return response.blob();
}

async function analyzeStoredFile(fileMeta, originalFile) {
  if (originalFile) {
    return analyzeFile(originalFile);
  }
  const blob = await fetchFileBlobFromServer(fileMeta);
  if (!blob) {
    throw new Error('Gespeicherte Datei konnte für die Erkennung nicht geladen werden.');
  }
  const name = fileMeta?.original_name || 'beleg';
  const file = new File([blob], name, { type: blob.type || fileMeta?.mime || 'application/octet-stream' });
  return analyzeFile(file);
}
let pdfjsLib = null;
let tesseractLoading = null;
let currentSuggestion = null;
let previewObjectUrl = null;

function $(id) {
  return document.getElementById(id);
}

function clearPreview() {
  if (previewObjectUrl) {
    URL.revokeObjectURL(previewObjectUrl);
    previewObjectUrl = null;
  }
  const previewEl = $('dg-voucher-extract-preview');
  if (previewEl) {
    previewEl.innerHTML = '';
    previewEl.hidden = true;
  }
}

/** Zeigt die Originaldatei sofort an (PDF im iframe, Bild als Vorschau). */
function showFilePreview(file) {
  clearPreview();
  const previewEl = $('dg-voucher-extract-preview');
  const panel = $('dg-voucher-extract');
  if (!previewEl || !panel || !file) return;

  panel.hidden = false;
  previewEl.hidden = false;

  const name = file.name || 'beleg';
  const ext = name.split('.').pop().toLowerCase();
  const type = (file.type || '').toLowerCase();
  const head = (body) =>
    `<div class="dg-voucher-extract__preview-head"><span class="dg-voucher-extract__preview-name">${escapeHtml(name)}</span>${body}</div>`;

  if (ext === 'pdf' || type === 'application/pdf') {
    previewObjectUrl = URL.createObjectURL(file);
    const openLink = `<a href="${previewObjectUrl}" target="_blank" rel="noopener">Vollbild</a>`;
    previewEl.innerHTML =
      head(openLink) +
      `<iframe class="dg-voucher-extract__preview-frame" src="${previewObjectUrl}" title="Belegvorschau: ${escapeHtml(name)}"></iframe>`;
    return;
  }

  if (type.startsWith('image/') || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
    previewObjectUrl = URL.createObjectURL(file);
    const openLink = `<a href="${previewObjectUrl}" target="_blank" rel="noopener">Vollbild</a>`;
    previewEl.innerHTML =
      head(openLink) +
      `<img class="dg-voucher-extract__preview-img" src="${previewObjectUrl}" alt="${escapeHtml(name)}">`;
    return;
  }

  if (ext === 'xml' || type.includes('xml')) {
    previewEl.innerHTML =
      head('') +
      '<p class="dg-voucher-extract__preview-hint dg-field-hint">E-Rechnung (XML) — keine Bildvorschau. Die Felder werden direkt aus dem XML gelesen.</p>';
  }
}

function setStatus(kind, html) {
  const panel = $('dg-voucher-extract');
  const status = $('dg-voucher-extract-status');
  if (!panel || !status) return;
  panel.hidden = false;
  status.className = 'dg-voucher-extract__status dg-voucher-extract__status--' + kind;
  status.innerHTML = html;
}

function spinner() {
  return '<span class="dg-voucher-extract__spinner"></span>';
}

async function loadPdfJs() {
  if (pdfjsLib) return pdfjsLib;
  pdfjsLib = await import(/* @vite-ignore */ PDFJS_URL);
  try {
    pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER_URL;
  } catch {
    /* ignore */
  }
  return pdfjsLib;
}

function loadTesseract() {
  if (window.Tesseract) return Promise.resolve(window.Tesseract);
  if (tesseractLoading) return tesseractLoading;
  tesseractLoading = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = TESSERACT_URL;
    script.onload = () => (window.Tesseract ? resolve(window.Tesseract) : reject(new Error('Tesseract nicht geladen')));
    script.onerror = () => reject(new Error('Tesseract konnte nicht geladen werden'));
    document.head.appendChild(script);
  });
  return tesseractLoading;
}

async function getPdfDocument(bytes) {
  const lib = await loadPdfJs();
  return lib.getDocument({ data: bytes }).promise;
}

async function findEInvoiceXmlInPdf(pdf, bytes) {
  try {
    const attachments = await pdf.getAttachments();
    if (attachments) {
      const keys = Object.keys(attachments).sort((a, b) => {
        const score = (name) => (/factur|zugferd|xrechnung|\.xml$/i.test(name) ? 0 : 1);
        return score(a) - score(b);
      });
      for (const key of keys) {
        const entry = attachments[key];
        const content = entry && entry.content ? entry.content : null;
        if (!content) continue;
        const text = new TextDecoder('utf-8', { fatal: false }).decode(content);
        if (looksLikeEInvoiceXml(text)) return text;
      }
    }
  } catch {
    /* keine Anhänge */
  }
  return extractEmbeddedXmlFromPdfBytes(bytes);
}

/** Fallback: eingebettetes Factur-X/XML direkt im PDF-Binary suchen. */
function extractEmbeddedXmlFromPdfBytes(bytes) {
  const hay = new TextDecoder('utf-8', { fatal: false }).decode(bytes);
  const patterns = [
    /<rsm:CrossIndustryInvoice[\s\S]*?<\/rsm:CrossIndustryInvoice>/,
    /<(?:[A-Za-z0-9_-]+:)?CrossIndustryInvoice[\s\S]*?<\/(?:[A-Za-z0-9_-]+:)?CrossIndustryInvoice>/,
    /<(?:[A-Za-z0-9_-]+:)?Invoice[\s\S]*?<\/(?:[A-Za-z0-9_-]+:)?Invoice>/,
  ];
  for (const pattern of patterns) {
    const match = hay.match(pattern);
    if (match && looksLikeEInvoiceXml(match[0])) return match[0];
  }
  return null;
}

async function extractPdfText(pdf) {
  const maxPages = Math.min(pdf.numPages, 8);
  const lines = [];
  for (let p = 1; p <= maxPages; p += 1) {
    const page = await pdf.getPage(p);
    const content = await page.getTextContent();
    let lastY = null;
    let line = '';
    for (const item of content.items) {
      const y = item.transform ? Math.round(item.transform[5]) : null;
      if (lastY !== null && y !== null && Math.abs(y - lastY) > 3) {
        if (line.trim()) lines.push(line.trim());
        line = '';
      }
      line += item.str + (item.hasEOL ? '\n' : ' ');
      lastY = y;
    }
    if (line.trim()) lines.push(line.trim());
  }
  return lines.join('\n');
}

function hasEnoughText(text) {
  const letters = (text.match(/[A-Za-zÄÖÜäöüß]/g) || []).length;
  return letters >= 40;
}

async function renderPdfPageToCanvas(pdf, pageNo) {
  const page = await pdf.getPage(pageNo);
  const viewport = page.getViewport({ scale: 2 });
  const canvas = document.createElement('canvas');
  canvas.width = viewport.width;
  canvas.height = viewport.height;
  const ctx = canvas.getContext('2d');
  await page.render({ canvasContext: ctx, viewport }).promise;
  return canvas;
}

async function ocrCanvasOrImage(source) {
  const Tesseract = await loadTesseract();
  const { data } = await Tesseract.recognize(source, 'deu');
  return data && data.text ? data.text : '';
}

function isoFromLoose(value) {
  if (!value) return '';
  const iso = String(value).match(/(\d{4})-(\d{2})-(\d{2})/);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
  const de = String(value).match(/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})/);
  if (de) {
    const year = de[3].length === 2 ? `20${de[3]}` : de[3];
    return `${year}-${de[2].padStart(2, '0')}-${de[1].padStart(2, '0')}`;
  }
  return '';
}

function ibanFromLine(line) {
  if (!line) return '';
  const m = String(line).replace(/\s+/g, '').toUpperCase().match(/[A-Z]{2}[0-9A-Z]{13,32}/);
  return m ? m[0] : '';
}

function bicFromLine(line) {
  if (!line) return '';
  const m = String(line).toUpperCase().match(/\b[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?\b/);
  return m ? m[0] : '';
}

function normalizeTaxRate(percent) {
  const p = Math.round(parseFloat(String(percent).replace(',', '.')));
  if (!Number.isFinite(p)) return '';
  if ([0, 7, 19].includes(p)) return String(p);
  const options = [0, 7, 19];
  return String(options.reduce((best, option) => (Math.abs(option - p) < Math.abs(best - p) ? option : best)));
}

function formatMoneyDe(value) {
  if (value === '' || value == null) return '';
  const n = Number(String(value).replace(',', '.'));
  if (!Number.isFinite(n)) return String(value);
  return n.toFixed(2).replace('.', ',');
}

/** Ergänzt fehlende Felder aus Rohtext (PDF-Textebene / OCR). */
function enrichSuggestion(suggestion, rawText) {
  if (!rawText) return suggestion;
  const lines = rawText
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  if (!suggestion.invoiceNumber || suggestion.invoiceNumber.length > 40 || /^urn:/i.test(suggestion.invoiceNumber)) {
    for (let i = 0; i < lines.length; i += 1) {
      const line = lines[i];
      const inline = line.match(/(?:rechnungs?-?\s*nr\.?|rechnungsnummer)[:\s-]+([A-Z0-9][A-Z0-9\-/.]{2,})/i);
      if (inline?.[1]) {
        suggestion.invoiceNumber = inline[1];
        break;
      }
      if (/^rechnungs?(?:\s*-?\s*nr\.?|\s*nummer)?$/i.test(line) && lines[i + 1] && /^\d{6,}$/.test(lines[i + 1])) {
        suggestion.invoiceNumber = lines[i + 1];
        break;
      }
    }
  }

  const grossMatch = rawText.match(
    /(?:gesamtbetrag|rechnungsbetrag|zu\s*zahlen|endsumme)\s*(?:eur|€)?\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[,.]\d{2})/i,
  );
  if (grossMatch?.[1]) {
    const gross = parseReceiptAmount(grossMatch[1]);
    if (gross !== null && Math.abs(gross) >= 1) {
      const current = parseReceiptAmount(String(suggestion.grossAmount || ''));
      if (current === null || Math.abs(current) < Math.abs(gross)) {
        suggestion.grossAmount = gross.toFixed(2);
      }
    }
  }

  if (!suggestion.netAmount) {
    const netMatch = rawText.match(/(?:nettowert|netto(?:wert)?|taxbasis)\s*(?:eur|€)?\s*(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[,.]\d{2})/i);
    if (netMatch?.[1]) {
      const net = parseReceiptAmount(netMatch[1]);
      if (net !== null) suggestion.netAmount = net.toFixed(2);
    }
  }

  if (!suggestion.vatAmount) {
    const vatLine = lines.find((line) => /^mwst$/i.test(line));
    if (vatLine) {
      const idx = lines.indexOf(vatLine);
      const next = lines[idx + 1];
      if (next) {
        const amt = parseReceiptAmount(next.replace(/[^\d,.-]/g, '').match(/-?\d+[,.]\d{2}/)?.[0] || next);
        if (amt !== null) suggestion.vatAmount = amt.toFixed(2);
      }
    }
    if (!suggestion.vatAmount) {
      const steuerMatch = rawText.match(/steuerbetrag\s+(\d{1,3}(?:\.\d{3})*,\d{2}|\d+[,.]\d{2})/i);
      if (steuerMatch?.[1]) {
        const vat = parseReceiptAmount(steuerMatch[1]);
        if (vat !== null) suggestion.vatAmount = vat.toFixed(2);
      }
    }
  }

  if (!suggestion.taxPercent) {
    const rateMatch = rawText.match(/(?:mwst\.?-?\s*satz|steuer(?:betrag)?|ust)[^\d]{0,20}(\d{1,2})[,.]\d{0,2}\s*%/i);
    if (rateMatch?.[1]) suggestion.taxPercent = normalizeTaxRate(rateMatch[1]);
  }

  if (!suggestion.invoiceDate) {
    const dateMatch = rawText.match(/(?:rechnungsdatum|belegdatum)[:\s]+(\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4})/i);
    if (dateMatch?.[1]) suggestion.invoiceDate = isoFromLoose(dateMatch[1]);
  }

  const contactExtra = extractContactMasterFromText(rawText);
  const contactFields = [
    'taxNumber',
    'customerNumber',
    'phone',
    'website',
    'street',
    'city',
    'postal',
    'commercialRegister',
    'weeeRegistration',
    'iban',
    'bic',
  ];
  for (const key of contactFields) {
    if (!suggestion[key] && contactExtra[key]) suggestion[key] = contactExtra[key];
  }

  if (suggestion.taxPercent) suggestion.taxPercent = normalizeTaxRate(suggestion.taxPercent);

  return suggestion;
}

function finalizeSuggestion(suggestion, sourceLabel, rawText) {
  return enrichSuggestion({ ...suggestion, sourceLabel }, rawText);
}

/** analyzeInvoiceText-Ergebnis -> einheitliches Vorschlagsobjekt. */
function suggestionFromAnalysis(analysis, source) {
  return {
    source,
    supplier: analysis.supplier || '',
    invoiceNumber: analysis.invoiceNumber || '',
    invoiceDate: isoFromLoose(analysis.invoiceDate) || '',
    grossAmount: analysis.grossAmount || '',
    netAmount: '',
    vatAmount: '',
    taxPercent: '',
    iban: ibanFromLine(analysis.ibanLine),
    bic: bicFromLine(analysis.bicLine),
    vatId: analysis.vatId || '',
    taxNumber: analysis.taxNumber || '',
    customerNumber: '',
    phone: '',
    website: '',
    street: '',
    city: '',
    postal: '',
    commercialRegister: '',
    weeeRegistration: '',
    items: [],
    itemCount: Number(analysis.itemCount) || 0,
  };
}

async function analyzeFile(file) {
  const name = file.name || 'beleg';
  const ext = name.split('.').pop().toLowerCase();
  const type = (file.type || '').toLowerCase();

  if (ext === 'xml' || type.includes('xml')) {
    const text = await file.text();
    if (looksLikeEInvoiceXml(text)) {
      return finalizeSuggestion(parseEInvoiceXml(text, name), 'E-Rechnung (XML)', text);
    }
    throw new Error('XML ist keine erkennbare E-Rechnung.');
  }

  if (ext === 'pdf' || type === 'application/pdf') {
    const bytes = new Uint8Array(await file.arrayBuffer());
    const pdf = await getPdfDocument(bytes);

    setStatus('working', `${spinner()} PDF wird geprüft (E-Rechnung / Textebene) …`);
    const xml = await findEInvoiceXmlInPdf(pdf, bytes);
    if (xml) {
      const pdfText = await extractPdfText(pdf);
      return finalizeSuggestion(
        parseEInvoiceXml(xml, name),
        'E-Rechnung (ZUGFeRD/XRechnung im PDF)',
        `${xml}\n${pdfText}`,
      );
    }

    const text = await extractPdfText(pdf);
    if (hasEnoughText(text)) {
      return finalizeSuggestion(
        suggestionFromAnalysis(analyzeInvoiceText(text), 'textlayer'),
        'Digitales PDF (Textebene)',
        text,
      );
    }

    setStatus('working', `${spinner()} Kein Textlayer gefunden – OCR läuft (kann etwas dauern) …`);
    const canvas = await renderPdfPageToCanvas(pdf, 1);
    const ocrText = await ocrCanvasOrImage(canvas);
    return finalizeSuggestion(
      suggestionFromAnalysis(analyzeInvoiceText(ocrText), 'ocr'),
      'Scan-PDF (OCR)',
      ocrText,
    );
  }

  if (type.startsWith('image/') || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) {
    setStatus('working', `${spinner()} Foto wird per OCR gelesen (kann etwas dauern) …`);
    const url = URL.createObjectURL(file);
    try {
      const ocrText = await ocrCanvasOrImage(url);
      return finalizeSuggestion(
        suggestionFromAnalysis(analyzeInvoiceText(ocrText), 'ocr'),
        'Foto (OCR)',
        ocrText,
      );
    } finally {
      URL.revokeObjectURL(url);
    }
  }

  throw new Error('Dateityp wird für die automatische Erkennung nicht unterstützt.');
}

function persistSuggestion(suggestion) {
  if (!suggestion) return;
  try {
    sessionStorage.setItem('dg.voucherExtractSuggestion', JSON.stringify(suggestion));
  } catch {
    /* ignore quota errors */
  }
}

function renderSuggestion(suggestion) {
  currentSuggestion = suggestion;
  persistSuggestion(suggestion);
  const fieldsEl = $('dg-voucher-extract-fields');
  const actionsEl = $('dg-voucher-extract-actions');
  if (!fieldsEl) return;

  const rows = [
    ['Lieferant', suggestion.supplier],
    ['Rechnungsnummer', suggestion.invoiceNumber],
    ['Rechnungsdatum', suggestion.invoiceDate],
    ['Betrag (brutto)', suggestion.grossAmount ? suggestion.grossAmount + ' €' : ''],
    ['Betrag (netto)', suggestion.netAmount ? suggestion.netAmount + ' €' : ''],
    ['MwSt-Betrag', suggestion.vatAmount ? suggestion.vatAmount + ' €' : ''],
    ['MwSt-Satz', suggestion.taxPercent ? suggestion.taxPercent + ' %' : ''],
    ['IBAN', suggestion.iban],
    ['BIC', suggestion.bic],
    ['USt-ID', suggestion.vatId],
    ['Steuer-Nr.', suggestion.taxNumber],
    ['Kundennummer (beim Lieferanten)', suggestion.customerNumber],
    ['WEEE-Registrierungsnr.', suggestion.weeeRegistration],
    ['Handelsregister', suggestion.commercialRegister],
    ['Internetadresse', suggestion.website],
    ['Straße', suggestion.street],
    ['Ort', suggestion.city ? [suggestion.postal, suggestion.city].filter(Boolean).join(' ') : suggestion.postal],
    ['Telefon', suggestion.phone],
    ['Positionen', (suggestion.items && suggestion.items.length) || suggestion.itemCount ? String((suggestion.items && suggestion.items.length) || suggestion.itemCount) : ''],
  ].filter(([, value]) => value);

  fieldsEl.innerHTML = rows
    .map(([label, value]) => `<div class="dg-voucher-extract__field"><strong>${escapeHtml(label)}</strong>${escapeHtml(String(value))}</div>`)
    .join('');

  const found = rows.length > 0;
  setStatus(found ? 'ok' : 'warn', found
    ? `Erkannt aus <em>${escapeHtml(suggestion.sourceLabel)}</em> — bitte prüfen.`
    : `Aus <em>${escapeHtml(suggestion.sourceLabel)}</em> konnten keine Felder sicher erkannt werden.`);
  if (actionsEl) actionsEl.hidden = !found;
  updateContactSyncVisibility();
}

function updateContactSyncVisibility() {
  const wrap = $('dg-voucher-extract-contact-sync-wrap');
  const contactId = Number(($('dg-voucher-contact-id') || {}).value || 0);
  if (wrap) wrap.hidden = contactId < 1;
}

async function patchContactMasterData(suggestion) {
  const contactId = Number(($('dg-voucher-contact-id') || {}).value || 0);
  const syncCheckbox = $('dg-voucher-extract-contact-sync');
  if (contactId < 1 || !syncCheckbox || !syncCheckbox.checked) return null;

  const csrf = document.querySelector('input[name="_csrf"]')?.value || '';
  const body = new URLSearchParams();
  body.set('_csrf', csrf);
  body.set('contact_id', String(contactId));
  if (suggestion.taxNumber) body.set('tax_number', suggestion.taxNumber);
  if (suggestion.vatId) body.set('vat_id', suggestion.vatId);
  if (suggestion.customerNumber) body.set('supplier_customer_number', suggestion.customerNumber);
  if (suggestion.commercialRegister) body.set('commercial_register', suggestion.commercialRegister);
  if (suggestion.weeeRegistration) body.set('weee_registration', suggestion.weeeRegistration);
  if (suggestion.website) body.set('website', suggestion.website);
  if (suggestion.phone) body.set('phone_1', suggestion.phone);
  if (suggestion.street) body.set('address1_street', suggestion.street);
  if (suggestion.postal) body.set('address1_postal', suggestion.postal);
  if (suggestion.city) body.set('address1_city', suggestion.city);

  const response = await fetch('/app?page=buchhaltung-beleg-form&action=contact-patch', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json' },
    body: body.toString(),
    credentials: 'same-origin',
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.success) {
    throw new Error(payload.message || 'Kontakt konnte nicht aktualisiert werden.');
  }
  return payload.updated || [];
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function setFieldValue(el, value) {
  if (!el || value === '' || value == null) return;
  if (el.readOnly || el.disabled) return;
  el.value = value;
  el.dispatchEvent(new Event('input', { bubbles: true }));
  el.dispatchEvent(new Event('change', { bubbles: true }));
}

function applySuggestion() {
  if (!currentSuggestion) return;
  const s = currentSuggestion;

  const apply = async () => {
    const invoiceEl = $('dg-voucher-invoice-number');
    if (invoiceEl && invoiceEl.name === 'invoice_number') setFieldValue(invoiceEl, s.invoiceNumber);

    setFieldValue($('dg-voucher-date'), s.invoiceDate);
    setFieldValue($('dg-voucher-delivery-date'), s.invoiceDate);
    setFieldValue($('dg-voucher-supplier-name'), s.supplier);

    const contactSearch = $('dg-voucher-contact-search');
    if (contactSearch && !contactSearch.value && s.supplier) {
      contactSearch.value = s.supplier;
      contactSearch.dispatchEvent(new Event('input', { bubbles: true }));
    }

    if (s.grossAmount) {
      const grossInput = document.querySelector('.dg-voucher-split-gross');
      if (grossInput) setFieldValue(grossInput, formatMoneyDe(s.grossAmount));
    }

    if (s.taxPercent) {
      const rate = normalizeTaxRate(s.taxPercent);
      document.querySelectorAll('.dg-voucher-split-tax, select[name*="[tax_rate]"]').forEach((taxField) => {
        if (taxField.tagName === 'SELECT') setFieldValue(taxField, rate);
      });
    }

    const notes = document.querySelector('textarea[name="notes"]');
    if (notes) {
      const parts = [];
      if (s.netAmount) parts.push('Netto: ' + formatMoneyDe(s.netAmount) + ' €');
      if (s.vatAmount) parts.push('MwSt: ' + formatMoneyDe(s.vatAmount) + ' €');
      if (s.taxPercent) parts.push('MwSt-Satz: ' + normalizeTaxRate(s.taxPercent) + ' %');
      if (s.iban) parts.push('IBAN: ' + s.iban);
      if (s.bic) parts.push('BIC: ' + s.bic);
      if (s.vatId) parts.push('USt-ID: ' + s.vatId);
      if (s.customerNumber) parts.push('Kundennummer (beim Lieferant): ' + s.customerNumber);
      if (s.taxNumber) parts.push('Steuer-Nr.: ' + s.taxNumber);
      if (s.weeeRegistration) parts.push('WEEE: ' + s.weeeRegistration);
      if (s.commercialRegister) parts.push('Handelsregister: ' + s.commercialRegister);
      if (s.website) parts.push('Website: ' + s.website);
      if (s.street) parts.push('Straße: ' + s.street);
      if (s.postal || s.city) parts.push('Ort: ' + [s.postal, s.city].filter(Boolean).join(' '));
      if (s.phone) parts.push('Telefon: ' + s.phone);
      if (parts.length) {
        const addition = 'Aus Beleg erkannt — ' + parts.join(', ');
        notes.value = notes.value ? notes.value + '\n' + addition : addition;
        notes.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }

    let statusMsg = 'Vorschläge übernommen. Bitte Kontakt aus der Liste bestätigen und Beleg speichern.';
    try {
      const updated = await patchContactMasterData(s);
      if (updated && updated.length > 0) {
        statusMsg = `Vorschläge übernommen. Beim Kontakt ergänzt: ${updated.join(', ')}.`;
      }
    } catch (error) {
      statusMsg = 'Vorschläge übernommen, aber Kontakt-Stammdaten konnten nicht gespeichert werden: '
        + (error.message || String(error));
    }

    setStatus('ok', statusMsg);
    const actionsEl = $('dg-voucher-extract-actions');
    if (actionsEl) actionsEl.hidden = true;
  };

  apply().catch((error) => {
    setStatus('error', error.message || String(error));
  });
}

async function handleFiles(files) {
  if (!files || files.length === 0) return;
  const file = files[0];
  const input = $('dg-voucher-file-input');
  showFilePreview(file);
  let uploadData = null;
  try {
    setStatus('working', `${spinner()} Datei wird gespeichert …`);
    uploadData = await uploadFileImmediate(file);
  } catch (error) {
    setStatus('error', 'Datei konnte nicht gespeichert werden: ' + escapeHtml(error.message || String(error)));
    return;
  }

  const storedFiles = Array.isArray(uploadData.files) ? uploadData.files : [];
  const latestFile = storedFiles[storedFiles.length - 1] || null;
  if (latestFile) {
    showServerFilePreview(latestFile, file.name);
  }
  if (input) {
    input.value = '';
  }

  try {
    setStatus('working', `${spinner()} Datei wird analysiert …`);
    const suggestion = await analyzeStoredFile(latestFile, file);
    renderSuggestion(suggestion);
  } catch (error) {
    setStatus('warn', 'Datei gespeichert, aber Erkennung fehlgeschlagen: ' + escapeHtml(error.message || String(error))
      + ' — Felder bitte manuell ausfüllen.');
    const actionsEl = $('dg-voucher-extract-actions');
    if (actionsEl) actionsEl.hidden = true;
  }
}

function init() {
  const input = $('dg-voucher-file-input');
  if (!input) return;

  if (Array.isArray(importConfig.initialFiles) && importConfig.initialFiles.length > 0) {
    renderAttachedFiles(importConfig.initialFiles);
    const latestFile = importConfig.initialFiles[importConfig.initialFiles.length - 1];
    if (latestFile) {
      showServerFilePreview(latestFile, latestFile.original_name || 'Beleg');
      const panel = $('dg-voucher-extract');
      if (panel) panel.hidden = false;
    }
  }

  input.addEventListener('change', () => handleFiles(input.files));

  const applyBtn = $('dg-voucher-extract-apply');
  if (applyBtn) applyBtn.addEventListener('click', applySuggestion);

  const dismissBtn = $('dg-voucher-extract-dismiss');
  if (dismissBtn) {
    dismissBtn.addEventListener('click', () => {
      currentSuggestion = null;
      clearPreview();
      const panel = $('dg-voucher-extract');
      if (panel) panel.hidden = true;
    });
  }

  const contactIdInput = $('dg-voucher-contact-id');
  if (contactIdInput) {
    contactIdInput.addEventListener('change', updateContactSyncVisibility);
    contactIdInput.addEventListener('input', updateContactSyncVisibility);
  }
  updateContactSyncVisibility();

  const dropzone = $('dg-voucher-file-dropzone');
  if (dropzone) {
    ['dragenter', 'dragover'].forEach((evt) =>
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.add('is-dragover');
      }),
    );
    ['dragleave', 'drop'].forEach((evt) =>
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
      }),
    );
    dropzone.addEventListener('drop', (e) => {
      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        handleFiles(input.files);
      }
    });
  }
}

window.dgVoucherImportRestoreSuggestion = function restoreSuggestion(suggestion) {
  if (!suggestion) return;
  renderSuggestion(suggestion);
  const panel = $('dg-voucher-extract');
  if (panel) panel.hidden = false;
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
