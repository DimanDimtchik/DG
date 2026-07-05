-- Einkauf-Recht in allow_article_catalog zusammenführen (eine Checkbox in der UI)

UPDATE dg_departments
SET allow_article_catalog = 1
WHERE is_purchasing = 1 AND allow_article_catalog = 0;

UPDATE dg_departments SET is_purchasing = 0 WHERE is_purchasing = 1;
