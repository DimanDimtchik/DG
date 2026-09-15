-- Terminkalender-Website-Seite: Marketing-Landingpage → Online-Terminbuchung
UPDATE dg_website_pages
SET layout_json = '{"page_kind":"online_booking","rows":[]}'
WHERE slug = 'terminkalender'
  AND (layout_json IS NULL OR layout_json NOT LIKE '%online_booking%');
