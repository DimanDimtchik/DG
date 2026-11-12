# OfficeCRM E-Mail-Modul — Entwicklungsplan (Original)

> **Quelle:** `OfficeCRM_Emailmodul_Entwicklungsplan.xlsx` (alle vier Tabellenblätter).  
> **Stand der IDs:** ursprünglicher Plan — **106 Mini-Schritte**, keine neuen Funktionen, keine ID-Änderungen.  
> **Arbeitsweise:** Eine Chat-Session = eine Mini-ID (wie übrige Rest-Pläne).

Verwandt (bereits erledigt, Basis-Postfächer): [`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md) — P1–P6 ✅.  
Dieser Plan beschreibt die **darauffolgende** Ausbaustufe (Nachrichtendetails → Composer → CRM-Verknüpfung → Kihel → Tests).

---

## Empfohlene Reihenfolge

Die ursprüngliche Reihenfolge des Plans:

| Reihenfolge | Phase | Inhalt |
|-------------|-------|--------|
| 1 | A1 | Bestandsaufnahme |
| 2 | B1 | Nachrichtendetails |
| 3 | C1 | Antworten und Weiterleiten |
| 4 | D1 | Composer und Entwürfe |
| 5 | E1 | Anhänge und Mediathek |
| 6 | F1 | Drucken und Export |
| 7 | G1 | Markieren und Organisieren |
| 8 | H1 | Erweiterte Suche |
| 9 | I1 | CRM-Verknüpfungen |
| 10 | J1 | Hintergrundprozesse |
| 11 | K1 | Kihel-Integration |
| 12 | L1 | Tests und Abschluss |

---

## Mini-Schritte (106) — Platzhalter

Die **106 Mini-Schritte** aus den vier Excel-Tabellenblättern sind der verbindliche Originalstand.

**Noch nicht in dieses Repo übernommen:** Die Detailtabellen (IDs, Titel, Akzeptanzkriterien) fehlen hier — in der Chat-Übergabe kam nur die Reihenfolge A1–L1 an. **Keine IDs erfinden.** Sobald die Excel-Datei oder der kopierbare Text der vier Blätter nachgeliefert wird, hier 1:1 einfügen (A1…L1 unverändert).

Struktur je Phase (nach Nachlieferung ausfüllen):

| Phase | Mini-IDs | Status |
|-------|----------|--------|
| A1 Bestandsaufnahme | *(aus Excel)* | offen |
| B1 Nachrichtendetails | *(aus Excel)* | offen |
| C1 Antworten und Weiterleiten | *(aus Excel)* | offen |
| D1 Composer und Entwürfe | *(aus Excel)* | offen |
| E1 Anhänge und Mediathek | *(aus Excel)* | offen |
| F1 Drucken und Export | *(aus Excel)* | offen |
| G1 Markieren und Organisieren | *(aus Excel)* | offen |
| H1 Erweiterte Suche | *(aus Excel)* | offen |
| I1 CRM-Verknüpfungen | *(aus Excel)* | offen |
| J1 Hintergrundprozesse | *(aus Excel)* | offen |
| K1 Kihel-Integration | *(aus Excel)* | offen |
| L1 Tests und Abschluss | *(aus Excel)* | offen |

---

## Regeln (verbindlich)

1. **Reihenfolge:** A1 → B1 → … → L1 wie oben; innerhalb einer Phase die Mini-IDs aus dem Excel.
2. **IDs:** Original-IDs beibehalten; keine Umbenennung, keine „Verbesserungs“-IDs.
3. **Kein Sondercode pro Domain** — branchenneutrales Multi-Tenant-Produkt ([`AGENTS.md`](../AGENTS.md) §5).
4. **UI:** [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Guards vor Merge.
5. **Abgrenzung:** Roundcube / fremde Webmail-Suites bleiben ausgeschlossen (siehe Postfach-Plan).

---

## Nächster Schritt

Excel / kopierbaren Text der **vier Tabellenblätter** (106 Mini-Schritte) nachliefern → Abschnitt „Mini-Schritte“ 1:1 füllen → erste Session mit **A1** (bzw. erster Mini-ID unter A1) starten.
