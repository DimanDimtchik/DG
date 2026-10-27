-- System-Artikel „Mehrzweck-Gutschein“ (Nachzug auch über GiftVoucherService::ensureDefaultCatalogArticle)
SET NAMES utf8mb4;

INSERT INTO dg_calendar_articles (
    article_number, catalog_kind, gtin, title, description, note,
    unit, tax_type, price_gross, work_minutes, area_id, sort_order,
    is_active, price_list_online, track_stock, stock_qty, min_stock
)
SELECT
    'GUT-MEHRZWECK',
    'product',
    '',
    'Mehrzweck-Gutschein',
    'Geschenkgutschein / Mehrzweckgutschein (variabler Nennwert). Gültigkeit nach §§ 195, 199 BGB (drei Jahre ab Schluss des Ausstellungsjahres). Mehrzweckgutschein: Beim Verkauf fällt keine Umsatzsteuer an; die USt entsteht erst bei Einlösung (§ 3 Abs. 13–15 UStG). Es besteht kein gesetzlicher Anspruch auf Barauszahlung des Gutscheinwerts, es sei denn, die geschuldete Leistung kann dauerhaft nicht mehr erbracht werden (z. B. Geschäftsaufgabe ohne Insolvenz).',
    'Systemartikel Gutschein — Nennwert beim Verkauf setzen.',
    'Stück',
    'ust0',
    0.00,
    0,
    0,
    5,
    1,
    1,
    0,
    0,
    0
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM dg_calendar_articles WHERE article_number = 'GUT-MEHRZWECK' LIMIT 1
);
