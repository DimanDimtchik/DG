# Szenario: CRM neu installieren (Assistent)

> **Produktionsregeln:** [`VIDEO-REGELN.md`](VIDEO-REGELN.md)  
> **Live-Log (diese Installation):** [`../INSTALL-LOG-GANZ-OM-DEV.md`](../INSTALL-LOG-GANZ-OM-DEV.md)  
> **Später:** Locale `docs/akademie/locales/de/crm-installation.json` · Stimme `de-DE-KatjaNeural`

**Zielgruppe:** Neue Kunden / Partner, die das CRM auf einer eigenen Domain einrichten  
**Dauer-Ziel:** ca. 6–8 Minuten (kurze Clips möglich: Vorbereitung / Assistent 1–3 / Assistent 4–6 / erster Login)  
**Stil:** Einfache Sprache, echte Assistenten-Screenshots, keine Passwörter zeigen  
**Demo-Instanz für Aufnahmen:** nach Freigabe ideal Dev `ganz-om.de` oder frische Test-Domain (keine echten Secrets)

---

## Ablauffolie (für Video)

1. **Vorbereitung** — Domain, Hosting (All-Inkl), Postfach, Zugangsdaten bereitlegen  
2. **Installer öffnen** — `https://ihre-domain.de/install.php`  
3. **Schritt 1 DB** — KAS + Datenbank + Absender-Mail  
4. **Schritt 2 Domain** — Domain bestätigen  
5. **Schritt 3 Firma** — Firmendaten eingeben  
6. **Schritt 4 Recht** — Pflichtangaben  
7. **Schritt 5 Import** — optional überspringen oder Beispieldaten  
8. **Schritt 6 Benutzer** — Admin anlegen, Installation abschließen  
9. **Erster Login** — Dashboard zeigen (Orientierung laut Video-Regeln)

---

## Roh-Sprechtext (wird nach Live-Installation geschärft)

### Intro
> Willkommen. In diesem Video richten wir das CRM auf einer eigenen Internetadresse ein.  
> Sie brauchen Ihre Hosting-Zugangsdaten, eine E-Mail-Adresse für den Versand und die Firmendaten.

### Vorbereitung
> Legen Sie bereit: Login zum Hosting, Passwort, ein Postfach für Absender-Mails und — falls schon vorhanden — die Datenbank.  
> Öffnen Sie dann im Browser die Adresse Ihrer Domain mit `/install.php`.

### Schritte 1–6
> _(Texte nach Live-Durchlauf an echte Feldbeschriftungen anpassen — siehe Install-Log.)_

### Abschluss
> Nach der Installation melden Sie sich an. Sie landen auf dem Dashboard — Ihrem Startpunkt im CRM.  
> Von hier aus erreichen Sie Kontakte, Buchhaltung und alle weiteren Module.

---

## Aufnahme-Checkliste

- [ ] Störfaktoren weg (echte Passwörter nicht tippen / verpixeln)  
- [ ] Systemprüfung grün zeigen  
- [ ] Alle 6 Schritte kurz, ohne Seitensprünge  
- [ ] Nach Login: volles Dashboard als Abschlussbild  
- [ ] Locale-JSON + VTT erzeugen  

---

## Status

| Teil | Status |
|------|--------|
| Szenario-Gerüst | ✅ angelegt 2026-11-01 |
| Live-Durchlauf dokumentiert | ⏳ läuft parallel in Install-Log |
| Finale Kunden-Anleitung | ⏳ nach erfolgreicher Installation |
| Locale / Video | ⏳ später |
