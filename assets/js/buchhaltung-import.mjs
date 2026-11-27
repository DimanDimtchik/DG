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
  mapEInvoiceToBo2Extract,
  mapReceiptHintsToBo2Extract,
} from './vendor/dg-einvoice-xml.mjs';
import { buildApplyStatus } from './vendor/dg-voucher-ocr-apply.mjs';

const PDFJS_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.6.82/build/pdf.min.mjs';
const PDFJS_WORKER_URL = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.6.82/build/pdf.worker.min.mjs';
const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
const importConfig = window.dgBuchhaltungBelege || {};

function getDocumentKind() {
  const select = document.getElementById('dg-voucher-document-kind');
  if (select && select.value) {
    return String(select.value);
  }
  const hidden = document.querySelector('#dg-voucher-form input[name="document_kind"]');
  return hidden ? String(hidden.value || '') : '';
}

function getVoucherType() {
  const select = document.getElementById('dg-voucher-type');
  if (select && select.value) {
    return String(select.value);
  }
  const hidden = document.querySelector('#dg-voucher-form input[name="voucher_type"]');
  return hidden ? String(hidden.value || '') : '';
}

/** Auftrag/Angebot: nur Datei speichern, keine E-Rechnungs-/OCR-Analyse. */
function isNotesAttachmentMode() {
  const kind = getDocumentKind();
  if (kind === 'offer' || kind === 'work_order') {
    return true;
  }
  return !!document.querySelector('.dg-voucher-files--notes');
}

function setNotesUploadStatus(type, message) {
  const el = $('dg-voucher-notes-upload-status');
  if (!el) return;
  if (!message) {
    el.hidden = true;
    el.textContent = '';
    el.className = 'dg-field-hint';
    return;
  }
  el.hidden = false;
  el.textContent = message;
  el.className = 'dg-field-hint'
    + (type === 'error' ? ' dg-field-hint--error' : '')
    + (type === 'ok' ? ' dg-field-hint--ok' : '');
}

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
  const focusParam = document.querySelector('#dg-voucher-form input[name="focus"]');
  const returnWithFocus = focusParam && focusParam.value
    ? returnBase + '&focus=' + encodeURIComponent(focusParam.value)
    : returnBase;
  const newContactLink = $('dg-voucher-new-contact-link');
  if (newContactLink) {
    newContactLink.href = '/app?page=kontakte&action=new&return_to=' + encodeURIComponent(returnWithFocus);
  }
  if (window.history && window.history.replaceState) {
    window.history.replaceState(null, '', returnWithFocus);
  }
}

/** Zuletzt gerenderte Anhänge (für „Erneut auslesen“). */
let storedFilesCache = [];

function canAnalyzeFileMeta(file) {
  if (!file) return false;
  if (file.is_pdf || file.is_image) return true;
  const mime = String(file.mime || '').toLowerCase();
  if (mime === 'application/pdf' || mime.startsWith('image/') || mime.includes('xml')) return true;
  const name = String(file.original_name || '').toLowerCase();
  const ext = name.split('.').pop() || '';
  return ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'xml'].includes(ext);
}

function latestAnalyzableStoredFile() {
  for (let i = storedFilesCache.length - 1; i >= 0; i -= 1) {
    if (canAnalyzeFileMeta(storedFilesCache[i])) return storedFilesCache[i];
  }
  return null;
}

function updateRereadToolbar() {
  const wrap = $('dg-voucher-extract-reread-wrap');
  if (!wrap) return;
  if (isNotesAttachmentMode() || !latestAnalyzableStoredFile()) {
    wrap.hidden = true;
    return;
  }
  wrap.hidden = false;
}

function renderAttachedFiles(files) {
  const container = $('dg-voucher-attachments-live');
  if (!container) return;
  storedFilesCache = Array.isArray(files) ? files.slice() : [];
  if (storedFilesCache.length === 0) {
    container.hidden = true;
    container.innerHTML = '';
    updateRereadToolbar();
    return;
  }
  container.hidden = false;
  const allowReread = !isNotesAttachmentMode();
  container.innerHTML = '<h3 class="dg-subsection-title">Gespeicherte Dateien</h3><ul class="dg-voucher-attachments__list">'
    + storedFilesCache.map((file, idx) => {
      const name = escapeHtml(file.original_name || 'Datei');
      const size = escapeHtml(file.size_label || '');
      const viewUrl = escapeHtml(file.view_url || '#');
      const downloadUrl = escapeHtml(file.download_url || viewUrl);
      const thumb = file.is_image
        ? `<a href="${viewUrl}" target="_blank" rel="noopener" class="dg-voucher-attachments__thumb"><img src="${viewUrl}" alt="${name}" loading="lazy"></a>`
        : `<a href="${viewUrl}" target="_blank" rel="noopener" class="dg-voucher-attachments__thumb dg-voucher-attachments__thumb--file">PDF</a>`;
      const rereadBtn = allowReread && canAnalyzeFileMeta(file)
        ? `<button type="button" class="dg-button dg-button--small" data-voucher-reread="${idx}">Erneut auslesen</button>`
        : '';
      return `<li class="dg-voucher-attachments__item">${thumb}<div class="dg-voucher-attachments__meta"><a href="${viewUrl}" target="_blank" rel="noopener" class="dg-voucher-attachments__name">${name}</a><span class="dg-muted">${size}</span><span class="dg-voucher-attachments__links dg-table__actions-group"><a class="dg-button dg-button--small" href="${downloadUrl}">Herunterladen</a>${rereadBtn}</span></div></li>`;
    }).join('')
    + '</ul>';
  updateRereadToolbar();
}

async function rereadStoredAttachment(fileMeta) {
  if (isNotesAttachmentMode()) {
    setNotesUploadStatus('error', 'Bei Auftrag/Angebot gibt es kein automatisches Auslesen.');
    return;
  }
  if (!canAnalyzeFileMeta(fileMeta)) {
    setStatus('warn', 'Dieser Dateityp kann nicht automatisch ausgelesen werden.');
    return;
  }
  showServerFilePreview(fileMeta, fileMeta.original_name || 'Beleg');
  try {
    setStatus('working', `${spinner()} Gespeicherte Datei wird erneut ausgelesen …`);
    const suggestion = await analyzeStoredFile(fileMeta, null);
    renderSuggestion(suggestion);
  } catch (error) {
    setStatus('warn', 'Erneutes Auslesen fehlgeschlagen: ' + escapeHtml(error.message || String(error))
      + ' — Felder bitte manuell ausfüllen.');
    const actionsEl = $('dg-voucher-extract-actions');
    if (actionsEl) actionsEl.hidden = true;
  }
}

async function uploadFileImmediate(file) {
  const csrf = importConfig.csrf || document.querySelector('#dg-voucher-form input[name="_csrf"]')?.value || '';
  const body = new FormData();
  body.set('_csrf', csrf);
  body.set('file', file);
  const voucherId = getVoucherId();
  if (voucherId > 0) {
    body.set('voucher_id', String(voucherId));
  } else {
    const voucherType = getVoucherType() || 'income';
    const documentKind = getDocumentKind();
    body.set('voucher_type', voucherType);
    if (documentKind) {
      body.set('document_kind', documentKind);
    }
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

/** Max. Seiten für Scan-PDF-OCR (Tesseract ist langsam). */
const OCR_PDF_MAX_PAGES = 5;

/**
 * OCR über mehrere PDF-Seiten; Statuszeile mit Fortschritt.
 * @returns {Promise<{ text: string, pagesUsed: number, pagesTotal: number, truncated: boolean }>}
 */
async function ocrPdfPages(pdf) {
  const pagesTotal = Math.max(1, Number(pdf.numPages) || 1);
  const pagesUsed = Math.min(pagesTotal, OCR_PDF_MAX_PAGES);
  const chunks = [];
  for (let pageNo = 1; pageNo <= pagesUsed; pageNo += 1) {
    setStatus(
      'working',
      `${spinner()} Scan-PDF OCR: Seite ${pageNo} von ${pagesUsed}`
        + (pagesTotal > pagesUsed ? ` (von ${pagesTotal}, Limit ${OCR_PDF_MAX_PAGES})` : '')
        + ' …',
    );
    const canvas = await renderPdfPageToCanvas(pdf, pageNo);
    const pageText = await ocrCanvasOrImage(canvas);
    if (pageText && pageText.trim()) {
      chunks.push(pageText.trim());
    }
  }
  return {
    text: chunks.join('\n\n'),
    pagesUsed,
    pagesTotal,
    truncated: pagesTotal > pagesUsed,
  };
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

function hasCompleteEInvoiceLines(suggestion) {
  return suggestion?.source === 'einvoice'
    && ((Array.isArray(suggestion.lines) && suggestion.lines.length > 0)
      || (Array.isArray(suggestion.extract?.lines) && suggestion.extract.lines.length > 0));
}

function syncSuggestionFromExtract(suggestion) {
  const extract = suggestion.extract;
  if (!extract || typeof extract !== 'object') return suggestion;
  if (extract.supplier_name) suggestion.supplier = extract.supplier_name;
  if (extract.invoice_number) suggestion.invoiceNumber = extract.invoice_number;
  if (extract.invoice_date) suggestion.invoiceDate = extract.invoice_date;
  if (extract.gross_total) suggestion.grossAmount = extract.gross_total;
  if (extract.net_total) suggestion.netAmount = extract.net_total;
  if (extract.vat_total) suggestion.vatAmount = extract.vat_total;
  if (extract.iban) suggestion.iban = extract.iban;
  if (extract.bic) suggestion.bic = extract.bic;
  if (extract.vat_id) suggestion.vatId = extract.vat_id;
  if (extract.tax_number) suggestion.taxNumber = extract.tax_number;
  if (Array.isArray(extract.lines)) {
    suggestion.lines = extract.lines;
    suggestion.itemCount = extract.lines.length;
    suggestion.items = extract.lines.map((line) => ({
      id: `line-${line.line_no}`,
      description: line.title,
      amount: Number(String(line.line_net || '').replace(',', '.')) || 0,
    }));
  }
  return suggestion;
}

/**
 * BO3d — optional Server-Extrakt nach Textlayer/OCR (kein Chat).
 * Soft-Fail / Netzwerkfehler: Client-Hints behalten.
 */
async function enrichWithAssistenz(suggestion, rawText, fileName) {
  const text = String(rawText || '').trim();
  if (!text || hasCompleteEInvoiceLines(suggestion)) {
    return suggestion;
  }

  const csrf = importConfig.csrf
    || document.querySelector('#dg-voucher-form input[name="_csrf"]')?.value
    || '';
  if (!csrf) {
    return suggestion;
  }

  setStatus('working', `${spinner()} Assistenz ordnet den Text …`);

  const body = new FormData();
  body.set('_csrf', csrf);
  body.set('raw_text', text.slice(0, 100000));
  const extractSource = suggestion.source === 'ocr'
    ? 'ocr'
    : (suggestion.source === 'einvoice' ? 'einvoice_xml' : 'pdf_text');
  body.set('source', extractSource);
  if (fileName) body.set('file_name', fileName);
  const voucherId = getVoucherId();
  if (voucherId > 0) body.set('voucher_id', String(voucherId));
  if (suggestion.extract && suggestion.extract.schema_version === 1) {
    body.set('hint_extract', JSON.stringify(suggestion.extract));
  }

  const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
  const timer = controller ? setTimeout(() => controller.abort(), 90000) : null;
  try {
    const response = await fetch('/api/voucher?action=voucher_extract', {
      method: 'POST',
      body,
      credentials: 'same-origin',
      signal: controller ? controller.signal : undefined,
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || !payload.success || !payload.data) {
      suggestion.assistenz = {
        ollama_used: false,
        soft_fail: true,
        soft_fail_reason: payload.message || 'Assistenz nicht erreichbar',
      };
      return suggestion;
    }
    const data = payload.data;
    const meta = data.meta && typeof data.meta === 'object' ? data.meta : {};
    suggestion.assistenz = {
      ollama_used: !!meta.ollama_used,
      soft_fail: !!meta.soft_fail,
      soft_fail_reason: String(meta.soft_fail_reason || ''),
      model: String(meta.model || ''),
    };
    if (data.extract && typeof data.extract === 'object' && data.extract.schema_version === 1) {
      suggestion.extract = data.extract;
      syncSuggestionFromExtract(suggestion);
      if (meta.ollama_used) {
        suggestion.sourceLabel = `${suggestion.sourceLabel || 'Beleg'} · geordnet durch Assistenz`;
      }
    }
    return suggestion;
  } catch {
    suggestion.assistenz = {
      ollama_used: false,
      soft_fail: true,
      soft_fail_reason: 'Assistenz nicht erreichbar — Text-Hinweise behalten.',
    };
    return suggestion;
  } finally {
    if (timer) clearTimeout(timer);
  }
}

async function finalizeSuggestion(suggestion, sourceLabel, rawText, fileName = '') {
  const next = enrichSuggestion({ ...suggestion, sourceLabel }, rawText);
  const hasEInvoiceLines = hasCompleteEInvoiceLines(next);

  if (hasEInvoiceLines) {
    if (!next.extract || next.extract.schema_version !== 1) {
      next.extract = mapEInvoiceToBo2Extract(next);
    }
  } else if (next.source === 'textlayer' || next.source === 'ocr' || next.source === 'pdf_text') {
    const hintSource = next.source === 'ocr' ? 'ocr' : 'pdf_text';
    next.extract = mapReceiptHintsToBo2Extract(next, rawText || '', hintSource);
  } else if (next.source === 'einvoice') {
    // XML ohne Positionen: optional Text-Hints aus Rohtext (ohne LLM)
    next.extract = mapEInvoiceToBo2Extract(next);
    if (!next.extract.lines.length && rawText) {
      const hints = mapReceiptHintsToBo2Extract({ ...next, source: 'textlayer' }, rawText, 'pdf_text');
      if (hints.lines.length) {
        next.extract = { ...hints, source: 'einvoice_xml', warnings: [...(hints.warnings || []), 'Positionen aus Text-Hints ergänzt.'] };
      }
    }
  }

  if (next.extract?.lines) {
    next.lines = next.extract.lines;
    next.itemCount = next.extract.lines.length;
    if (!Array.isArray(next.items) || next.items.length === 0) {
      next.items = next.extract.lines.map((line) => ({
        id: `line-${line.line_no}`,
        description: line.title,
        amount: Number(String(line.line_net || '').replace(',', '.')) || 0,
      }));
    }
  }

  return enrichWithAssistenz(next, rawText, fileName || '');
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
    items: Array.isArray(analysis.items) ? analysis.items : [],
    itemCount: Number(analysis.itemCount) || (Array.isArray(analysis.items) ? analysis.items.length : 0),
  };
}

async function analyzeFile(file) {
  const name = file.name || 'beleg';
  const ext = name.split('.').pop().toLowerCase();
  const type = (file.type || '').toLowerCase();

  if (ext === 'xml' || type.includes('xml')) {
    const text = await file.text();
    if (looksLikeEInvoiceXml(text)) {
      return finalizeSuggestion(parseEInvoiceXml(text, name), 'E-Rechnung (XML)', text, name);
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
        name,
      );
    }

    const text = await extractPdfText(pdf);
    if (hasEnoughText(text)) {
      return finalizeSuggestion(
        suggestionFromAnalysis(analyzeInvoiceText(text), 'textlayer'),
        'Digitales PDF (Textebene)',
        text,
        name,
      );
    }

    setStatus('working', `${spinner()} Kein Textlayer gefunden – OCR über Seiten (kann etwas dauern) …`);
    const ocr = await ocrPdfPages(pdf);
    const sourceLabel = ocr.truncated
      ? `Scan-PDF (OCR, ${ocr.pagesUsed}/${ocr.pagesTotal} Seiten)`
      : (ocr.pagesUsed > 1
        ? `Scan-PDF (OCR, ${ocr.pagesUsed} Seiten)`
        : 'Scan-PDF (OCR)');
    return finalizeSuggestion(
      suggestionFromAnalysis(analyzeInvoiceText(ocr.text), 'ocr'),
      sourceLabel,
      ocr.text,
      name,
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
        name,
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

  const lines = Array.isArray(suggestion.lines) && suggestion.lines.length
    ? suggestion.lines
    : (Array.isArray(suggestion.extract?.lines) ? suggestion.extract.lines : []);
  const linePreview = lines.slice(0, 8).map((line) => {
    const kind = line.catalog_kind === 'product' ? 'Artikel' : 'Leistung';
    const qty = line.quantity ? `${line.quantity} ${line.unit || ''}`.trim() : '';
    const net = line.line_net ? `${line.line_net} €` : '';
    return `${kind}: ${line.title || '—'}${qty ? ` · ${qty}` : ''}${net ? ` · ${net}` : ''}`;
  }).join(' | ');
  const amountCheck = suggestion.extract?.amount_check;
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
    ['Positionen', lines.length ? String(lines.length) : ((suggestion.items && suggestion.items.length) || suggestion.itemCount ? String((suggestion.items && suggestion.items.length) || suggestion.itemCount) : '')],
    ['Positionsvorschau', linePreview],
    ['Summenprüfung', amountCheck && amountCheck.ok === false ? (amountCheck.detail || 'Abweichung') : ''],
  ].filter(([, value]) => value);

  fieldsEl.innerHTML = rows
    .map(([label, value]) => `<div class="dg-voucher-extract__field"><strong>${escapeHtml(label)}</strong>${escapeHtml(String(value))}</div>`)
    .join('');

  const found = rows.length > 0;
  const assistenz = suggestion.assistenz || null;
  let statusHtml;
  if (!found) {
    statusHtml = `Aus <em>${escapeHtml(suggestion.sourceLabel)}</em> konnten keine Felder sicher erkannt werden.`;
  } else if (assistenz?.ollama_used) {
    statusHtml = `Erkannt aus <em>${escapeHtml(suggestion.sourceLabel)}</em> — Text geordnet durch Assistenz, bitte prüfen.`;
  } else {
    statusHtml = `Erkannt aus <em>${escapeHtml(suggestion.sourceLabel)}</em> — bitte prüfen.`;
    if (assistenz?.soft_fail && assistenz.soft_fail_reason) {
      statusHtml += ` ${escapeHtml(assistenz.soft_fail_reason)}`;
    }
  }
  setStatus(found ? 'ok' : 'warn', statusHtml);
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
    // Kopfdaten immer zuerst — unabhängig von Positions-Apply (BO4c).
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

    const extractLines = Array.isArray(s.extract?.lines) && s.extract.lines.length
      ? s.extract.lines
      : (Array.isArray(s.lines) ? s.lines : []);
    const formApi = window.dgVoucherFormApi;
    let lineApply = {
      mode: 'none',
      count: 0,
      summaryLines: [],
      skipped: 0,
      skippedEmptyTitle: 0,
      skippedNoAmount: 0,
      skippedTitles: [],
    };
    try {
      if (extractLines.length && formApi && typeof formApi.applyOcrExtractLines === 'function') {
        lineApply = formApi.applyOcrExtractLines(extractLines) || lineApply;
      } else if (s.grossAmount) {
        const grossInput = document.querySelector('.dg-voucher-split-gross');
        if (grossInput) setFieldValue(grossInput, formatMoneyDe(s.grossAmount));
        if (s.taxPercent) {
          const rate = normalizeTaxRate(s.taxPercent);
          document.querySelectorAll('.dg-voucher-split-tax, select[name*="[tax_rate]"]').forEach((taxField) => {
            if (taxField.tagName === 'SELECT') setFieldValue(taxField, rate);
          });
        }
      }
    } catch (lineError) {
      lineApply = {
        ...lineApply,
        mode: 'error',
        count: 0,
      };
      lineApply._error = lineError?.message || String(lineError);
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
      const chunks = [];
      if (parts.length) {
        chunks.push('Aus Beleg erkannt — ' + parts.join(', '));
      }
      if (lineApply.summaryLines && lineApply.summaryLines.length) {
        chunks.push('Positionen aus Beleg:\n' + lineApply.summaryLines.join('\n'));
      }
      if (chunks.length) {
        const addition = chunks.join('\n');
        notes.value = notes.value ? notes.value + '\n' + addition : addition;
        notes.dispatchEvent(new Event('input', { bubbles: true }));
      }
    }

    const status = buildApplyStatus(s, lineApply, extractLines.length);
    let statusMsg = status.message;
    if (lineApply._error) {
      statusMsg = `Kopfdaten übernommen. Positionen konnten nicht übernommen werden: ${lineApply._error}`;
      status.kind = 'warn';
    }
    try {
      const updated = await patchContactMasterData(s);
      if (updated && updated.length > 0) {
        statusMsg += ` Beim Kontakt ergänzt: ${updated.join(', ')}.`;
      }
    } catch (error) {
      statusMsg += ' Kontakt-Stammdaten konnten nicht gespeichert werden: '
        + (error.message || String(error));
      status.kind = 'warn';
    }

    setStatus(status.kind, statusMsg);
    const actionsEl = $('dg-voucher-extract-actions');
    if (actionsEl) actionsEl.hidden = true;
  };

  apply().catch((error) => {
    setStatus('error', error.message || String(error));
  });
}

async function handleFiles(files) {
  if (!files || files.length === 0) return;
  const input = $('dg-voucher-file-input');
  const list = Array.from(files);
  const notesMode = isNotesAttachmentMode();

  if (notesMode) {
    let lastData = null;
    for (let i = 0; i < list.length; i += 1) {
      const file = list[i];
      try {
        setNotesUploadStatus('working', `Speichere ${file.name || 'Datei'} (${i + 1}/${list.length}) …`);
        lastData = await uploadFileImmediate(file);
      } catch (error) {
        setNotesUploadStatus('error', 'Datei konnte nicht gespeichert werden: ' + (error.message || String(error)));
        if (input) input.value = '';
        return;
      }
    }
    const count = Array.isArray(lastData?.files) ? lastData.files.length : list.length;
    setNotesUploadStatus('ok', count === 1
      ? '1 Datei gespeichert (intern).'
      : `${count} Dateien gespeichert (intern).`);
    if (input) input.value = '';
    return;
  }

  const file = list[0];
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
    const latestFile = latestAnalyzableStoredFile()
      || importConfig.initialFiles[importConfig.initialFiles.length - 1];
    if (latestFile && !isNotesAttachmentMode()) {
      showServerFilePreview(latestFile, latestFile.original_name || 'Beleg');
      const panel = $('dg-voucher-extract');
      if (panel) panel.hidden = false;
      setStatus(
        'ok',
        'Gespeicherte Datei bereit. Mit <strong>Erneut auslesen</strong> Felder vorschlagen.',
      );
    }
  } else {
    updateRereadToolbar();
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

  const rereadBtn = $('dg-voucher-extract-reread');
  if (rereadBtn) {
    rereadBtn.addEventListener('click', () => {
      const fileMeta = latestAnalyzableStoredFile();
      if (!fileMeta) {
        setStatus('warn', 'Keine gespeicherte Datei zum Auslesen gefunden.');
        return;
      }
      rereadStoredAttachment(fileMeta);
    });
  }

  const attachmentsLive = $('dg-voucher-attachments-live');
  if (attachmentsLive) {
    attachmentsLive.addEventListener('click', (event) => {
      const btn = event.target && event.target.closest
        ? event.target.closest('[data-voucher-reread]')
        : null;
      if (!btn) return;
      event.preventDefault();
      const idx = Number(btn.getAttribute('data-voucher-reread'));
      const fileMeta = storedFilesCache[idx];
      if (!fileMeta) return;
      rereadStoredAttachment(fileMeta);
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
