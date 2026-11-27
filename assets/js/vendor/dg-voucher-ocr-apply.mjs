/**
 * BO4 — reine Apply-Helfer (ohne DOM): Extrakt-Zeilen normalisieren + Status-Text.
 * Browser: window.dgVoucherOcrApply · Node-Selftest: Named Exports.
 */

export function parseOcrMoney(value) {
  if (value === '' || value == null) return 0;
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  let raw = String(value).trim().replace(/[€\s]/g, '').replace(/EUR/gi, '');
  if (!raw) return 0;
  if (/^\d{1,3}(\.\d{3})+,\d{2}$/.test(raw)) {
    raw = raw.replace(/\./g, '').replace(',', '.');
  } else {
    raw = raw.replace(',', '.');
  }
  const n = Number(raw);
  return Number.isFinite(n) ? n : 0;
}

/**
 * @param {unknown} rawLines
 * @returns {{
 *   lines: Array<Record<string, unknown>>,
 *   skippedEmptyTitle: number,
 *   skippedNoAmount: number,
 *   skippedTitles: string[]
 * }}
 */
export function normalizeOcrExtractLines(rawLines) {
  const out = [];
  let skippedEmptyTitle = 0;
  let skippedNoAmount = 0;
  const skippedTitles = [];
  if (!Array.isArray(rawLines)) {
    return { lines: out, skippedEmptyTitle: 0, skippedNoAmount: 0, skippedTitles };
  }

  for (const line of rawLines) {
    if (!line || typeof line !== 'object') {
      skippedEmptyTitle += 1;
      continue;
    }
    const title = String(line.title || line.description || '').trim();
    if (!title) {
      skippedEmptyTitle += 1;
      continue;
    }
    let taxRate = parseInt(String(
      line.tax_percent != null ? line.tax_percent : (line.tax_rate != null ? line.tax_rate : 19),
    ), 10);
    if (![0, 7, 19].includes(taxRate)) taxRate = 19;

    let qty = parseOcrMoney(line.quantity != null ? line.quantity : '1');
    if (!qty || qty <= 0) qty = 1;

    let kind = String(line.catalog_kind || 'service').toLowerCase();
    if (kind !== 'product' && kind !== 'service') kind = 'service';
    const unit = String(line.unit || 'Stück').trim() || 'Stück';

    let gross = parseOcrMoney(line.line_gross != null ? line.line_gross : '');
    const net = parseOcrMoney(line.line_net != null ? line.line_net : '');
    if (!gross && net) {
      gross = Math.round(net * (1 + taxRate / 100) * 100) / 100;
    }
    const unitNet = parseOcrMoney(line.unit_price_net != null ? line.unit_price_net : '');
    let unitGross = parseOcrMoney(line.unit_price_gross != null ? line.unit_price_gross : '');
    if (!unitGross && unitNet) {
      unitGross = Math.round(unitNet * (1 + taxRate / 100) * 100) / 100;
    }
    if (!gross && unitGross) {
      gross = Math.round(qty * unitGross * 100) / 100;
    }
    if (!unitGross && gross && qty) {
      unitGross = Math.round((gross / qty) * 100) / 100;
    }
    if (!gross) {
      skippedNoAmount += 1;
      if (skippedTitles.length < 5) skippedTitles.push(title);
      continue;
    }
    out.push({
      title,
      catalog_kind: kind,
      quantity: qty,
      unit,
      unit_price_gross: unitGross || gross,
      gross_amount: gross,
      tax_rate: taxRate,
      sku: String(line.sku || '').trim(),
    });
  }

  return { lines: out, skippedEmptyTitle, skippedNoAmount, skippedTitles };
}

/**
 * @param {object} suggestion
 * @param {object} lineApply
 * @param {number} extractLineCount
 * @returns {{ kind: 'ok'|'warn', message: string }}
 */
export function buildApplyStatus(suggestion, lineApply, extractLineCount) {
  const notices = [];
  let kind = 'ok';
  let base = 'Kopfdaten übernommen. Bitte Kontakt aus der Liste bestätigen und Beleg speichern.';

  if (lineApply.count > 0 && lineApply.mode === 'items') {
    base = `Kopfdaten und ${lineApply.count} Positionen in die Rechnungspositionen übernommen. Katalogzuordnung später prüfen, dann speichern.`;
  } else if (lineApply.count > 0 && lineApply.mode === 'splits') {
    base = `Kopfdaten und ${lineApply.count} Positionen als Buchungszeilen übernommen. Konten bitte wählen und speichern.`;
  } else if (extractLineCount > 0 && lineApply.count === 0) {
    base = 'Kopfdaten übernommen. Keine Position mit Betrag übernommen — bitte manuell ergänzen.';
    kind = 'warn';
  }

  const skipped = Number(lineApply.skipped || 0);
  if (skipped > 0) {
    kind = 'warn';
    const bits = [];
    if (lineApply.skippedNoAmount > 0) bits.push(`${lineApply.skippedNoAmount} ohne Betrag`);
    if (lineApply.skippedEmptyTitle > 0) bits.push(`${lineApply.skippedEmptyTitle} ohne Titel`);
    let skipMsg = `${skipped} Position${skipped === 1 ? '' : 'en'} übersprungen`;
    if (bits.length) skipMsg += ` (${bits.join(', ')})`;
    if (Array.isArray(lineApply.skippedTitles) && lineApply.skippedTitles.length) {
      skipMsg += ': ' + lineApply.skippedTitles.join('; ');
    }
    notices.push(skipMsg);
  }

  const amountCheck = suggestion?.extract?.amount_check;
  if (amountCheck && amountCheck.ok === false) {
    kind = 'warn';
    notices.push(amountCheck.detail
      ? `Summenprüfung: ${amountCheck.detail}`
      : 'Summenprüfung: Zeilen und Kopf weichen ab.');
  }

  const warnings = Array.isArray(suggestion?.extract?.warnings)
    ? suggestion.extract.warnings.map((w) => String(w || '').trim()).filter(Boolean)
    : [];
  if (warnings.length) {
    kind = 'warn';
    notices.push('Hinweise: ' + warnings.slice(0, 2).join(' · '));
  }

  if (suggestion?.assistenz?.soft_fail && suggestion.assistenz.soft_fail_reason) {
    notices.push(String(suggestion.assistenz.soft_fail_reason));
  }

  return { kind, message: [base, ...notices].join(' ') };
}

const api = {
  parseOcrMoney,
  normalizeOcrExtractLines,
  buildApplyStatus,
};

if (typeof window !== 'undefined') {
  window.dgVoucherOcrApply = api;
}

export default api;
