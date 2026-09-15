-- Terminkalender-Seite: Endkunden-Video statt Mitarbeiter-Schulung (Admin-Abschnitte)
UPDATE dg_website_pages
SET layout_json = REPLACE(
    layout_json,
    '/media/training/terminkalender/terminkalender-online-buchung.mp4',
    '/media/training/terminkalender/terminkalender-online-kunde.mp4'
)
WHERE slug = 'terminkalender'
  AND layout_json LIKE '%terminkalender-online-buchung.mp4%';
