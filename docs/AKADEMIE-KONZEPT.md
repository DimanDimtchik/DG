# DG CRM — Akademie (Lernplattform)

Stand: **2026-09-14** · Status: **Konzept (noch nicht implementiert)**

> Regeln & Deploy: [`AGENTS.md`](../AGENTS.md)

---

## 1. Ziel

Die **Akademie** ist die integrierte Lernplattform im CRM für Erklärvideos, Pflichtschulungen vor Arbeitsbeginn und optional Zertifikate — mit **prüfbarem Fortschritt** (wer, wann, wie lange) und **fairen Anti-Betrugs-Regeln**.

Erste Inhalte (Beispiel): Lager → Platz-Check (Scan / Manuell) mit Beschreibung und Untertiteln, Stimme optional später.

---

## 2. Begriffe

| Begriff | Bedeutung |
|---------|-----------|
| **Tarif-Klasse** | Starter · Business · Premium — aus Lizenzserver (`plan`) |
| **Bereich** | Buchhaltung · Lager · Verkauf · Admin · … (an CRM-Module angelehnt) |
| **Kurs** | Sammlung von Modulen (Videos) + optional Quiz |
| **Modul** | Ein Video/Clip mit Regeln (Mindestzeit, max. 2× Geschwindigkeit) |
| **Lernpfad** | Zuweisung Kurs ↔ Rolle/Abteilung/Modul + **Zugriffsmodus** |
| **Zugriffsmodus** | Siehe Abschnitt 6 |
| **Zertifikat** | PDF nach **HR-Freigabe** |

---

## 3. Tarif-Erkennung (Lizenz)

- Lizenzschlüssel: `config/license.php` → Check gegen `dg-user.ganz-soft.de`
- Antwortfeld **`plan`** → in `storage/license_state.json` (`LicenseGuard`)
- Akademie zeigt nur Kurse mit `min_tier ≤ plan` (starter < business < premium)
- Plan wird **serverseitig** dem Key zugeordnet (nicht aus Key-String parsen)

---

## 4. Regeln vor Schulungsstart

Vor dem ersten Modul: Pflicht-Screen **„Schulungsregeln“** mit Checkbox.

Inhalt u. a.:

- Was protokolliert wird (Zeit, Geschwindigkeit, Sitzungen)
- **Max. 2× Wiedergabegeschwindigkeit** — schneller zählt nicht
- Realistische Gesamtzeit (3-h-Kurs in ¼ der Zeit → auffällig)
- Tab/Fenster im Blick; Heartbeat nur bei aktivem Ansehen
- Zertifikat nur nach HR-Prüfung
- DSGVO-Hinweis

Speichern: `user_id`, Zeitstempel, Kurs-Version, IP optional.

---

## 5. Video-Quellen & Darstellung

| Quelle | Phase | Hinweis |
|--------|-------|---------|
| Eigene MP4 (`storage/media/training/`) | MVP | Volle Kontrolle |
| YouTube / Vimeo / … | später | Pro Modul einstellbar |
| **YouTube-Sichtbarkeit** | pro Modul | intern-only vs. veröffentlichen/unlisted |

**Phase 1:** Beschreibungstext + **Untertitel (SRT/VTT)**; Feld „Audio geplant“ für später.  
Kein Voiceover/TTS in Phase 1.

---

## 6. Zugriffsmodi (Sperre — nicht immer gleich)

Pro **Kurs / Lernpfad / Zuweisung** einstellbar — **kein fester Standard** im System (z. B. Lager-Einarbeitung nicht vordefiniert als hart oder Vergleich).

**Wer entscheidet:** **Admin bzw. Chef** (Geschäftsführung) in der Akademie-Admin-UI — pro Kurs beim Anlegen oder bei Zuweisung an Mitarbeiter/Abteilung. HR kann zuweisen und prüfen; **Zugriffsmodus** setzt Admin/Chef (CRM-Rolle `admin`, später ggf. eigene Berechtigung „Akademie-Verwaltung“).

| Modus | Code | Verhalten |
|-------|------|-----------|
| **Vergleich** | `compare` | Software **bleibt nutzbar**; Banner in Akademie + betroffenen Modulen: „Bitte Schulung parallel absolvieren.“ Mitarbeiter kann **Lerninhalt mit der echten Oberfläche vergleichen** (Video offen, CRM offen). Kein harter Block. |
| **Weich** | `soft` | Module sichtbar; deutlicher Hinweis + Link zur Akademie; Frist optional. |
| **Hart** | `hard` | Betroffene Module **gesperrt** bis Kurs abgeschlossen **und** ggf. HR-Zertifikat. Redirect zur Akademie. |

**Beispiele (nur zur Orientierung — Entscheidung liegt bei Admin/Chef):**

- Lager-Einarbeitung: Chef wählt je nach Vertrauen/Reife **`hard`** (Sperre bis Zertifikat) oder **`compare`** (parallel am echten Lager üben)
- Update-Schulung nach Release: oft **`soft`**
- Oberflächen-Tour: oft **`compare`**

Technik: `TrainingGateService` liest `access_mode` aus Kurs/Zuweisung; `DepartmentAccess` / `MenuRegistry` blockieren nur bei `hard`.

---

## 7. Fortschritt, Integrität, Auffälligkeiten

### Pro Modul protokollieren

- `user_id`, `module_id`, `session_id`
- `started_at`, `ended_at`, `watched_seconds`
- `playback_rate` (Events bei > 2.0)
- `tab_visible` / Heartbeat alle ~30 s bei „playing“

### Regeln

- Max. **2×** Geschwindigkeit
- Mindest-Wandzeit: `Σ(Video-Dauer) / 2` (+ Puffer) für Gesamtkurs
- Mindest-Anteil pro Video (z. B. 90 % effektive Abdeckung)

### HR-Dashboard

| Status | Darstellung |
|--------|-------------|
| Unauffällig | neutral / grün |
| Grenzwert | gelb |
| Zu schnell, zu wenig Heartbeats, Rate-Verstöße, unrealistische Gesamtzeit | **rot** |

---

## 8. Zertifikat

### Felder (PDF)

- Nummer (eindeutig, z. B. `AKD-2026-000042`)
- Name Teilnehmer
- Kurs + **Version**
- **Ausstellungsdatum** (= HR-Freigabe)
- **Gültig bis**
- **Geltungsbereich:**
  - **`company`** — nur diese Firma / Instanz
  - **`platform`** — DG-Standard, anerkannt über CRM-Kunden mit gleichem Kurs/Version

### Ablauf

```
Kurs erfüllt (technisch) → Status „zur HR-Prüfung“
→ HR sieht Report (rote/gelbe Markierungen)
→ Freigabe oder Ablehnung
→ E-Mail an Mitarbeiter (siehe Abschnitt 9)
→ Bei Freigabe: PDF + Dossier-Eintrag
```

**Kein automatisches Zertifikat** ohne HR.

Quiz: pro Kurs/Bereich **manuell** aktivierbar (später).

---

## 9. HR-Freigabe / Ablehnung + E-Mail

Anbindung an bestehendes `MailService` + Vorlagen (Einstellungen → Benachrichtigungen, neues Template).

### Bei Freigabe (an Mitarbeiter)

- Betreff z. B. „Schulung abgeschlossen — Zertifikat freigegeben“
- Kursname, Gültigkeit, Geltungsbereich
- Link zum PDF in der Akademie / Mitarbeiter-Dossier
- Optional: Hinweis auf freigeschaltete Module (bei `hard`-Pfad)

### Bei Ablehnung (an Mitarbeiter)

- Betreff z. B. „Schulung — Nachbesserung erforderlich“
- **Keine** sensiblen Interna; kurze Begründung + was zu tun ist (Module wiederholen, HR kontaktieren)
- Link zurück zur Akademie

### Optional (an HR)

- Info bei Antrag „zur Prüfung“ (neuer Abschluss mit roten Flags)

Vorlagen editierbar; Versand nur wenn Mail konfiguriert (`MailSettings::isConfigured()`).

---

## 10. Datenmodell (Überblick)

Migration geplant: `070_training_academy.sql`

- `dg_academy_areas`, `dg_academy_tiers`
- `dg_academy_courses`, `dg_academy_modules`
- `dg_academy_assignments` (user, course, access_mode: compare|soft|hard, due_at, status)
- `dg_academy_module_progress`, `dg_academy_watch_sessions`, `dg_academy_watch_events` (append-only)
- `dg_academy_rules_acceptance`
- `dg_academy_certificates` (pending | approved | rejected, HR user, timestamps)
- `dg_academy_gates` (module_key → required_course_id)

---

## 11. UI (CRM)

| Rolle | Bereich |
|-------|---------|
| Alle | **Akademie** — Meine Schulungen, Katalog, Player, Untertitel |
| HR | Zuweisungen, Prüf-Queue, Zertifikate freigeben/ablehnen, Auffälligkeiten |
| **Admin / Chef** | Kurse anlegen, **Zugriffsmodus** (compare/soft/hard), Gates, Inhalte |
| Einstellungen | Mail-Vorlagen, Bereiche, Tarif-Mapping |

Menü: neuer Eintrag **Akademie** (`MenuRegistry`).

---

## 12. Phasen

| Phase | Inhalt |
|-------|--------|
| **0** | Dieses Konzept ✓ |
| **1** | Katalog, eigene MP4, Untertitel, Regeln-Screen, Basis-Logging |
| **2** | Lernpfade, Zugriffsmodi (compare/soft/hard), Gates |
| **3** | Integrität (Heartbeat, 2×, Mindestzeit, HR-Dashboard rot/gelb) |
| **4** | Zertifikat-PDF, HR-Queue, **E-Mail Freigabe/Ablehnung** |
| **5** | YouTube/externe Provider, Quiz pro Kurs optional |
| **6** | Analytics, plattformweite Zertifikate |

---

## 13. Festlegungen (Product)

| Thema | Entscheidung |
|-------|--------------|
| Name | **Akademie** |
| Tarif | Lizenzserver **`plan`** |
| Zertifikat | HR-Freigabe, Gültigkeit + Geltungsbereich |
| Vor Start | Regeln-Screen |
| Software-Sperre | **Optional pro Pfad** — compare / soft / hard; **Admin/Chef wählt**, kein System-Default |
| Vergleichsmodus | Lernen **parallel** am echten CRM |
| Quiz | Pro Kurs manuell |
| YouTube | Pro Video einstellbar |
| Ton | Erst Untertitel + Text; Stimme optional später |
| HR | Freigabe/Ablehnung + **E-Mail an User** |

---

## 14. Referenzen

- Lizenz: `src/Security/LicenseGuard.php` (`plan`)
- Mail: `src/Mail/MailService.php`
- Module: `src/Menu/MenuRegistry.php`, `src/Department/DepartmentAccess.php`
- Lager-Beispielclips: `docs/LAGER-WIRTSCHAFT.md` (Platz-Check)
