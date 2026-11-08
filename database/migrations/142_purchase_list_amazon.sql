-- Amazon Business Stufe 2: Bestellreferenz an Einkaufslisten-Einträgen (GoBD-Nachvollziehbarkeit)
SET NAMES utf8mb4;

ALTER TABLE dg_purchase_list_items
    ADD COLUMN external_order_id VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'Amazon externalId / Order-Ref' AFTER note,
    ADD COLUMN external_order_channel VARCHAR(40) NOT NULL DEFAULT '' COMMENT 'z. B. amazon_business' AFTER external_order_id,
    ADD COLUMN external_order_payload TEXT NULL COMMENT 'gekürzte API-Antwort, ohne Secrets' AFTER external_order_channel,
    ADD COLUMN external_ordered_at DATETIME NULL DEFAULT NULL AFTER external_order_payload,
    ADD KEY idx_dg_pli_external_order (external_order_channel, external_order_id);
