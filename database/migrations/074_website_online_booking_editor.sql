-- Terminkalender & Online-Buchungsseiten: bearbeitbares CMS-Layout mit Buchungsblock + optional Akademie-Video
UPDATE dg_website_pages
SET layout_json = '{"page_kind":"online_booking","rows":[{"id":"row-help-video","columns":[{"id":"col-help-video","width":12,"blocks":[{"id":"blk-help-heading","type":"heading","text":"So buchen Sie online","level":"h2"},{"id":"blk-help-text","type":"text","text":"Kurzanleitung für Ihre Kunden — optional bearbeiten oder entfernen."},{"id":"blk-help-video","type":"video","url":"/media/training/terminkalender/terminkalender-online-buchung.mp4","caption":"Online-Terminbuchung — Anleitung für Kunden"}]}]},{"id":"row-online-booking","columns":[{"id":"col-online-booking","width":12,"blocks":[{"id":"blk-online-booking","type":"online_booking"}]}]}]}'
WHERE slug = 'terminkalender'
  AND (
    layout_json LIKE '%"page_kind":"online_booking"%'
    AND (
      layout_json LIKE '%"rows":[]%'
      OR layout_json NOT LIKE '%"type":"online_booking"%'
    )
  );
