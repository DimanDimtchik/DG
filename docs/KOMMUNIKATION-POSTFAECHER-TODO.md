# Kommunikation — E-Mail-Postfächer (eigene Implementierung)

Stand: **2026-10-23**

**Entscheidung:** Keine Fremdsoftware (kein Roundcube, SnappyMail, AfterLogic o. Ä.). Nur eigener CRM-Code auf dem bestehenden IMAP/SMTP-Stack.

**Späterer Menü-Rahmen (Kommunikation):**

1. E-Mail-Postfächer ← dieser Plan  
2. Telefon (Telefonanlage — eigener Plan)  
3. Dokumentenverfasser (PDF/Word/Excel — eigener Plan)

---

## Bereits vorhanden (nutzen, nicht neu erfinden)

| Baustein | Ort |
|----------|-----|
| Mailbox-Stammdaten | `MailboxRepository`, Einstellungen / Provisioning |
| IMAP lesen | `ImapMailboxClient`, `MailFolderCatalog`, `MailImapSync` |
| SMTP senden | `MailService`, `SmtpClient`, Einstellungen → E-Mail |
| Archiv / Log | `MailLogRepository`, `storage/mail/` |
| Inbound-Webhook | `MailInboundApi` (u. a. AB aus Angebotsantwort) |
| Bestehende Post-Seite | `/app?page=post` (Compose/Sync-Ansätze) — in P2/P3 ausbauen oder in neue Kommunikations-Routen überführen |

---

## ToDo — Postfächer (P1–P6)

### P1 — Menü „Kommunikation“

- [x] Sidebar-Sektion **Kommunikation** (analog Website/Buchhaltung)
- [x] Eintrag **E-Mail-Postfächer** → bestehende `/app?page=post` (Label angepasst; Liste/Detail = P2/P3)
- [x] Platzhalter-Einträge **Telefon** und **Dokumentenverfasser** (Hinweisseiten)
- [x] Rechte: Modul `post` bzw. Kontakte + canEdit für Platzhalter; Postfach-Detailrechte weiter über `MailboxRepository`

### P2 — Postfach-Liste

- [x] Seite: Liste der sichtbaren Postfächer (Adresse, Typ privat/shared, Status IMAP/SMTP) — `/app?page=post`
- [x] Klick → Postfach öffnen (`/app?page=post&mailbox=id`)
- [x] Kurzstatus soft (Zugangsdaten + ungelesen + letzte Aktivität aus Mail-Log; kein Live-IMAP-Probe)
- [x] Kein Embed fremder Webmailer; Admin-Link „Postfächer verwalten“

### P3 — Postfach-UI (Lesen)

- [x] Ordnerleiste (IMAP-Ordner, deutsche Labels via `MailFolderLabels`) — bestehend + nach P2 nur mit gewähltem Postfach
- [x] Nachrichtenliste (Betreff, Von/An, Datum, gelesen/ungelesen) + Sync-API
- [x] Nachricht öffnen: HTML sanitized (`MailHtmlSanitizer`), Text, Anhänge download, .eml
- [x] „Weitere Nachrichten laden“ (Offset/Limit in API + UI); IMAP-Timeouts unverändert knapp
- [x] MIME-Parser erweitert (`MailMimeReader::parse`), IMAP-Fetch mit Body HTML/Text

### P4 — Schreiben / Antworten (CRM-Compose)

- [x] Neue Nachricht + Antworten + Weiterleiten (`compose` / `reply` / `forward`, Zitat, Wg:/Re:)
- [x] Versand über vorhandenes SMTP der jeweiligen Mailbox (`PostMailComposer` → `MailService`)
- [x] Kopie in Gesendet (lokales Archiv/`__sent__` wie `MailService`; Redirect nach Gesendet)
- [x] Assist: `ContactChannelLink::preferCrmMail()` → `/app?page=post&compose=1&to=`, `mailto:` als Fallback-Link

### P5 — CRM-Fachverknüpfung

- [ ] Absender/Empfänger → Kontakt vorschlagen / verknüpfen
- [ ] Belegkette: eingehende/ausgehende Mails an Beleg hängen (Ausbau Inbound/Outbound)
- [ ] Suche über Postfach + Kontaktbezug
- [ ] GoBD-taugliche Nachvollziehbarkeit (wer hat wann gelesen/gesendet — Protokoll, keine stillen Löschungen ohne Spur)

### P6 — Härten & Betrieb

- [ ] Rechte-Matrix (Shared-Postfach, Vertretung)
- [ ] Anhänge: Größenlimit, MIME-Whitelist
- [ ] Fehlertexte verständlich (Zugangsdaten, SSL, Ordner)
- [ ] Smoke-Testliste (Master + ganz-soft / kontur)
- [ ] Deploy nur Master → `sync-crm-from-master.sh`

---

## Explizit nicht

- Roundcube / andere Webmail-Suites im CRM-Code oder als Pflicht-iframe  
- Eigenes SMTP-Relay betreiben (Hoster-SMTP der Mailbox nutzen)  
- Telefonanlage und Dokumentenverfasser in diesem Ticket (nur Menü-Platzhalter in P1)

---

## Reihenfolge

`P1 → P2 → P3 → P4 → P5 → P6`  
P1+P2 können in einem Deploy; P3 ist der größte Block.
