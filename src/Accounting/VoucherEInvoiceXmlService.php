<?php
declare(strict_types=1);

/**
 * E-Rechnung als UBL-Invoice-XML (XRechnung-Profil, pragmatisch EN 16931).
 * Für Rechnungsarten; sonst leerer String.
 */
final class VoucherEInvoiceXmlService
{
    /**
     * @param array<string, mixed> $voucher
     */
    public static function supports(array $voucher): bool
    {
        $kind = VoucherDocumentKind::sanitize((string) ($voucher['document_kind'] ?? ''));
        $isInvoice = in_array($kind, [
            VoucherDocumentKind::PARTIAL_INVOICE,
            VoucherDocumentKind::INVOICE,
            VoucherDocumentKind::FINAL_INVOICE,
        ], true);

        return $isInvoice
            && VoucherRepository::normalizeVoucherType((string) ($voucher['voucher_type'] ?? '')) === 'income'
            && empty($voucher['is_draft']);
    }

    /**
     * @param array<string, mixed> $voucher
     */
    public static function filename(array $voucher): string
    {
        $num = trim((string) ($voucher['invoice_number'] ?? ''));
        $base = $num !== '' ? preg_replace('/[^A-Za-z0-9._-]+/', '_', $num) : ('RE-' . (int) ($voucher['id'] ?? 0));

        return 'XRechnung_' . $base . '.xml';
    }

    /**
     * @param array<string, mixed> $voucher
     * @throws InvalidArgumentException
     */
    public static function renderXml(array $voucher): string
    {
        if (!self::supports($voucher)) {
            throw new InvalidArgumentException('E-Rechnung nur für Ausgangsrechnungen.');
        }
        $company = CompanySettings::config();
        $sellerName = CompanySettings::displayName();
        if ($sellerName === '') {
            $sellerName = 'Absender';
        }
        $sellerVat = trim((string) ($company['vat_id'] ?? ''));
        $sellerTax = trim((string) ($company['tax_number'] ?? ''));
        $sellerStreet = trim((string) ($company['street'] ?? ''));
        $sellerZip = trim((string) ($company['postal'] ?? ''));
        $sellerCity = trim((string) ($company['city'] ?? ''));
        $sellerCountry = strtoupper(trim((string) ($company['country'] ?? 'DE'))) ?: 'DE';
        if (strlen($sellerCountry) > 2) {
            $sellerCountry = 'DE';
        }

        $contactId = (int) ($voucher['contact_id'] ?? 0);
        $buyer = $contactId > 0 ? ContactRepository::findById($contactId) : null;
        $buyerName = $buyer !== null
            ? ($buyer->companyName !== '' ? $buyer->companyName : $buyer->listLabel())
            : trim((string) ($voucher['supplier_name'] ?? 'Kunde'));
        $buyerVat = $buyer !== null ? trim($buyer->vatId) : '';
        $buyerStreet = $buyer !== null ? trim($buyer->address1Street) : '';
        $buyerZip = $buyer !== null ? trim($buyer->address1Postal) : '';
        $buyerCity = $buyer !== null ? trim($buyer->address1City) : '';
        $buyerCountry = $buyer !== null ? strtoupper(trim($buyer->address1Country)) : 'DE';
        if ($buyerCountry === '' || strlen($buyerCountry) > 2) {
            $buyerCountry = 'DE';
        }

        $invoiceId = trim((string) ($voucher['invoice_number'] ?? ''));
        if ($invoiceId === '') {
            $invoiceId = 'RE-' . (int) ($voucher['id'] ?? 0);
        }
        $issueDate = substr((string) ($voucher['voucher_date'] ?? date('Y-m-d')), 0, 10);
        $dueDate = trim((string) ($voucher['payment_due_date'] ?? ''));
        if ($dueDate === '') {
            $dueDate = $issueDate;
        } else {
            $dueDate = substr($dueDate, 0, 10);
        }

        $currency = 'EUR';
        $gross = (float) ($voucher['gross_amount'] ?? 0);
        $net = (float) ($voucher['net_amount'] ?? 0);
        $tax = (float) ($voucher['tax_amount'] ?? 0);
        $defaultRate = (float) ($voucher['tax_rate'] ?? 19);

        $items = VoucherRepository::itemsForVoucher((int) ($voucher['id'] ?? 0));
        $invoiceLines = '';
        $lineNo = 0;
        if ($items !== []) {
            foreach ($items as $item) {
                $lineNo++;
                $qty = (float) str_replace(',', '.', (string) ($item['quantity'] ?? '1'));
                if ($qty <= 0) {
                    $qty = 1.0;
                }
                $lineGross = (float) str_replace(',', '.', (string) ($item['gross_amount'] ?? '0'));
                $rate = (float) ($item['tax_rate'] ?? $defaultRate);
                $lineNet = $rate > 0 ? round($lineGross / (1 + $rate / 100), 2) : $lineGross;
                $unitGross = (float) str_replace(',', '.', (string) ($item['unit_price_gross'] ?? '0'));
                $unitNet = $rate > 0 ? round($unitGross / (1 + $rate / 100), 2) : $unitGross;
                $title = trim((string) ($item['title'] ?? 'Position'));
                $unit = trim((string) ($item['unit'] ?? 'Stück'));
                $invoiceLines .= self::invoiceLineXml($lineNo, $title, $qty, $unit, $unitNet, $lineNet, $rate, $currency);
            }
        } else {
            $lineNo = 1;
            $invoiceLines = self::invoiceLineXml(1, 'Leistung gemäß Beleg', 1.0, 'Stück', $net, $net, $defaultRate, $currency);
        }

        $taxCategory = $defaultRate > 0 ? 'S' : 'Z';
        $customizationId = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"'
            . ' xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"'
            . ' xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            . '<cbc:CustomizationID>' . self::e($customizationId) . '</cbc:CustomizationID>'
            . '<cbc:ProfileID>urn:fdc:peppol.eu:2017:poacc:billing:01:1.0</cbc:ProfileID>'
            . '<cbc:ID>' . self::e($invoiceId) . '</cbc:ID>'
            . '<cbc:IssueDate>' . self::e($issueDate) . '</cbc:IssueDate>'
            . '<cbc:DueDate>' . self::e($dueDate) . '</cbc:DueDate>'
            . '<cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>'
            . '<cbc:DocumentCurrencyCode>' . self::e($currency) . '</cbc:DocumentCurrencyCode>'
            . '<cac:AccountingSupplierParty><cac:Party>'
            . self::partyNameXml($sellerName)
            . ($sellerVat !== '' ? '<cac:PartyTaxScheme><cbc:CompanyID>' . self::e($sellerVat) . '</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>' : '')
            . ($sellerTax !== '' ? '<cac:PartyLegalEntity><cbc:RegistrationName>' . self::e($sellerName) . '</cbc:RegistrationName><cbc:CompanyID>' . self::e($sellerTax) . '</cbc:CompanyID></cac:PartyLegalEntity>' : '<cac:PartyLegalEntity><cbc:RegistrationName>' . self::e($sellerName) . '</cbc:RegistrationName></cac:PartyLegalEntity>')
            . self::postalXml($sellerStreet, $sellerZip, $sellerCity, $sellerCountry)
            . '</cac:Party></cac:AccountingSupplierParty>'
            . '<cac:AccountingCustomerParty><cac:Party>'
            . self::partyNameXml($buyerName)
            . ($buyerVat !== '' ? '<cac:PartyTaxScheme><cbc:CompanyID>' . self::e($buyerVat) . '</cbc:CompanyID><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:PartyTaxScheme>' : '')
            . '<cac:PartyLegalEntity><cbc:RegistrationName>' . self::e($buyerName) . '</cbc:RegistrationName></cac:PartyLegalEntity>'
            . self::postalXml($buyerStreet, $buyerZip, $buyerCity, $buyerCountry)
            . '</cac:Party></cac:AccountingCustomerParty>'
            . '<cac:TaxTotal><cbc:TaxAmount currencyID="' . self::e($currency) . '">' . self::amt($tax) . '</cbc:TaxAmount>'
            . '<cac:TaxSubtotal><cbc:TaxableAmount currencyID="' . self::e($currency) . '">' . self::amt($net) . '</cbc:TaxableAmount>'
            . '<cbc:TaxAmount currencyID="' . self::e($currency) . '">' . self::amt($tax) . '</cbc:TaxAmount>'
            . '<cac:TaxCategory><cbc:ID>' . self::e($taxCategory) . '</cbc:ID><cbc:Percent>' . self::amt($defaultRate) . '</cbc:Percent>'
            . '<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal></cac:TaxTotal>'
            . '<cac:LegalMonetaryTotal>'
            . '<cbc:LineExtensionAmount currencyID="' . self::e($currency) . '">' . self::amt($net) . '</cbc:LineExtensionAmount>'
            . '<cbc:TaxExclusiveAmount currencyID="' . self::e($currency) . '">' . self::amt($net) . '</cbc:TaxExclusiveAmount>'
            . '<cbc:TaxInclusiveAmount currencyID="' . self::e($currency) . '">' . self::amt($gross) . '</cbc:TaxInclusiveAmount>'
            . '<cbc:PayableAmount currencyID="' . self::e($currency) . '">' . self::amt($gross) . '</cbc:PayableAmount>'
            . '</cac:LegalMonetaryTotal>'
            . $invoiceLines
            . '</Invoice>';

        return $xml;
    }

    private static function invoiceLineXml(
        int $lineNo,
        string $title,
        float $qty,
        string $unit,
        float $unitNet,
        float $lineNet,
        float $rate,
        string $currency,
    ): string {
        $cat = $rate > 0 ? 'S' : 'Z';

        return '<cac:InvoiceLine>'
            . '<cbc:ID>' . $lineNo . '</cbc:ID>'
            . '<cbc:InvoicedQuantity unitCode="C62">' . self::amt($qty) . '</cbc:InvoicedQuantity>'
            . '<cbc:LineExtensionAmount currencyID="' . self::e($currency) . '">' . self::amt($lineNet) . '</cbc:LineExtensionAmount>'
            . '<cac:Item><cbc:Name>' . self::e($title) . '</cbc:Name>'
            . '<cac:ClassifiedTaxCategory><cbc:ID>' . self::e($cat) . '</cbc:ID><cbc:Percent>' . self::amt($rate) . '</cbc:Percent>'
            . '<cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory></cac:Item>'
            . '<cac:Price><cbc:PriceAmount currencyID="' . self::e($currency) . '">' . self::amt($unitNet) . '</cbc:PriceAmount></cac:Price>'
            . '</cac:InvoiceLine>';
    }

    private static function partyNameXml(string $name): string
    {
        return '<cac:PartyName><cbc:Name>' . self::e($name) . '</cbc:Name></cac:PartyName>';
    }

    private static function postalXml(string $street, string $zip, string $city, string $country): string
    {
        return '<cac:PostalAddress>'
            . ($street !== '' ? '<cbc:StreetName>' . self::e($street) . '</cbc:StreetName>' : '')
            . ($city !== '' ? '<cbc:CityName>' . self::e($city) . '</cbc:CityName>' : '')
            . ($zip !== '' ? '<cbc:PostalZone>' . self::e($zip) . '</cbc:PostalZone>' : '')
            . '<cac:Country><cbc:IdentificationCode>' . self::e($country) . '</cbc:IdentificationCode></cac:Country>'
            . '</cac:PostalAddress>';
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function amt(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
