/**
 * Mirror of App.tsx receipt parsing (keep in sync for test-belege-ocr.mjs).
 */

export const INVOICE_TOTAL_KEYWORDS =
  /gesamt|summe|total|zu\s*zahlen|endsumme|betrag|brutto|gesamtbetrag|rueckstand|rechnungsbetrag|vorlaeufige|vorläufige/i;

export const INVOICE_GROSS_KEYWORDS =
  /gesamt|summe|total|zu\s*zahlen|endsumme|rechnungsbetrag|brutto|gesamtbetrag|rueckstand|gesamtrueckstand|vorlaeufige|vorläufige/i;

export function normalizeOcrLineText(line) {
  return line
    .replace(/[|]/g, '1')
    .replace(/(\d)[oO](\d)/g, '$10$2')
    .replace(/\b1BANN\b/gi, 'IBAN')
    .replace(/\s+/g, ' ')
    .trim();
}

export function repairOcrAmountLetters(token) {
  return token
    .replace(/[OoQ]/g, '0')
    .replace(/[Il|]/g, '1')
    .replace(/[Zz]/g, '2')
    .replace(/[Ss]/g, '5')
    .replace(/[Bb]/g, '8')
    .replace(/[Mm]/g, '4')
    .replace(/[Gg]/g, '6');
}

export function interpretDigitStringAsAmounts(digits) {
  if (digits.length < 3 || digits.length > 8) return [];
  const amounts = [];
  if (digits.length >= 4) {
    const cents = Number(`${digits.slice(0, -2)}.${digits.slice(-2)}`);
    if (Number.isFinite(cents) && cents !== 0) amounts.push(cents);
  }
  return amounts;
}

export function bruteForceOcrAmountToken(token) {
  if (!/[A-Za-z]/.test(token) || token.length > 14) return null;
  const candidates = [];
  const variants = [token, token.replace(/\./g, '')];
  for (const variant of variants) {
    for (let digit = 0; digit <= 9; digit += 1) {
      const replaced = variant.replace(/[A-Za-z]/, String(digit));
      const digitified = repairOcrAmountLetters(replaced).replace(/[^\d-]/g, '');
      candidates.push(...interpretDigitStringAsAmounts(digitified.replace('-', '')));
      for (let insertPos = 1; insertPos < variant.length; insertPos += 1) {
        for (let insertDigit = 0; insertDigit <= 9; insertDigit += 1) {
          const expanded = `${variant.slice(0, insertPos)}${insertDigit}${variant.slice(insertPos)}`.replace(
            /[A-Za-z]/,
            String(digit),
          );
          const expandedDigits = repairOcrAmountLetters(expanded).replace(/[^\d]/g, '');
          candidates.push(...interpretDigitStringAsAmounts(expandedDigits));
        }
      }
    }
    const repaired = repairOcrAmountLetters(variant).replace(/[^\d]/g, '');
    candidates.push(...interpretDigitStringAsAmounts(repaired));
  }
  const plausible = candidates.filter((amount) => Math.abs(amount) >= 0.01 && Math.abs(amount) < 1_000_000);
  if (plausible.length === 0) return null;
  if (token.includes('.')) {
    const large = plausible.filter((amount) => Math.abs(amount) >= 100);
    if (large.length > 0) {
      if (/^\d{1,2}\.\d{2}/.test(token)) {
        const invoiceScale = large.filter((amount) => Math.abs(amount) >= 1000);
        if (invoiceScale.length > 0) return Math.min(...invoiceScale);
      }
      return Math.max(...large.map(Math.abs)) * (plausible.some((a) => a < 0) ? -1 : 1);
    }
  }
  return Math.max(...plausible.map(Math.abs));
}

export function isImpressumOrLegalLine(line) {
  const lower = line.toLowerCase();
  return (
    /handelsregister|amtsgericht|registergericht|\bhra\b|\bhrb\b|gesch[aä]ftsf[uü]hrer|vertretungsberechtigt|ust-id|ust-idnr|umsatzsteuer-ident|steuer-nr|steuernummer/i.test(
      lower,
    ) ||
    /\bwww\.|\.de\b|\.com\b|e-?mail|kontakt@|info@/i.test(lower) ||
    (/telefon|fax|tel\.|mobil/i.test(lower) && /\d{3,}/.test(line))
  );
}

export function isDateRangeLine(line) {
  return /\d{1,2}[.,]\d{1,2}[.,]?\d{0,4}\s*[-–]\s*\d{1,2}[.,]\d{1,2}[.,]?\d{0,4}/.test(line);
}

export function isContributionPeriodLine(line) {
  return /beitr[aä]ge|nebenforderung|beitragszeitraum|versicherungszeitraum/i.test(line) && isDateRangeLine(line);
}

export function isNetAmountLabelLine(line) {
  if (/gesamtbetrag\s+brutto|endsumme|zu\s*zahlen/i.test(line)) {
    return false;
  }
  if (/nettogesamt/i.test(line)) {
    return true;
  }
  if (/rechnungsbetrag/i.test(line)) {
    return false;
  }
  return /\bnetto\b/i.test(line) && !/\bbrutto\b/i.test(line);
}

export function isDiscountOrAdjustmentLine(line) {
  return /rabatt|skonto|nachlass|erm[aä]ßigung|aktionsrabatt|aktionsdauerrabatt/i.test(line);
}

export function isTaxSubtotalLabelLine(line) {
  return (
    /\b(nettowert|nettopreis|basisbetrag|steuerbetrag|steuercode|mwst\.?)\b/i.test(line) &&
    !/gesamtbetrag|rechnungsbetrag/i.test(line)
  );
}

function isCurrencyOnlyLine(line) {
  return /^(?:EUR|€|Euro)$/i.test(line.trim());
}

function dedupeGermanAmountTokens(tokens) {
  const filtered = tokens.filter((entry, _, list) => {
    return !list.some((other) => {
      if (other === entry) return false;
      if (
        entry.index >= other.index &&
        entry.index + entry.length <= other.index + other.length &&
        other.length > entry.length
      ) {
        return true;
      }
      if (other.index < entry.index && other.index + other.length > entry.index) {
        return /^\d{1,3}(?:\.\d{3})+,\d{2}/.test(other.token);
      }
      return false;
    });
  });
  const seen = new Set();
  return filtered.filter((entry) => {
    const key = `${entry.index}:${entry.length}:${entry.amount.toFixed(2)}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
}

export function amountFromDateRangeLine(line, amount) {
  if (isDateRangeLine(line)) {
    if (amount < 0) return true;
    const digitsOnly = line.replace(/[^\d]/g, '');
    const amountDigits = String(Math.round(Math.abs(amount) * 100)).replace(/^0+/, '');
    return amountDigits.length >= 4 && digitsOnly.includes(amountDigits);
  }

  if (/\bbis\s+\d{1,2}[./-]\d{1,2}[./-]\d{2,4}\b/i.test(line)) {
    return true;
  }

  if (/\bsaldo\b/i.test(line) && /\d{1,2}[./-]\d{1,2}[./-]\d{2,4}/.test(line)) {
    return true;
  }

  return false;
}

export function normalizeOcrAmountToken(token) {
  let negative = false;
  let cleaned = token.replace(/\s+/g, ' ').trim();
  if (/^[-−]/.test(cleaned)) {
    negative = true;
    cleaned = cleaned.slice(1).trim();
  }
  cleaned = cleaned
    .replace(/\s*[bB](?:\s*[pP])?\s*$/g, '')
    .replace(/€/g, '')
    .replace(/\s*(?:EUR|Euro)\s*/gi, '')
    .replace(/\s/g, '');

  if (/^\d{1,2}\.\d{2},\d{2}$/.test(cleaned)) {
    const fixed = `${cleaned.slice(0, 1)}${cleaned.slice(2).replace(',', '.')}`;
    return negative ? `-${fixed}` : fixed;
  }

  if (/^-?\d{1,5}\.\d{2}$/.test((negative ? '-' : '') + cleaned)) {
    return `${negative ? '-' : ''}${cleaned}`;
  }

  const digitsOnly = cleaned.replace(/[^\d]/g, '');
  if (/^\d{4,7}$/.test(digitsOnly) && !cleaned.includes(',') && !/\.\d{2}$/.test(cleaned)) {
    const value = `${digitsOnly.slice(0, -2)}.${digitsOnly.slice(-2)}`;
    return negative ? `-${value}` : value;
  }

  if (/^\d{1,2}\.\d{3}\d{2}$/.test(repairOcrAmountLetters(cleaned).replace(/[^\d.]/g, ''))) {
    const fixedDigits = repairOcrAmountLetters(cleaned).replace(/[^\d]/g, '');
    if (fixedDigits.length >= 5) {
      const value = `${fixedDigits.slice(0, -2)}.${fixedDigits.slice(-2)}`;
      return negative ? `-${value}` : value;
    }
  }

  if (/[A-Za-z]/.test(cleaned)) {
    const bruteForced = bruteForceOcrAmountToken(cleaned);
    if (bruteForced !== null) {
      const value = bruteForced.toFixed(2);
      return negative ? `-${value}` : value;
    }
  }

  cleaned = repairOcrAmountLetters(cleaned);
  if (/^\d{1,3}(\.\d{3})+,\d{2}$/.test(cleaned)) {
    const value = cleaned.replace(/\./g, '').replace(',', '.');
    return negative ? `-${value}` : value;
  }
  cleaned = cleaned.replace(/(\d)[.,](\d{2})$/, '$1.$2');
  const result = cleaned.replace(',', '.');
  return negative && !result.startsWith('-') ? `-${result}` : result;
}

export function parseReceiptAmount(input) {
  const normalizedInput = normalizeOcrAmountToken(input);
  const amount = Number(normalizedInput);
  return Number.isFinite(amount) && amount !== 0 ? Math.round(amount * 100) / 100 : null;
}

export function parseSpaceDecimalAmount(token) {
  const match = token.trim().match(/^(-?\d{1,4})\s(\d{2})$/);
  return match ? parseReceiptAmount(`${match[1]},${match[2]}`) : null;
}

export function extractGermanAmountTokens(line) {
  const tokens = [];
  const allowCompactDigits = INVOICE_GROSS_KEYWORDS.test(line) || /\b(?:total|betrag)\s+eur\b/i.test(line);
  const patterns = [
    /-?\d{1,3}(?:\.\d{3})*,\d{2}/g,
    /(?<!\d\.)\d{1,4},\d{2}(?!\d)/g,
    /-?\d{1,2}\.\d{3}\d{2}/g,
    /-?\d{1,5}\.\d{2}(?!\d)/g,
    /-?\d{1,4}[,.]\d{2}(?=\s*(?:EUR|€|Euro))?(?:\s*[bB])?\s*$/g,
    /-?\d{1,4}\s\d{2}(?=\s*$)/g,
  ];
  if (allowCompactDigits) {
    patterns.push(/-?\d{5,6}(?!\d)/g);
  }
  for (const pattern of patterns) {
    let match = pattern.exec(line);
    while (match) {
      const token = match[0];
      const amount =
        token.includes(' ') && !token.includes(',') && !token.includes('.')
          ? parseSpaceDecimalAmount(token)
          : parseReceiptAmount(token);
      if (amount !== null && amount !== 0) {
        tokens.push({ token, amount, index: match.index ?? 0, length: token.length });
      }
      match = pattern.exec(line);
    }
  }
  return dedupeGermanAmountTokens(tokens);
}

export function extractLastGermanAmount(line) {
  const totalMatch = line.match(/Total\s+EUR\s+(-?\d{1,3}(?:\.\d{3})*,\d{2}|-?\d+[,.]\d{2})/i);
  if (totalMatch?.[1]) {
    const amount = parseReceiptAmount(totalMatch[1]);
    if (amount !== null) return { token: totalMatch[1], amount, index: totalMatch.index ?? 0 };
  }

  const endPatterns = [
    /(-?\d{1,3}(?:\.\d{3})*,\d{2})(?:\s*(?:EUR|€|Euro))?\s*[bB]?\s*$/,
    /(-?\d{1,4}[,.]\d{2})(?:\s*(?:EUR|€|Euro))?\s*[bB]?\s*$/,
    /(-?\d{1,4})\s(\d{2})(?:\s*(?:EUR|€|Euro))?\s*[bB]?\s*$/,
  ];
  for (const pattern of endPatterns) {
    const match = line.match(pattern);
    if (!match) continue;
    const token = match[2] ? `${match[1]} ${match[2]}` : match[1];
    const amount =
      match[2] && !match[1].includes(',') && !match[1].includes('.')
        ? parseSpaceDecimalAmount(token)
        : parseReceiptAmount(match[2] ? `${match[1]},${match[2]}` : match[1]);
    if (amount !== null && amount !== 0) return { token, amount, index: match.index ?? 0 };
  }
  const tokens = extractGermanAmountTokens(line);
  return tokens.length > 0 ? tokens[tokens.length - 1] : null;
}

export function isDocumentTitleLine(line) {
  return /^(rechnung|lieferschein|quittung|gutschrift|mahnung|zahlungserinnerung|angebot|auftrag|barverkauf|guthabenauszahlung)\b/i.test(
    line.trim(),
  );
}

export function isReferenceNumberLine(line) {
  return /^(?:nr|nummer|beleg|kd|kunden?|trace|bon|kasse|verk[aä]ufer)[.:\s#-]/i.test(line.trim());
}

export function isDateFragmentAmount(line, amountToken) {
  if (isDateRangeLine(line) || isContributionPeriodLine(line)) return true;
  const dateMatch = line.match(/\b(\d{1,2})[./](\d{1,2})[./]?(\d{0,4})\b/);
  if (!dateMatch) return false;
  const day = Number(dateMatch[1]);
  const month = Number(dateMatch[2]);
  if (day < 1 || day > 31 || month < 1 || month > 12) return false;
  const normalizedAmount = amountToken.replace(/\s/g, '').replace(',', '.');
  const dayMonth = `${dateMatch[1]}.${dateMatch[2]}`;
  const dayMonthComma = `${dateMatch[1]},${dateMatch[2]}`;
  return normalizedAmount === dayMonth || normalizedAmount === dayMonthComma;
}

export function isPaymentInstructionLine(line) {
  const lower = line.toLowerCase();
  return (
    /zahlungserinnerung|zahlungsaufforderung|kartenzahlung|girocard|mastercard|contactless|zahlung\s+erfolgt|trace-nr|beleg-nr|uhrzeit:|incl\.\s*\d+[,.]?\d*%?\s*mwst|netto-warenwert/i.test(
      lower,
    ) || (/verwendungszweck/i.test(lower) && !/\d+[,.]\d{2}/.test(line))
  );
}

export function isBankMetadataLine(line) {
  const lower = line.toLowerCase();
  return (
    /bankverbindung|kontoverbindung|\biban\b|\bbic\b|\bempf[aä]nger\b|^bank$/i.test(lower) ||
    /\bDE\s?\d{2}\s?\d{4}/i.test(line) ||
    /1bann/i.test(line)
  );
}

export function shouldSkipOcrSourceLine(line) {
  const lower = line.toLowerCase();
  return (
    isPaymentInstructionLine(line) ||
    isReferenceNumberLine(line) ||
    isDocumentTitleLine(line) ||
    isImpressumOrLegalLine(line) ||
    isContributionPeriodLine(line) ||
    /^datum[:\s]/i.test(line) ||
    /zahlbar\s*bis|leistungsdatum|rechnungsdatum|belegdatum|bankverbindung|seite\s+\d|sehr\s+geehrte|leergut|leerkasten|pfandkasten|kd-nr|kundenbeleg|verk[aä]ufer:|lieferzeitpunkt:/i.test(
      lower,
    ) ||
    (/summe\s+lfs|lieferschein\s*:|vorgang\s*:/i.test(line) && !/\d+[,.]\d{2}\s*(?:EUR|€)/i.test(line)) ||
    /^(?:pos|menge|wgr|art-nr|bezeichnung|mwst|ust\b|einzel|gesamt|g-preis|e-preis|anzahl)\b/i.test(line.trim()) ||
    /\bmwst\.?\s*:\s*eur\b/i.test(line.trim()) ||
    /^(?:nr|nummer|kunden?-?nr|vorgang|referenz|verwendungszweck)[.:\s]/i.test(line) ||
    /^\d{10,}$/.test(line.replace(/\s/g, ''))
  );
}

export function isTotalSummaryDescription(description) {
  const core = description.toLowerCase().replace(/[0-9.,\s€$/-]/g, '').trim();
  return /^(gesamt|gesamtsumme|summe|total|betrag|gesamtbetrag|zwischensumme|brutto|netto|warenwert|rechnungsbetrag|mwst|ust|steuer|vorlaeufige|vorläufige)$/.test(core);
}

export function isPlausibleProductDescription(description) {
  const letters = (description.match(/[a-zA-ZäöüÄÖÜß]{2,}/g) ?? []).join('');
  return letters.length >= 3;
}

export function cleanReceiptDescription(description) {
  return description
    .replace(/^\d+\s*[xX]\s*/, '')
    .replace(/\s+-?\d+[,.]\d{2}(?:\s+-?\d+[,.]\d{2})*\s*(?:EUR)?\s*$/gi, '')
    .replace(/\s+\d{1,2}\s*%\s*(?:mwst|ust).*$/i, '')
    .replace(/\s+/g, ' ')
    .trim();
}

export function shouldSkipReceiptLine(description) {
  const core = description.toLowerCase().replace(/[0-9.,%€\s-]/g, '').trim();
  if (isImpressumOrLegalLine(description)) return true;
  return [
    'summe',
    'gesamt',
    'total',
    'mwst',
    'ust',
    'netto',
    'brutto',
    'karte',
    'datum',
    'iban',
    'bic',
    'ware',
    'pfand',
    'beiträge',
    'beitrage',
    'nebenforderung',
    'gesamtrückstand',
    'gesamtrueckstand',
  ].includes(core);
}

export function looksLikeStandaloneAmountLine(line) {
  const normalized = normalizeOcrLineText(line);
  const withoutCurrency = normalized.replace(/\s*(?:EUR|€|Euro)\s*/gi, ' ').replace(/\s*[bB]\s*$/g, '').trim();
  const letters = (withoutCurrency.match(/[a-zA-ZäöüÄÖÜß]/g) ?? []).length;
  if (letters >= 4) return false;
  const amount = extractLastGermanAmount(normalized);
  return amount !== null && Math.abs(amount.amount) >= 0.01;
}

export function looksLikeProductLine(line) {
  const normalized = normalizeOcrLineText(line);
  if (shouldSkipOcrSourceLine(normalized) || isBankMetadataLine(normalized) || isDocumentTitleLine(normalized)) return false;
  const letterCount = (normalized.match(/[a-zA-ZäöüÄÖÜß]/g) ?? []).length;
  return letterCount >= 3 && !looksLikeStandaloneAmountLine(normalized);
}

export function looksLikeProductFragment(line) {
  const normalized = normalizeOcrLineText(line);
  if (shouldSkipOcrSourceLine(normalized) || isBankMetadataLine(normalized)) return false;
  if (/^\d{5,9}$/.test(normalized)) return true;
  if (/^\d{5,9}\s/.test(normalized) && !looksLikeStandaloneAmountLine(normalized)) return true;
  const letters = (normalized.match(/[a-zA-ZäöüÄÖÜß]{2,}/g) ?? []).length;
  return letters >= 1 && !looksLikeStandaloneAmountLine(normalized) && normalized.length >= 2;
}

export function mergeOcrLinesForReceiptParsing(lines) {
  const merged = [];
  let buffer = [];

  const flushBuffer = (amountLine) => {
    if (buffer.length === 0) return false;
    if (amountLine && looksLikeStandaloneAmountLine(amountLine)) {
      const amount = extractLastGermanAmount(normalizeOcrLineText(amountLine));
      if (amount) {
        merged.push(`${buffer.join(' ')} ${amount.token}`);
        buffer = [];
        return true;
      }
    }
    merged.push(...buffer);
    buffer = [];
    return false;
  };

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    const nextLine = lines[index + 1];

    if (line && nextLine && looksLikeProductLine(line) && looksLikeStandaloneAmountLine(nextLine)) {
      flushBuffer();
      const amount = extractLastGermanAmount(normalizeOcrLineText(nextLine));
      if (amount) {
        merged.push(`${line} ${amount.token}`);
        index += 1;
        continue;
      }
    }

    if (looksLikeProductFragment(line) && !looksLikeStandaloneAmountLine(line)) {
      buffer.push(line);
      if (nextLine && looksLikeStandaloneAmountLine(nextLine)) {
        if (flushBuffer(nextLine)) {
          index += 1;
          continue;
        }
      }
      if (buffer.length >= 8) flushBuffer();
      continue;
    }

    flushBuffer();
    merged.push(line);
  }
  flushBuffer();
  return merged;
}

export function parseRetailTableRow(line) {
  const normalized = normalizeOcrLineText(line);
  const tableMatch = normalized.match(
    /^\d+\s+-?\d+\s+\d+\s+(\d{5,9})\s+(.+?)\s+\d+[,.]\d{2}\s+(-?\d+[,.]\d{2})\s+(-?\d+[,.]\d{2})\s*(?:EUR)?\s*$/i,
  );
  if (tableMatch) {
    const description = cleanReceiptDescription(`${tableMatch[1]} ${tableMatch[2]}`);
    const amount = parseReceiptAmount(tableMatch[4]);
    if (description && amount !== null) return { description, amount, sourceLine: normalized };
  }
  const articleMatch = normalized.match(/^(\d{4,9})\s+(.+)$/);
  if (articleMatch) {
    const tokens = extractGermanAmountTokens(normalized);
    if (tokens.length > 0) {
      const lineTotal = tokens[tokens.length - 1];
      let description = cleanReceiptDescription(`${articleMatch[1]} ${articleMatch[2]}`);
      if (description && !shouldSkipReceiptLine(description) && !isTotalSummaryDescription(description)) {
        return { description, amount: lineTotal.amount, sourceLine: normalized };
      }
    }
  }
  return null;
}

export function isPlausibleNegativeAmount(line, amount) {
  if (amount >= 0) return true;
  const lower = line.toLowerCase();
  return /gutschrift|erstattung|rueckzahl|rückzahl|guthaben|retoure|storno|korrektur|-\s*\d+[,.]\d{2}/i.test(lower);
}

export function extractPlausibleLineAmount(line) {
  const retail = parseRetailTableRow(line);
  if (retail) return retail;

  const normalized = normalizeOcrLineText(line.replace(/\s+/g, ' ').trim());
  if (
    isBankMetadataLine(normalized) ||
    shouldSkipOcrSourceLine(normalized) ||
    isReferenceNumberLine(normalized) ||
    isImpressumOrLegalLine(normalized)
  ) {
    return null;
  }

  const suffix = '(-?\\d{1,3}(?:\\.\\d{3})*,\\d{2}|-?\\d{1,5}\\.\\d{2}|-?\\d{1,4}[,.]\\d{2})';
  const trailingMatch = normalized.match(new RegExp(`^(.{3,}?)\\s+${suffix}\\s*(?:EUR|€|Euro)?\\s*[bB]?\\s*$`, 'i'));
  if (trailingMatch) {
    const description = cleanReceiptDescription(trailingMatch[1]);
    const amountToken = trailingMatch[2];
    if (isDateFragmentAmount(normalized, amountToken)) return null;
    const amount = parseReceiptAmount(amountToken);
    if (
      description &&
      amount !== null &&
      !amountFromDateRangeLine(normalized, amount) &&
      isPlausibleNegativeAmount(normalized, amount) &&
      isPlausibleProductDescription(description) &&
      !shouldSkipReceiptLine(description) &&
      !isDocumentTitleLine(description) &&
      !isTotalSummaryDescription(description)
    ) {
      return { description, amount, sourceLine: normalized };
    }
  }

  const lastAmount = extractLastGermanAmount(normalized);
  if (
    !lastAmount ||
    isDateFragmentAmount(normalized, lastAmount.token) ||
    amountFromDateRangeLine(normalized, lastAmount.amount) ||
    !isPlausibleNegativeAmount(normalized, lastAmount.amount)
  ) {
    return null;
  }
  const description = cleanReceiptDescription(normalized.slice(0, lastAmount.index).trim());
  if (
    !description ||
    !isPlausibleProductDescription(description) ||
    shouldSkipReceiptLine(description) ||
    isDocumentTitleLine(description) ||
    isTotalSummaryDescription(description)
  ) {
    return null;
  }
  return { description, amount: lastAmount.amount, sourceLine: normalized };
}

function dedupeRetailItems(items) {
  const byAmount = new Map();
  for (const item of items) {
    const key = item.amount.toFixed(2);
    const existing = byAmount.get(key);
    if (!existing || item.description.length > existing.description.length) {
      byAmount.set(key, item);
    }
  }
  return [...byAmount.values()];
}

export function parseGermanInvoiceLine(line) {
  const normalizedLine = normalizeOcrLineText(line);
  const stueckMatch = normalizedLine.match(
    /^(\d+[,.]\d+)\s*Stück\s+(.+?)\s+(\d+[,.]\d{2})\s+(\d+[,.]\d{2})(?:\s+\d+[,.]\d+%)?/i,
  );

  if (stueckMatch) {
    const description = cleanReceiptDescription(stueckMatch[2]);
    const amount = parseReceiptAmount(stueckMatch[4]);
    if (description && amount !== null && !shouldSkipReceiptLine(description)) {
      return { description, amount, sourceLine: normalizedLine };
    }
  }

  const looseStueckMatch = normalizedLine.match(/(\d+[,.]\d+)\s*Stück\s+(.+)/i);
  if (looseStueckMatch) {
    const amountTokens = extractGermanAmountTokens(normalizedLine);
    const lineTotal = amountTokens.length > 0 ? amountTokens[amountTokens.length - 1].amount : null;
    const description = cleanReceiptDescription(
      looseStueckMatch[2].replace(/\s+\d+[,.]\d{2}(?:\s+\d+[,.]\d{2})*.*$/, '').trim(),
    );
    if (description && lineTotal !== null && !shouldSkipReceiptLine(description)) {
      return { description, amount: lineTotal, sourceLine: normalizedLine };
    }
  }

  return null;
}

export function parseReceiptLines(lines) {
  const items = [];
  const merged = mergeOcrLinesForReceiptParsing(lines.map((l) => normalizeOcrLineText(l)));

  merged.forEach((line, index) => {
    if (shouldSkipOcrSourceLine(line)) return;

    const germanInvoiceItem = parseGermanInvoiceLine(line);
    if (germanInvoiceItem) {
      items.push({ id: `item-${index}`, ...germanInvoiceItem });
      return;
    }

    const parsed = extractPlausibleLineAmount(line);
    if (!parsed) return;
    if (isTotalSummaryDescription(parsed.description)) return;
    if (/^(?:betrag|total)\s*(?:eur|€)?$/i.test(parsed.description.trim())) return;
    items.push({ id: `item-${index}`, ...parsed });
  });

  const seen = new Set();
  const deduped = dedupeRetailItems(items).filter((item) => {
    const key = `${item.description.toLowerCase()}|${item.amount.toFixed(2)}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });

  if (deduped.length > 0) return deduped;
  return buildFallbackRecognizedItems(lines);
}

function findLabeledGrossOnAdjacentLines(lines, index, radius = 5) {
  if (isNetAmountLabelLine(lines[index] ?? '')) {
    return null;
  }

  const tryNeighbor = (neighborIndex) => {
    const neighbor = lines[neighborIndex];
    if (!neighbor) return null;
    if (isBankMetadataLine(neighbor) || isImpressumOrLegalLine(neighbor)) return null;
    if (isDateRangeLine(neighbor) || isContributionPeriodLine(neighbor)) return null;
    if (/zahlbar\s+bis/i.test(neighbor)) return null;
    if (isNetAmountLabelLine(neighbor) || isDiscountOrAdjustmentLine(neighbor)) return null;
    if (isCurrencyOnlyLine(neighbor) || isTaxSubtotalLabelLine(neighbor)) return null;
    const tokens = extractGermanAmountTokens(neighbor);
    const last = tokens[tokens.length - 1];
    if (!last || Math.abs(last.amount) < 0.5 || Math.abs(last.amount) >= 500000) return null;
    if (last.amount < 0 && isNearDiscountLabel(lines, neighborIndex)) return null;
    const labelLine = lines[index] ?? '';
    const allowsNegativeFromLabel =
      /vorlaeufige|vorläufige|gutschrift|guthaben|erstattung|retoure|storno|korrektur|auszahlung/i.test(labelLine);
    if (last.amount < 0 && !allowsNegativeFromLabel && !isPlausibleNegativeAmount(neighbor, last.amount)) {
      return null;
    }
    return { value: last.amount.toFixed(2), line: neighbor };
  };

  const sameLine = tryNeighbor(index);
  if (sameLine) return sameLine;

  for (let offset = 1; offset <= radius; offset += 1) {
    const forward = tryNeighbor(index + offset);
    if (forward) return forward;
  }

  for (let offset = 1; offset <= radius; offset += 1) {
    const backward = tryNeighbor(index - offset);
    if (backward) return backward;
  }

  return null;
}

export function extractLabeledGrossAmount(lines, index) {
  const labeled = findLabeledGrossOnAdjacentLines(lines, index);
  if (!labeled) return null;
  const amount = Number(labeled.value);
  return Number.isFinite(amount) ? amount : null;
}

function isNearDiscountLabel(lines, index, radius = 4) {
  for (let scan = Math.max(0, index - radius); scan <= Math.min(lines.length - 1, index + radius); scan += 1) {
    if (isDiscountOrAdjustmentLine(lines[scan])) {
      return true;
    }
  }
  return false;
}

function findBestEurLineGrossAmount(lines) {
  let best = null;

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    const previousLine = lines[index - 1] ?? '';

    if (isBankMetadataLine(line) || isImpressumOrLegalLine(line) || isPaymentInstructionLine(line)) continue;
    if (/zahlbar\s+bis/i.test(line)) continue;
    if (isDateRangeLine(line) || isContributionPeriodLine(line)) continue;
    if (isNetAmountLabelLine(previousLine) || isNetAmountLabelLine(line) || isDiscountOrAdjustmentLine(line)) continue;
    if (isTaxSubtotalLabelLine(previousLine) || isTaxSubtotalLabelLine(line)) continue;
    if (isNearDiscountLabel(lines, index)) continue;

    const match = line.match(
      /\b(\d{1,3}(?:\.\d{3})*,\d{2}|\d{1,2}\.\d{2},\d{2}|\d{2,6}[,.]\d{2}|\d{3,6}[A-Za-z]\d{1,2}|\d{1,2}\.\d{3}\d{2}|\d{4,7})\s*(?:EUR|€|Euro)\b/i,
    );
    if (!match?.[1]) continue;

    const amount = parseReceiptAmount(match[1]);
    if (amount === null || Math.abs(amount) < 1 || Math.abs(amount) >= 500000) continue;
    if (amount < 0 && !/gutschrift|storno|erstattung|korrektur/i.test(line)) continue;
    if (/mwst|ust\b|steuer/i.test(line) && !/gesamtbetrag|rechnungsbetrag/i.test(line)) continue;

    if (!best || Math.abs(amount) > Math.abs(best.amount)) {
      best = { value: amount.toFixed(2), line, amount };
    }
  }

  return best ? { value: best.value, line: best.line } : null;
}

export function findGrossAmountOcrTolerant(lines) {
  for (const line of lines) {
    const totalMatch = line.match(/Total\s+EUR\s+(-?\d+[,.]\d{2})/i);
    if (totalMatch?.[1]) {
      const amount = parseReceiptAmount(totalMatch[1]);
      if (amount !== null) return { value: amount.toFixed(2), line };
    }
  }

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (/bitte\s+[uü]berweisen/i.test(line)) {
      continue;
    }
    if (/rechnungsbetrag|gesamtbetrag|gesamtsumme|gesamtr[uü]ckstand|offene\s+gesamtsumme|vorlaeufige|vorläufige|gesamtbetrag\s+brutto|zu\s*zahlende[r]?\s+gesamtbetrag|endbetrag/i.test(line)) {
      if (isNetAmountLabelLine(line)) {
        continue;
      }
      const labeled = findLabeledGrossOnAdjacentLines(lines, index);
      if (labeled) return labeled;
      const tokens = extractGermanAmountTokens(line);
      const last = tokens[tokens.length - 1];
      if (last && Math.abs(last.amount) >= 0.5) {
        if (!(last.amount < 0 && !/gutschrift|storno|erstattung|korrektur/i.test(line))) {
          return { value: last.amount.toFixed(2), line };
        }
      }
    }
  }

  for (const line of lines) {
    if (!/\bbetrag\b/i.test(line) && !INVOICE_GROSS_KEYWORDS.test(line)) continue;
    if (isNetAmountLabelLine(line) || isTaxSubtotalLabelLine(line)) continue;
    if (/^betrag\s*(?:eur|€)?$/i.test(line.trim())) continue;
    if (/bitte\s+[uü]berweisen/i.test(line)) continue;
    if (/zahlbar\s+bis/i.test(line)) continue;
    if (isDateRangeLine(line) || isContributionPeriodLine(line)) continue;
    if (/summe\s+lfs|lieferschein\s*:|vorgang\s*:/i.test(line) && !/rechnungsbetrag|gesamtbetrag|gesamtsumme|vorlaeufige|vorläufige/i.test(line)) continue;
    if (/\d{7,}/.test(line.replace(/\s/g, '')) && !/\d+[,.]\d{2}/.test(line)) continue;
    const tokens = extractGermanAmountTokens(line);
    const last = tokens[tokens.length - 1];
    if (last && Math.abs(last.amount) >= 0.5 && Math.abs(last.amount) < 500000) {
      if (last.amount < 0 && !/gutschrift|storno|erstattung|korrektur/i.test(line)) {
        continue;
      }
      return { value: last.amount.toFixed(2), line };
    }
  }

  const eurGross = findBestEurLineGrossAmount(lines);
  if (eurGross) return eurGross;

  let bestLine = '';
  let bestAmount = 0;
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (
      isBankMetadataLine(line) ||
      shouldSkipOcrSourceLine(line) ||
      isPaymentInstructionLine(line) ||
      isImpressumOrLegalLine(line) ||
      isDateRangeLine(line) ||
      isContributionPeriodLine(line) ||
      /zahlbar\s+bis/i.test(line)
    ) {
      continue;
    }
    if (isTaxSubtotalLabelLine(lines[index - 1] ?? '') || isTaxSubtotalLabelLine(line)) {
      continue;
    }
    const lastAmount = extractLastGermanAmount(line);
    if (
      lastAmount &&
      lastAmount.amount > bestAmount &&
      lastAmount.amount < 500000 &&
      !amountFromDateRangeLine(line, lastAmount.amount) &&
      !(lastAmount.amount < 0 && isNearDiscountLabel(lines, index))
    ) {
      bestAmount = lastAmount.amount;
      bestLine = line;
    }
  }

  if (bestAmount >= 0.5) {
    return { value: bestAmount.toFixed(2), line: bestLine };
  }

  return { value: '', line: '' };
}

const HEALTH_INSURER_SUPPLIER_RE =
  /\b(?:DAK|AOK|Barmer|Techniker|IKK|BKK|KKH|HEK|hkk|TK)\s+Gesundheit\b|Techniker\s+Krankenkasse|\bKrankenkasse\b/i;
const HEALTH_INSURER_SHORT_BRAND_RE = /^(?:DAK|AOK|TK|IKK|BKK|KKH|HEK|hkk|Barmer|Techniker)\b/i;

function findHealthInsurerSupplierLine(lines) {
  const headerLines = lines.slice(0, 20);

  const namedInsurer = headerLines.find(
    (line) => HEALTH_INSURER_SUPPLIER_RE.test(line.trim()) && !isBankMetadataLine(line),
  );
  if (namedInsurer) return namedInsurer.trim();

  const shortBrand = headerLines.find(
    (line) => HEALTH_INSURER_SHORT_BRAND_RE.test(line.trim()) && !isBankMetadataLine(line),
  );
  if (shortBrand) return shortBrand.trim();

  return '';
}

function findSupplierFromLines(lines) {
  const labeled = lines.find((line) =>
    /^(?:aussteller|lieferant|verk[aä]ufer|anbieter|absender)[:\s-]+(.+)/i.test(line),
  );
  if (labeled) {
    const match = labeled.match(/^(?:aussteller|lieferant|verk[aä]ufer|anbieter|absender)[:\s-]+(.+)/i);
    if (match?.[1] && !isBankMetadataLine(match[1])) return match[1].trim();
  }

  const healthInsurer = findHealthInsurerSupplierLine(lines);
  if (healthInsurer) return healthInsurer;

  const company = lines
    .slice(0, 25)
    .find(
      (line) =>
        (/\b(GmbH|GmbH\s*&\s*Co|KG|AG|UG|e\.?\s*K\.?|GbR|OHG|SE|eG|Gesundheit)\b/i.test(line) ||
          (/^[A-ZÄÖÜ][\wÄÖÜäöüß&.\- ]{2,}$/.test(line.trim()) &&
            line.length < 72 &&
            !/rechnung|datum|summe|herrn|frau|straße|strasse/i.test(line))) &&
        !isBankMetadataLine(line) &&
        !isImpressumOrLegalLine(line) &&
        !/^herrn\b|^frau\b/i.test(line.trim()),
    );
  if (company) return company;

  return lines.find((line) => /e\.k\.|gmbh|kg|ohg|ag\b|ug\b|inc\.|ltd\./i.test(line) && !isBankMetadataLine(line)) ?? '';
}

function findInvoiceNumberFromLines(lines) {
  for (const line of lines) {
    const inline = line.match(
      /(?:rechnungs?-?\s*nr\.?|rechnung\s*nummer|beleg\s*nr\.?|invoice\s*(?:no|#|nr))[:\s-]*([A-Z0-9\-/.]+)/i,
    );
    if (inline?.[1] && inline[1].length >= 3) return inline[1];
  }

  for (let index = 0; index < lines.length; index += 1) {
    if (/rechnungs?-?\s*nr|rechnung\s*nummer|vorgangs-?\s*nr|auftragsnr/i.test(lines[index])) {
      const next = lines[index + 1]?.trim();
      if (next && /^[A-Z0-9][A-Z0-9\-/.]{2,}$/i.test(next) && !/\d{1,2}[./-]\d{1,2}/.test(next)) {
        return next;
      }
    }
  }

  const vorgang = lines
    .map((line) => line.match(/vorgang\s*:\s*(\d{4,})/i)?.[1])
    .find(Boolean);
  if (vorgang) return vorgang;

  return '';
}

function findVatIdFromLines(lines) {
  for (const line of lines) {
    const labeled = line.match(/(?:ust(?:-|\s*)id(?:nr\.?)?|umsatzsteuer(?:-|\s*)id)[:\s-]*([A-Z]{2}\s?[A-Z0-9 ]{8,})/i);
    if (labeled?.[1]) return labeled[1].replace(/\s/g, '');

    const garbled = line.match(/U\s*S[\s-]*H?0?\s*DE\s*Z?\s*([0-9A-Za-z\s]{8,12})/i);
    if (garbled) {
      const digits = garbled[0].replace(/[^0-9]/g, '');
      if (digits.length >= 9) return `DE${digits.slice(0, 9)}`;
    }

    const direct = line.match(/\b(DE\s?[0-9]{9})\b/i);
    if (direct?.[1]) return direct[1].replace(/\s/g, '');
  }
  return '';
}

export function analyzeInvoiceText(text) {
  const lines = text
    .split(/\r?\n/)
    .map((line) => normalizeOcrLineText(line))
    .filter((line) => line.replace(/\s/g, '').length > 1);
  const gross = findGrossAmountOcrTolerant(lines);
  const supplierLine = findSupplierFromLines(lines);
  const ibanLine = lines.find((line) => /\bDE\s?\d{2}/i.test(line) || /\biban\b/i.test(line)) ?? '';
  const bicLine = lines.find((line) => /\bbic\b/i.test(line) || /\b[A-Z]{4}DE[A-Z0-9]{2,}\b/.test(line)) ?? '';
  const invoiceNumber = findInvoiceNumberFromLines(lines);
  const invoiceDate =
    lines
      .map((line) =>
        line.match(/(?:rechnungsdatum|belegdatum|datum)[:\s-]*(\d{1,2}[./-]\d{1,2}[./-]\d{2,4})/i)?.[1],
      )
      .find(Boolean) ??
    lines
      .map((line) => line.match(/\b(\d{1,2}[./-]\d{1,2}[./-]\d{2,4})\b/)?.[1])
      .find((value) => value && !/^01[./-]01/.test(value)) ??
    '';
  const taxNumber =
    lines
      .map((line) => line.match(/(?:steuer(?:nummer|-nr\.?)|st(?:euer)?-?nr\.?)[:\s-]*([0-9/ \-]{8,})/i)?.[1])
      .find(Boolean) ?? '';
  const vatId = findVatIdFromLines(lines);
  const tseLines = lines.filter((line) => /tse|tss|signatur|transaktion|seriennummer|kassen/i.test(line));
  const parsedItems = parseReceiptLines(lines);

  return {
    lines,
    supplier: supplierLine,
    ibanLine,
    bicLine,
    invoiceNumber,
    invoiceDate,
    taxNumber,
    vatId,
    grossAmount: gross.value,
    grossLine: gross.line,
    itemCount: parsedItems.length,
    tseLineCount: tseLines.length,
  };
}

export function buildFallbackRecognizedItems(lines) {
  const grossResult = findGrossAmountOcrTolerant(lines);
  let gross = grossResult.value ? parseReceiptAmount(grossResult.value) : null;
  if (gross === null) {
    const amounts = lines.flatMap((line) => {
      if (line.replace(/\s/g, '').length <= 2) return [];
      if (!/\d+[,.]\d{2}/.test(line)) return [];
      const last = extractLastGermanAmount(normalizeOcrLineText(line));
      if (!last || shouldSkipOcrSourceLine(line) || isPaymentInstructionLine(line) || isBankMetadataLine(line)) return [];
      return [last.amount];
    });
    gross = amounts.length > 0 ? amounts.reduce((best, a) => (Math.abs(a) > Math.abs(best) ? a : best), amounts[0]) : null;
  }
  if (gross === null) return [];
  return [{ id: 'total', description: 'Gesamtbetrag aus OCR', amount: gross, sourceLine: grossResult.line }];
}
