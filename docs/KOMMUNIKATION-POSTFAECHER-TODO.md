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

- [ ] Seite: Liste der sichtbaren Postfächer (Adresse, Typ privat/shared, Status IMAP/SMTP)
- [ ] Klick → Postfach öffnen (`/app?page=…&mailbox=id`)
- [ ] Kurzstatus: Verbindungsfehler / letzte Sync-Zeit (soft)
- [ ] Kein Embed fremder Webmailer; optional nur Link „Hoster-Webmail“ falls in Einstellungen hinterlegt

### P3 — Postfach-UI (Lesen)

- [ ] Ordnerleiste (IMAP-Ordner, deutsche Labels via `MailFolderLabels`)
- [ ] Nachrichtenliste (Betreff, Von/An, Datum, gelesen/ungelesen)
- [ ] Nachricht öffnen (Text/HTML sanitized, Anhänge download)
- [ ] Pagination / „weitere laden“; Timeouts robust (KAS)
- [ ] Bestehenden Sync/`MailLog` wo sinnvoll wiederverwenden, Lücken schließen

### P4 — Schreiben / Antworten (CRM-Compose)

- [ ] Neue Nachricht + Antworten + Weiterleiten
- [ ] Versand über vorhandenes SMTP der jeweiligen Mailbox
- [ ] Kopie in Gesendet (IMAP und/oder lokales Archiv wie heute)
- [ ] Assist Phase 2: `ContactChannelLink::preferCrmMail()` → CRM-Compose, `mailto:` als Fallback

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
