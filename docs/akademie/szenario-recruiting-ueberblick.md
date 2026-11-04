# Szenario: Recruiting — Überblick (Stellen & Bewerber)

> **Regeln:** [`VIDEO-REGELN.md`](VIDEO-REGELN.md) · **Text:** [`locales/de/recruiting-ueberblick.json`](locales/de/recruiting-ueberblick.json)

**Zielgruppe:** HR / Personal mit Zugang zu Stellen und Bewerber  
**Dauer:** ca. 2–3 Minuten  
**Stil:** Navigation vom Dashboard, Listen und Formulare — ohne Xing-/LinkedIn-API-Details

## Ablauf

1. Dashboard — Einstieg  
2. Hinweis: Menü **HR** → Stellen / Bewerber (keine eigene Dashboard-Kachel)  
3. Stellen-Liste — neue Stelle, öffentliche Seite  
4. Stelle bearbeiten — Status Veröffentlicht, optional Anzeigen-Export  
5. Bewerber-Liste  
6. Bewerbung bearbeiten — Status, Unterlagen, Einstellung  
7. Abschluss — Bewerbungen laufen über die eigene Seite `/rekrut`

## Aufnahme

```bash
bash bin/academy-build-recruiting-video.sh
# optional lokal ohne SSH: SKIP_SSH=1 BASE_URL=https://ganz-om.de/
```

Demo-Daten im Export (keine echten Bewerber).
