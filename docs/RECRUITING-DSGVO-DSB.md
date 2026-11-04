# Recruiting — Fristen & DSGVO (DSB / Anwalt)

Stand: 2026-11-04 · Technik im CRM · **keine Rechtsberatung** — Freigabe durch Datenschutzbeauftragten bzw. Anwalt der jeweiligen Instanz.

Begleitend: [`RECRUITING-HR-PLAN.md`](RECRUITING-HR-PLAN.md) · Einstellungen CRM: **HR → Recruiting Datenschutz**

---

## 1. Was das CRM technisch macht (Ist)

| Thema | Verhalten |
|--------|-----------|
| Speicherort | Nur Instanz-CRM (`dg_applicants`, `dg_job_applications`, Dateien unter `storage/`) |
| Öffentliche Bewerbung | `/rekrut` · Pflicht-Häkchen + Hinweis Speicherdauer · Link Datenschutz |
| Xing / LinkedIn | Nur Anzeigen-Link auf `/rekrut/…` — **keine** Bewerberakte dort |
| Absage / Zurückgezogen | Feld `retention_until` (Standard: X Monate ab Statuswechsel) |
| Auto-Löschung | Täglich `RecruitingRetentionService::runIfDue` — Bewerbung + Dateien + Ereignisse; Bewerber nur ohne weitere Akte |
| Laufend / Eingestellt | **nicht** auto-gelöscht; bei Zusage → Mitarbeiterstamm (dann Personal-/10-Jahre-Logik) |
| E-Mails | Optional HR + Bewerber (separate Einstellung) |

Standardfrist im Code/Default: **6 Monate** nach Absage (konfigurierbar 1–24).

---

## 2. Fachliche Einordnung (zur Prüfung)

| Punkt | Übliche Praxis / Hinweis | Freigabe nötig |
|--------|---------------------------|----------------|
| Zweck | Durchführung Bewerbungsverfahren | ☐ |
| Rechtsgrundlage Formular | Häufig § 26 BDSG / Art. 6 Abs. 1 lit. b DSGVO; Formular holt zusätzlich Einwilligung ein (Art. 6 Abs. 1 lit. a) — Machtungleichgewicht beachten | ☐ Text/Rechtsgrundlage |
| Speicherdauer Absage | AGG: Klagefrist oft kurz (§ 15 Abs. 4 AGG, i. d. R. 2 Monate); viele Arbeitgeber behalten **3–6 Monate** zur Rechtsverteidigung | ☐ Monatezahl |
| Talentpool | Im CRM **nicht** vorgesehen (keine längere Speicherung „auf Vorrat“) | ☐ falls später gewünscht: eigene Einwilligung |
| Datenschutzerklärung | Generator-Absatz „Bewerbungen“ + ggf. manuell nachziehen nach Freigabe | ☐ Website-Text |
| AV / Hosting | Hosting = Auftragsverarbeitung (Art. 28) — getrennt von Recruiting-Logik | ☐ AVV aktuell? |
| Auskunft/Löschung vor Frist | Manuell im CRM (Bewerberakte löschen / Status) — kein Self-Service-Portal | ☐ Prozess OK? |

---

## 3. Checkliste Freigabe (pro Kunde / Instanz)

1. ☐ Monate nach Absage festgelegt und in **HR → Recruiting Datenschutz** eingetragen  
2. ☐ Öffentlicher Hinweis- und Einwilligungstext geprüft (Leer = CRM-Standard)  
3. ☐ Datenschutzerklärung der Website enthält Bewerbungsabschnitt (Generator neu erzeugen oder manuell)  
4. ☐ SMTP/E-Mail-Empfänger HR datenschutzkonform (wer liest Bewerbungen?)  
5. ☐ Kein Talentpool ohne gesonderte Einwilligung  
6. ☐ Haken „DSB/Anwalt-Freigabe“ im CRM setzen und Datum notieren  

Unterschrift / Freigabe (extern): ______________________ Datum: __________

---

## 4. Empfohlene Standardantwort (wenn DSB „übliche Frist?“ fragt)

> Nach Absage oder Verfahrensende löschen wir Bewerbungsdaten in der Regel nach **6 Monaten**, soweit keine längere Speicherung zur Abwehr von Ansprüchen (insb. AGG) oder aufgrund gesetzlicher Pflichten erforderlich ist. Laufende Verfahren und Einstellungen bleiben davon unberührt.

Abweichung nur nach dokumentierter Freigabe — dann Monate im CRM ändern (Hinweistext folgt automatisch, sofern kein eigener Text hinterlegt ist).
