# Kichel — Verstehen → Suchen → Formulieren (RAG zuerst, Training später)

> **Eine Chat-Session = eine Mini-ID.**  
> Architektur-Basis: [`KICHEL-PHASE2.md`](KICHEL-PHASE2.md) · Ollama/GEX: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md) · UI: [`SUPERKICHEL-UI-PLAN.md`](SUPERKICHEL-UI-PLAN.md)  
> **Implementierung jetzt:** nur **Phase KR (RAG)**. Phasen KT/KP = später, hier nur beschrieben.

Stand: **2026-11-27** · Dev: **`ganz-om.de`** · Branch-Empfehlung: `cursor/kichel-rag-…`

---

## 0. Zielbild & harte Regeln

| Schicht | Aufgabe | Wer |
|---------|---------|-----|
| **Verstehen** | Intent, Suchbegriffe, Norm-Hinweise aus der Frage | zuerst Regeln/PHP; optional später LLM-Parser |
| **Suchen (RAG)** | Treffer aus CRM-Wissen, Code/Nav, Steuerkurztexten, später Normen/Urteile | PHP + Index/DB |
| **Entscheiden / Rechnen** | Beträge, USt, Skonto, Buchungslogik | **nur PHP** |
| **Formulieren** | lesbarer Antworttext **nur aus gelieferten Fakten** | Qwen/Ollama (`phrase`) |
| **Nachweisen** | Quelle, Stand, Link — sichtbar in Antwort/Protokoll | CRM |

**Haftung / Prüfbarkeit (verbindlich):**

- Keine Antwort „aus dem Modellgedächtnis“ zu geltendem Recht ohne Treffer.
- Jede Rechts-/Steueraussage braucht **Quelle** (Norm/§, Urteil, BMF, CRM-Hilfetext) + möglichst **Stand**.
- Bei unsicherem Treffer: nachfragen oder „nicht gefunden“ — nicht raten.
- Endnutzer-UI: keine Plan-Codes (`KR3b` …).

**Was „einfach, später erweiterbar“ heißt:**

1. Zuerst RAG über **bestehendes CRM-Wissen** (ohne große Gesetzes-DB).
2. Dann Korpus erweitern (Gesetze XML → Index).
3. Dann optional Intent per Qwen.
4. **Erst wenn RAG stabil:** Fine-Tuning / ggf. Gesetzestraining — bewusst mit Qualitäts- und Haftungsrisiko.

---

## 1. Arbeitsweise (tokenarm)

| Regel | Bedeutung |
|-------|-----------|
| **1 ID = 1 Chat** | Nur die freigegebene Mini-ID |
| **Ist zuerst** | Bei `*a`-IDs oft nur lesen / Doc |
| **Kein Training in KR** | Keine LoRA, kein Gewicht-Update, kein Massen-Scraping ohne ID |
| **Kein Tenant im Kern** | Keine Kundenfirma/Personen in `src/` Defaults |
| **UI** | [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Guard `php bin/ui-form-spacing-selftest.php` |

**Chat-Prompt-Vorlage:**

```text
Nur Mini-ID KR2b laut docs/KICHEL-RAG-TRAINING-PLAN.md.
Untersuche zuerst den Ist-Zustand. Ändere nur nach ausdrücklicher Freigabe.
Kein Fine-Tuning, kein Gesetzes-Vollimport außerhalb dieser ID.
```

---

## 2. Phasen-Übersicht (vollständig)

| Phase | Kürzel | Inhalt | Status |
|-------|--------|--------|--------|
| **A — RAG (jetzt)** | **KR0–KR9** | Verdrahtung Suche → Fakten → Qwen; CRM-Korpus; Quellen; Smoke | **KR0–2 ✅** · als Nächstes **KR3** |
| **B — Rechtskorpus** | **KL0–KL5** | Gesetze/Urteile importieren & indexieren (nach stabilem RAG) | geplant |
| **C — Intent-LLM** | **KI0–KI3** | Qwen nur als Frage-Parser (JSON), Suche weiter PHP | geplant |
| **D — Produkt/Haftung** | **KH0–KH3** | Disclaimer, Protokoll-Felder, Admin-Qualität | geplant |
| **E — Fine-Tuning** | **KT0–KT5** | LoRA auf Q&A/Stil — **nur nach RAG-Stabil** | später, beschrieben |
| **F — Gesetzestraining** | **KP0–KP4** | Continued Pretraining / schweres Norm-Training | später, beschrieben |
| **G — Pflege** | **KU0–KU2** | Update-Zyklus Normen, Re-Index, Re-Train-Trigger | laufend nach B/E |

---

## 3. Phase A — RAG Microschritte (**KR** — implementieren zuerst)

> RAG = Retrieval-Augmented Generation: **Treffer holen**, dann Qwen **nur formulieren**.  
> Kein Modellgewicht ändern.

### 3.1 Parent-Tabelle KR

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| **KR0** | Plan/Abgrenzung (diese Datei) · Haken in TODOS | 🟢 | [x] 2026-11-27 |
| **KR1** | Ist-Inventar Kichel-Pipeline (nur lesen) | 🟢 | [x] 2026-11-27 · §3.2 |
| **KR2** | Fakten-Objekt + Prompt-Vertrag (`phrase` nur aus Fakten) | 🟡 | [x] 2026-11-27 · a–c ✅ |
| **KR3** | Retrieval-Orchestrierung (Reihenfolge Sucher) | 🟡 | [ ] · Kinder a–d |
| **KR4** | CRM-Korpus v1 (Knowledge/Nav/Felder → einheitliche Chunks) | 🟡 | [ ] · Kinder a–c |
| **KR5** | Ranking / „kein Treffer“ / Mehrfachtreffer | 🟡 | [ ] · Kinder a–b |
| **KR6** | Quellenzeile in Antwort + Protokoll | 🟡 | [ ] · Kinder a–b |
| **KR7** | Super-Kichel/Widget: gleiche Pipeline (kein Parallelweg) | 🟡 | [ ] |
| **KR8** | Selftests + Dev-Smoke | 🔴🟡 | [ ] · Kinder a–b |
| **KR9** | Docs-Haken / Abnahmekriterien „RAG stabil“ | 🟢 | [ ] |

**Empfohlene Reihenfolge:** KR0 ✅ → KR1 → KR2 → KR3 → KR4 → KR5 → KR6 → KR7 → KR8 → KR9.  
Danach erst **KL** (Gesetze) oder Feinschliff KR.

---

### 3.2 KR1 — Ist-Inventar (nur lesen) ✅ 2026-11-27

**Ziel:** Eine Seite „wie läuft eine Frage heute“. **Kein Code in KR1.**

#### Prüfliste (Soll vs. Ist)

| Prüfen | Erwartung | Ist (Repo, 2026-11-27) |
|--------|-----------|-------------------------|
| `KichelAssistant::answer` | Token → Intent → Legal/Ledger/Money → Nav/Knowledge/Fields/Media | **ja** — siehe Ablauf unten |
| `KichelOllamaClient` | nur `phrase()` mit Fakten | **ja**, aber Aufruf **nur** aus `wrapMoney` (Skonto) — **nicht** bei Topic/Nav/Hilfe |
| `kichel.local.php` (Dev) | HTTPS `llm.ganz-soft.de`, Modell z. B. `qwen2.5-coder:14b` | laut Ops/SKC1 Docs ✅ · Datei **nicht** in Git (`config/kichel.local.php.example`: enabled=false, Default-Modell `llama3.1:8b`) |
| Widget vs. `/superkihel` | beide → `/api/kichel` | **ja** — `window.dgKichel.apiUrl` · `KichelApi` → `KichelAssistant::answer` |
| Lücken | freie Formulierung / Norm / einheitliche Fakten | bestätigt — siehe „Lücken für RAG“ |

#### HTTP & UI

```text
POST /api/kichel  (index.php)
  → KichelApi::handle()  — Staff + CSRF
  → KichelAssistant::answer($user, $query)
  → KichelProtocolRepository::record(..., $result)  — query, answer, response_json
  → JSON { success, data: $result }

Widget:  assets/js/kichel.js      + views/partials/kichel-widget.php  (apiUrl /api/kichel)
Seite:   assets/js/superkihel.js  + views/modules/superkihel.php       (gleiche cfg.apiUrl)
```

Eine Backend-Pipeline — gut für späteres **KR7** (kein Parallelweg nötig, nur absichern).

#### Ablauf `KichelAssistant::answer` (Ist)

```text
1. trim / leer → emptyResponse
2. KichelKnowledge::tokenize
3. KichelIntent::detect  → navigate | explain | calculate | general
4. Early-Return (ohne Multi-Suche):
   a. KichelLegalPages::tryAnswer     → wrapLegal (page_links)
   b. KichelLedgerAccounts::tryAnswer → wrapSimple(ledger_account)
   c. KichelMoneyLogic::tryAnswer + Intent===calculate → wrapMoney
5. Parallel sammeln:
   - KichelFieldCatalog::tryAnswer
   - KichelNavIndex::search (Limit 8)
   - KichelKnowledge::matchTopics (Limit 8)
   - KichelMediaSearch::search (Limit 8)
6. collectCandidates: Score-Filter (Media≥4, Nav≥6, Topic≥4, Field score 5),
   relatedCandidates (Belege/Nummernkreise), Dedup, max 5, MULTI_MIN_SCORE=3
7. 0 Treffer → emptyResponse (Hinweis Stichworte)
   1 Treffer → wrapSingleCandidate (PHP-Textbausteine)
   n Treffer → wrapMultiCandidates (nummerierte Liste)
8. Money-Sonderfall: Money-Treffer ohne andere Candidates → wrapMoney
```

Antwortform heute: `presentation`, `kind`, `answer`, `follow_up`, optional `action_links` / `page_links` / `calculation`.

#### Ollama / Fakten (Ist)

| Punkt | Befund |
|-------|--------|
| Einstieg | `KichelOllamaClient::phrase($userQuery, $baseAnswer, $facts)` |
| Config | `config/kichel.local.php` · Keys: `ollama_enabled`, `ollama_url`, `ollama_model`, `timeout_seconds` |
| Endpoint | `{ollama_url}/api/generate` (nicht OpenAI-`/v1`) |
| Prompt | „Formuliere … STRENG: Beträge nicht ändern“ + JSON-Fakten + Basisantwort |
| Guard | `numbersConsistent` — bei verdächtigen Amount/Percent-Abweichungen → `null` → Fallback Basisantwort |
| **Wer ruft phrase?** | **nur** `wrapMoney` |
| Fakten-Form Money | flaches Assoc: `net_amount`, `skonto_percent`, `discount_amount`, `payable_amount`, `payment_days` — **kein** einheitliches RAG-`facts[]`-Schema |

Topic-/Nav-/Feld-Antworten sind **reine PHP-Strings** — Qwen sieht sie heute nicht.

#### Wissensquellen (Ist)

| Quelle | Datei / Klasse | In Assistant verdrahtet? |
|--------|----------------|---------------------------|
| Hilfe-Topics | `src/Kichel/data/knowledge.php` + `KichelKnowledge` | ja |
| Menü/Nav | `KichelNavIndex` (+ Aliase MenuRegistry/Settings) | ja |
| Feldhilfe | `KichelFieldCatalog` | ja |
| Media/Akademie | `KichelMediaSearch` | ja |
| Pflichtseiten | `KichelLegalPages` | ja (Early) |
| Konten | `KichelLedgerAccounts` | ja (Early) |
| Skonto-Rechnung | `KichelMoneyLogic` | ja |
| Code/Doku-Scan | `KichelCodeSearch` (`src`/`views`/`docs`) | **nein** — Klasse existiert, **kein** Aufruf |
| Schema-Katalog | `KichelSchemaCatalog` | **nein** — Klasse existiert, **kein** Aufruf |
| Gesetze / Urteile | — | **nein** |
| Steuer-Kurztexte mit Norm-Quelle | nur indirekt in Knowledge (CRM-Navigation, kaum §-Nachweis) | schwach |

PHASE2-Doc nennt Code/Schema als Schicht — **Implementierungsstand: tot / ungenutzt** (für KR3/KR4 relevant).

#### Verstehen (Ist)

`KichelIntent`: Regex/Stichworte (Skonto+Zahl → calculate; was/wie → explain; wo/finde → navigate). **Kein** LLM-Parser. Schwache freie Formulierungen ohne Keyword-Treffer landen oft bei `emptyResponse`.

#### Lücken für RAG (Priorität KR2+)

1. **Kein einheitliches Fakten-Objekt** für alle Antwortarten (KR2a).  
2. **`phrase` nur bei Skonto** — Hilfe/Nav/Steuertext ohne Qwen-Formulierung aus Treffern (KR2b/c + später Orchestrierung).  
3. **Keine zentrale Retriever-Stelle** — Logik in `answer` + `collectCandidates` vermischt (KR3).  
4. **CodeSearch/SchemaCatalog unverdrahtet** — „Code durchsuchen“ im Produktgefühl fehlt (KR3/KR4).  
5. **Keine Norm-/Urteilssuche** (KL nach KR9).  
6. **Quellenzeile** für Nutzer außer Action-Links / Money-`calculation` schwach (KR6).  
7. Protokoll speichert `response_json` — gut erweiterbar für RAG-Refs (KR6b).

#### Abnahme KR1

- [x] Ablauf dokumentiert  
- [x] Ollama-Anbindung und Aufrufstelle geklärt  
- [x] Widget = Super-Kichel API bestätigt  
- [x] Lücken für KR2+ benannt  
- [x] kein Code geändert  

---

### 3.3 KR2 — Fakten-Objekt + Prompt-Vertrag ✅ 2026-11-27

| Kind | Aufgabe | Abnahme | Status |
|------|---------|---------|--------|
| **KR2a** | Schema `facts[]` | `KichelRagFact` | [x] |
| **KR2b** | `phrase()`-Prompt + Selftest | Prompt in `KichelOllamaClient` · `bin/kichel-rag-fact-selftest.php` | [x] |
| **KR2c** | Fallback ohne Ollama | `KichelRagFact::fallbackAnswer` · `compose()` · Money-Pfad | [x] |

**Nicht in KR2:** neue Suchquellen, Gesetzesimport, Retriever (→ KR3).

#### Schema (verbindlich)

Jeder Fakt (Assoc):

| Key | Typ | Pflicht |
|-----|-----|---------|
| `type` | string | ja — z. B. `topic`, `navigation`, `field_help`, `media`, `legal_pages`, `ledger_account`, `calculation`, `statute`, `caselaw`, `other` |
| `title` | string | ja (oder body) |
| `body` | string | ja (oder title) |
| `source_label` | string | ja (Default „CRM“) |
| `source_ref` | string | ja (stabile ID, z. B. `topic:ust-id`) |
| `href` | string\|null | nein |
| `confidence` | float 0…1 | ja (Default 1) |
| `numbers` | map string→number | nein — für Betrags-Guards |

API: `KichelRagFact::make` / `normalize` / `normalizeList` / `fromMoneyCalculation` / `fallbackAnswer` / `collectNumbers`.

#### Prompt-Vertrag (KR2b)

`KichelOllamaClient::phrase`: nur JSON-Fakten + Basisantwort; keine neuen §§; Zahlen unverändert; bei Lücke „keine gesicherten Angaben“; Guard `numbersConsistent` über `numbers`.

`compose($query, $facts, $base?)`: phrase oder Fallback.

#### Anbindung Ist

`KichelAssistant::wrapMoney` → RAG-Fakten + `compose`; Response zusätzlich `rag_facts`. Topic/Nav noch ohne phrase (→ KR3).

**Selftest:** `php bin/kichel-rag-fact-selftest.php` → ALL OK.

---

### 3.4 KR3 — Retrieval-Orchestrierung

| Kind | Aufgabe | Abnahme |
|------|---------|---------|
| **KR3a** | Eine Stelle „Retriever“: sammelt Kandidaten → normalisiert auf KR2-Schema | eine Aufrufkette |
| **KR3b** | Reihenfolge verbindlich: Money/Legal-Pflichtpfade **vor** freier Suche; RAG nur wenn nötig | Doc + Code-Pfad |
| **KR3c** | Budget: max. N Chunks / max. Zeichen an Ollama (Timeout schützen) | Konstante/Config |
| **KR3d** | Feature-Flag z. B. `rag_enabled` in `kichel.local.php` (Default an auf Dev nach Smoke) | Flag wirkt |

**Nicht in KR3:** Embedding-DB, Vektorsuche (kann später als Drop-in hinter dieselbe Schnittstelle).

---

### 3.5 KR4 — CRM-Korpus v1 (ohne Gesetze)

| Kind | Aufgabe | Abnahme |
|------|---------|---------|
| **KR4a** | Bestehende `KichelKnowledge`-Topics → Chunks mit Quelle „CRM-Hilfe“ | Treffer als Fakten |
| **KR4b** | Nav-/Feld-/Media-Treffer → dasselbe Chunk-Format | einheitlich |
| **KR4c** | 5–15 **Steuer-Kurztexte** selbst gepflegt (Skonto, §19, Ist/Soll kurz, Belegkette) — nur eure Formulierungen + Verweis auf Norm **ohne** Verlagskommentar | Datei/Tabelle + Suche findet sie |

**Nicht in KR4:** GII-XML-Massenimport (→ **KL**).

---

### 3.6 KR5 — Ranking & Leerfall

| Kind | Aufgabe | Abnahme |
|------|---------|---------|
| **KR5a** | Score-Schwellen: unter Minimum → „nicht gefunden“ + Formulierhilfe | keine Halluzinations-Fakten |
| **KR5b** | Mehrere gute Treffer: Liste oder Top-K an `phrase`, nicht widersprüchlich mischen ohne Kennzeichnung | nachvollziehbar |

---

### 3.7 KR6 — Quellen & Protokoll

| Kind | Aufgabe | Abnahme |
|------|---------|---------|
| **KR6a** | Antwort enthält kurze Quellenzeile(n) (für Nutzer verständlich) | UI Widget + Super-Kichel |
| **KR6b** | `dg_kichel_log` (o. Ä.): Fakten-Refs / rag_used speichern (Migration nur wenn nötig) | Admin-Protokoll auswertbar |

---

### 3.8 KR7 — Ein Pipeline-Pfad

Widget und Super-Kichel nutzen **dieselbe** Assistant/RAG-Kette. Kein zweites Prompt-System nur für Super-Kichel.

---

### 3.9 KR8 — Tests

| Kind | Aufgabe | Abnahme |
|------|---------|---------|
| **KR8a** | Selftest CLI: feste Fragen → erwartete Quellen-IDs / kein Fantasie-§ | `bin/…-selftest.php` grün |
| **KR8b** | Manuell Dev: 3 gute / 2 absichtlich unklare Fragen; Ollama an/aus | Checkliste abgehakt |

---

### 3.10 KR9 — „RAG stabil“ (Gate vor Training)

RAG gilt als **stabil**, wenn:

1. KR2–KR8 erledigt und auf Dev nachgewiesen  
2. Unklare Fragen → kein erfundenes Recht  
3. Mindestens Stichproben: Skonto/Nav/Firma-Feld + 1 Steuer-Kurztext mit Quelle  
4. Protokoll zeigt, **welche** Fakten genutzt wurden  
5. Bewusste Entscheidung dokumentiert: „RAG-Gate offen für KL / später KT“

**KR9 = Doc-Haken + Freigabe-Satz — kein Training starten ohne KR9.**

---

## 4. Phase B — Rechtskorpus (**KL** — nach RAG, geplant)

> Eigenes Indexieren freier Normen/Urteile. Weiterhin **kein** Fine-Tuning nötig.

| ID | Inhalt (Beschreibung) | Wann |
|----|----------------------|------|
| **KL0** | Quellenentscheid: Primär [gesetze-im-internet.de](https://www.gesetze-im-internet.de/) (XML + `gii-toc.xml`); optional Bund-API [rechtsinformationen.bund.de](https://docs.rechtsinformationen.bund.de/); Urteile später `rii-toc.xml` / Open Legal Data | nach KR9 |
| **KL1** | Import-Pipeline: TOC → gewählte Gesetze (Start: **AO, UStG, EStG**, ggf. HGB-Kern) → Abschnitte als Chunks (§/Abs.) + Metadaten (Gesetz, §, Abrufdatum) | |
| **KL2** | Speicher: Tabelle oder Datei-Index **in Instanz/GEX** — suchbar (Volltext zuerst; Vektor optional später) | |
| **KL3** | Retriever-Anbindung: Norm-Chunks als weiterer `facts[]`-Typ `statute` | |
| **KL4** | Update-Job: periodisch TOC-Diff / geänderte Normen neu laden; alten Stand kennzeichnen | |
| **KL5** | Pilot Urteile: nur BFH-Stichprobe oder Themenfilter — **kein** Vollbestand am Tag 1 | |

**Rechtliches:** Normtexte/amtliche Entscheidungen typisch § 5 UrhG nutzbar; **keine** Beck-/Juris-Kommentare scrapen. Erläuterungen = weiter **eigene** Kurztexte (KR4c) oder lizenzierte Inhalte.

**API vs. DB:** Live-API nur als Ergänzung/Refresh; **Antwortgrundlage** = indexierter Stand bei euch (Prüfbarkeit).

---

## 5. Phase C — Intent mit Qwen (**KI** — optional nach KR/KL)

| ID | Inhalt |
|----|--------|
| **KI0** | Entscheidung: LLM-Parser ja/nein (Kosten, Latenz, Fehlerrate) |
| **KI1** | Qwen liefert **nur JSON** (Intent, Suchstrings, vermutete Normen) — kein Fließtext-Recht |
| **KI2** | Validierung: Schema-Check; bei Müll → Fallback Token-Suche (heutige Logik) |
| **KI3** | A/B: Parser an/aus · gleiche Selftests |

Parser **ersetzt nicht** die Suche. Er verbessert nur die Query.

---

## 6. Phase D — Haftung & Produkt (**KH**)

| ID | Inhalt |
|----|--------|
| **KH0** | Nutzungs-/Hinweistext: „Unterstützung, keine Rechtsberatung; Quelle prüfen“ (kurz, verständlich) |
| **KH1** | Admin: schlechte Antworten markieren / Knowledge nachziehen |
| **KH2** | Rollen: was Mitarbeiter vs. nur Admin sehen (z. B. Roh-Fakten) |
| **KH3** | Abgleich GoBD-Ton: keine Buchungsvorschläge „einfach so buchen“ ohne CRM-Regel |

---

## 7. Phase E — Fine-Tuning (**KT** — erst nach KR9, bewusst)

> **Voraussetzung:** RAG stabil (KR9) · Qualitäts-/Haftungsrisiko akzeptiert · idealerweise KL-Pilot gelaufen.

### Was Fine-Tuning hier soll — und was nicht

| Soll | Nicht soll |
|------|------------|
| Antwortstil, Länge, „immer Quellen nennen“ | alleinige Rechtswahrheit |
| bessere Nutzung der **übergebenen** Fakten | „alle Gesetze auswendig“ |
| weniger Prompt-Drift | Ersatz für Norm-Updates |

### Micro-Beschreibung (später zerlegbar)

| ID | Inhalt |
|----|--------|
| **KT0** | Gate-Check: KR9 + Entscheidung „wir akzeptieren FT-Risiken“ dokumentieren |
| **KT1** | Datensatz: nur **eigene** Q&A aus Protokoll/redigierten Antworten + Fakten-Snippets (keine Verlagstexte) |
| **KT2** | Format: Instruction-Tuning / Chat-Paare; Train/Val-Split; keine echten Mandantendaten |
| **KT3** | Technik: LoRA/QLoRA auf Basis `qwen2.5-…`; Training **nicht** auf All-Inkl-PHP, sondern GPU-Box/GEX-Job; Ergebnis → Ollama-Modelfile |
| **KT4** | Eval: gleiche Selftests wie KR8; zusätzlich „erfindet §?“-Negativtests — Regression gegen Basis-Qwen |
| **KT5** | Rollout: Dev `kichel.local.php` Modellname umschalten · Kill-Switch zurück auf Basis |

**Regel nach FT:** RAG bleibt **an**. Fine-Tune ohne Retrieval = für Steuerprodukt ungeeignet.

---

## 8. Phase F — Volles Gesetzestraining (**KP** — spät, hohes Risiko)

> Continued Pretraining / großes Norm-Korpus in die Gewichte. **Nur wenn** RAG+FT nicht reichen und Nutzen das Risiko rechtfertigt.

| ID | Inhalt |
|----|--------|
| **KP0** | Schriftliche Entscheidung: Zweck, Kosten, Update-Problem, Halluzinationsrisiko |
| **KP1** | Korpus: GII (+ ggf. RII) vollständig oder Steuer-Teilmenge; Chunking; Dedup; Stand-Metadaten **im Text** mitführen hilft nur bedingt |
| **KP2** | Continued Pretraining-Lauf; Checkpoint; Eval „kennt §-Nummern“ ≠ „wendet Recht korrekt an“ |
| **KP3** | Danach **zwingend** weiter RAG für Zitate; KP-Modell darf nicht ohne Treffer Recht behaupten |
| **KP4** | Re-Train-Trigger bei größeren Gesetzesänderungen (KU) |

**Warnung:** Modell „kennt“ veraltetes Recht weiter, bis neu trainiert. Für UStG/AO-Änderungen ist **Index-Update (KL4)** billiger und sicherer als KP.

---

## 9. Phase G — Pflege (**KU**)

| ID | Inhalt |
|----|--------|
| **KU0** | Checkliste: Norm-Update → Re-Index (KL) → Selftests → optional FT-Daten nachziehen |
| **KU1** | Monitoring: Rate „kein Treffer“, Negativ-Feedback Protokoll |
| **KU2** | Wenn KT/KP aktiv: Version Matrix (Basis-Modell / Adapter / Index-Stand) im Protokoll |

---

## 10. Abhängigkeiten (Reihenfolge)

```text
KR0 ✅
  → KR1 → KR2 → KR3 → KR4 → KR5 → KR6 → KR7 → KR8 → KR9 (RAG-Gate)
       ↓
      KL0–KL5 (Gesetze/Urteile indexieren)     parallel möglich: KH0 Hinweistext
       ↓
      KI0–KI3 (optional Parser)
       ↓
      KT0–KT5 (Fine-Tuning, nur nach bewusster Freigabe)
       ↓
      KP0–KP4 (volles Gesetzestraining, selten nötig)
       ↓
      KU laufend
```

**SKP (OpenHands)** bleibt getrennt: Programmieren ≠ Rechts-RAG.  
**SKC Ollama-Proxy** ist Voraussetzung für `phrase` auf Dev — bereits ✅.

---

## 11. Abgrenzung

| Thema | Hier? |
|-------|--------|
| Beträge/Skonto rechnen | nein — bleibt `KichelMoneyLogic` |
| OpenHands / Agent-Button | nein — [`SUPERKICHEL-SKP-PLAN.md`](SUPERKICHEL-SKP-PLAN.md) |
| Embedding/Vektor-DB | optional später hinter KR3-Schnittstelle; nicht Blocker für KR1–KR8 |
| Juris/Beck-Volltext | nein ohne Lizenz |
| Öffentlicher Chat ohne Login | nein |

---

## 12. Nächster konkreter Schritt

**KR3** (Retrieval-Orchestrierung) — Start **KR3a** (Retriever → KR2-Schema).  
Kein Gesetzesimport, kein Fine-Tuning.

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-27 | Erstfassung: KR Microschritte · KL/KI/KH · KT/KP/KU beschrieben · RAG zuerst |
| 2026-11-27 | **KR1 ✅** Ist-Inventar: Pipeline, phrase nur Money, CodeSearch unverdrahtet, Lücken |
| 2026-11-27 | **KR2 ✅** `KichelRagFact` + Prompt-Vertrag + Fallback/`compose` · Money angebunden · Selftest |
