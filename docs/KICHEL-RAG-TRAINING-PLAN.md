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
| **A — RAG (jetzt)** | **KR0–KR9** | Verdrahtung Suche → Fakten → Qwen; CRM-Korpus; Quellen; Smoke | **KR0–9 ✅** Gate offen |
| **B — Rechtskorpus** | **KL0–KL5** | Gesetze/Urteile importieren & indexieren (nach stabilem RAG) | **KL0–5 ✅** · KR8b ✅ |
| **C — Intent-LLM** | **KI0–KI3** | Qwen nur als Frage-Parser (JSON), Suche weiter PHP | geplant |
| **D — Produkt/Haftung** | **KH0–KH3** | Disclaimer, Protokoll-Felder, Admin-Qualität | **KH0 ✅** · KH1–3 geplant |
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
| **KR3** | Retrieval-Orchestrierung (Reihenfolge Sucher) | 🟡 | [x] 2026-11-27 · a–d ✅ |
| **KR4** | CRM-Korpus v1 (Knowledge/Nav/Felder → einheitliche Chunks) | 🟡 | [x] 2026-11-27 · a–c ✅ |
| **KR5** | Ranking / „kein Treffer“ / Mehrfachtreffer | 🟡 | [x] 2026-11-27 · a–b ✅ |
| **KR6** | Quellenzeile in Antwort + Protokoll | 🟡 | [x] 2026-11-27 · a–b ✅ |
| **KR7** | Super-Kichel/Widget: gleiche Pipeline (kein Parallelweg) | 🟡 | [x] 2026-11-27 |
| **KR8** | Selftests + Dev-Smoke | 🟢 | [x] 2026-11-27 · a ✅ · b ✅ Dev-Browser `ganz-om.de` |
| **KR9** | Docs-Haken / Abnahmekriterien „RAG stabil“ | 🟢 | [x] 2026-11-27 · §3.10 |

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

`KichelAssistant::wrapMoney` → RAG-Fakten + `compose`; Response zusätzlich `rag_facts`.

**Selftest:** `php bin/kichel-rag-fact-selftest.php` → ALL OK.

---

### 3.4 KR3 — Retrieval-Orchestrierung ✅ 2026-11-27

| Kind | Aufgabe | Abnahme | Status |
|------|---------|---------|--------|
| **KR3a** | Eine Stelle Retriever → KR2-Schema | `KichelRetriever::retrieve` | [x] |
| **KR3b** | Legal → Ledger → Money(calc) → freie Suche; RAG nur bei `candidates`/`money` | Pfad-Feld `path` | [x] |
| **KR3c** | Budget Chunks/Zeichen | `rag_max_facts` / `rag_max_chars` · `applyBudget` | [x] |
| **KR3d** | Flag `rag_enabled` | Default **true** wenn Key fehlt · Example gesetzt | [x] |

**Nicht in KR3:** Embedding-DB, Vektorsuche, neuer Gesetzeskorpus (→ KL / KR4c).

#### Aufrufkette

```text
KichelAssistant::answer
  → KichelRetriever::retrieve
       path legal | ledger | money | candidates | empty
  → Wrap:
       money / (candidates + rag_enabled) → compose + rag_facts
       candidates + !rag_enabled → klassische PHP-Bausteine
```

Config (`config/kichel.local.php` / `.example`): `rag_enabled`, `rag_max_facts` (5), `rag_max_chars` (4000).

**Selftest:** `php bin/kichel-retriever-selftest.php` → ALL OK.

---

### 3.5 KR4 — CRM-Korpus v1 (ohne Gesetze) ✅ 2026-11-27

| Kind | Aufgabe | Abnahme | Status |
|------|---------|---------|--------|
| **KR4a** | Knowledge → Chunks „CRM-Hilfe“ | `KichelCorpus::chunkFromTopic` | [x] |
| **KR4b** | Nav/Feld/Media gleiches Format | `chunkFromCandidate` / `factsFromCandidates` | [x] |
| **KR4c** | Steuer-Kurztexte (10) + Suche | `data/tax-shorttexts.php` · `matchTaxShorttexts` im Retriever | [x] |

**Nicht in KR4:** GII-XML-Massenimport (→ **KL**).

#### Dateien

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelCorpus.php` | Chunk-Builder + Tax-Match |
| `src/Kichel/data/tax-shorttexts.php` | Skonto, §19, Ist/Soll, §13b, §14, Belegkette/GoBD, Vorsteuer, UStVA, Aufbewahrung, DATEV |
| `KichelRetriever` | bindet Tax-Kandidaten in freie Suche ein |

Quelle Tax: Label **CRM-Steuerkurztext**, Ref `tax:{id}`, Body inkl. Normhinweis. Texte: eigene Kurzform + CRM-Pfad, Disclaimer „keine Rechtsberatung“.

**Selftest:** `php bin/kichel-corpus-selftest.php` → ALL OK.

---

### 3.6 KR5 — Ranking & Leerfall ✅ 2026-11-27

| Kind | Aufgabe | Abnahme | Status |
|------|---------|---------|--------|
| **KR5a** | Score-Minimum → Leerfall + Formulierhilfe | `KichelRanker::MIN_ACCEPT_SCORE=5` · `notFoundMessage` | [x] |
| **KR5b** | Top-K + relative Floor; Mehrfach nummeriert | `TOP_K=4` · `RELATIVE_FLOOR=0.5` · `multiFactBaseAnswer` | [x] |

#### Verhalten

- Nach Rohsuche: `KichelRanker::select` → bei Top &lt; 5: `path=empty`, **keine** Fakten an phrase.
- Relative Schwelle: Score &lt; max(5, floor(top×0.5)) verworfen.
- Mehrere Treffer: nummerierte Basis „bitte getrennt lesen“ + eine Quellenliste.
- Response-Feld `rank_meta` (`top_score`, `kept`, `rejected`, `reason`).

**Selftest:** `php bin/kichel-ranker-selftest.php` → ALL OK.

---

### 3.7 KR6 — Quellen & Protokoll ✅ 2026-11-27

| Kind | Aufgabe | Abnahme | Status |
|------|---------|---------|--------|
| **KR6a** | Strukturierte `sources[]` + UI-Block „Quellen“ | Widget + Super-Kichel (`renderSources`) | [x] |
| **KR6b** | Protokoll: RAG + Quellen-Refs | `metaFromResponseJson` · Spalten in `kichel-protokoll` · **keine** Migration (JSON reicht) | [x] |

#### API

- Response: `sources: [{ label, ref, href }]` · eingebettete `Quelle:`-Zeilen aus `answer` entfernt, wenn `sources` gesetzt.
- `rag_used` / `rag_facts` weiter im `response_json` fürs Protokoll.

**Selftest:** `php bin/kichel-sources-selftest.php` → ALL OK.

---

### 3.8 KR7 — Ein Pipeline-Pfad ✅ 2026-11-27

Widget und Super-Kichel nutzen **dieselbe** Hilfe-Kette:

```text
window.dgKichel.apiUrl = /api/kichel
  → KichelApi::handle
  → KichelAssistant::answer
  → Retriever / Ranker / Corpus / compose
```

| Prüfung | Ergebnis |
|---------|----------|
| Beide JS: `cfg.apiUrl` | ja |
| Kein Direkt-Ollama in den UIs | ja |
| Super-Kichel-View ohne eigene Help-API | ja |
| OpenHands-Button | **SKP** (Programmieren) — absichtlich getrennt |

**Selftest:** `php bin/kichel-pipeline-selftest.php` → ALL OK.

---

### 3.9 KR8 — Tests ✅/◐ 2026-11-27

| Kind | Aufgabe | Abnahme | Status |
|------|---------|---------|--------|
| **KR8a** | Selftest CLI: feste Fragen → Quellen-IDs / kein Fantasie-§ | `bin/kichel-rag-smoke-selftest.php` · Suite `bin/kichel-rag-suite-selftest.php` | [x] SUITE ALL OK |
| **KR8b** | Manuell Dev: 3 gut / 2 unklar; Ollama an/aus | Checkliste unten | [x] 2026-11-27 · Dev-Browser `ganz-om.de` |

#### KR8a — feste Fragen (CLI)

| Frage | Erwartung |
|-------|-----------|
| USt-ID | Quelle `topic:ust-id` / Firmendaten · kein Fantasie-§ |
| Kleinunternehmer | `tax:tax-u19` · §§ nur aus Fakten |
| Skonto 3 % von 1000 € | `money:skonto` · Beträge PHP |
| Ist-Versteuerung | `tax:tax-ist-soll` |
| Pflichtseiten | `legal:pflichtseiten` |
| asdf… | Leerfall, keine Quellen, kein § |
| vage Steuerfrage | leer oder nur Fakten-§ |

#### KR8b — Dev-Browser (`ganz-om.de`, Staff)

Fragen wie KR8a (mind. 3 gut + 2 unklar) in **Widget** und einmal **Super-Kichel**:

- [x] Ollama **aus** (`ollama_enabled=false`): USt-ID → Fallback + Quellen (`topic:ust-id`) sichtbar
- [x] Ollama **an**: USt-ID / Kleinunternehmer / Skonto 970 € / Ist-Versteuerung · Quellen bleiben
- [x] Nonsense → „nichts Passendes“, keine erfundenen Normen
- [x] Protokoll: Spalten RAG + Quellen (ja/`topic:ust-id` · nein/— bei Nonsense)

**Dev-UI 2026-11-27:** Widget + Super-Kichel auf `ganz-om.de` · Ollama danach wieder an.

---

### 3.10 KR9 — „RAG stabil“ (Gate vor Training) ✅ 2026-11-27

RAG gilt als **stabil**, wenn:

| # | Kriterium | Nachweis 2026-11-27 |
|---|-----------|---------------------|
| 1 | KR2–KR8 erledigt | KR2–KR8a ✅ · Suite `php bin/kichel-rag-suite-selftest.php` → **SUITE ALL OK** |
| 2 | Unklare Fragen → kein erfundenes Recht | Smoke: Nonsense/vage ohne Fantasie-§ |
| 3 | Stichproben Skonto / Nav·Firma / Steuerkurztext | `money:skonto` · `topic:ust-id`/Firmendaten · `tax:tax-u19` / `tax-ist-soll` |
| 4 | Protokoll zeigt Fakten | `sources` + `rag_used` in API · `metaFromResponseJson` · Protokoll-Spalten RAG/Quellen (KR6) |
| 5 | Bewusste Freigabe | **dieser Abschnitt** |

**Rest (nicht Gate-Blocker für KL-Planung):**

- ~~KR8b Dev-Browser~~ ✅ 2026-11-27  
- Live-Deploy KR/KL-Stände: Kern+Sync 2026-11-27 erledigt.

#### Freigabe-Satz (verbindlich)

> **RAG-Gate KR9 ist offen (2026-11-27).**  
> Erlaubt: Phase **KL** (Rechtskorpus-Index) vorbereiten/starten.  
> **Nicht** ohne neues Gate: Fine-Tuning (**KT**) oder volles Gesetzestraining (**KP**).  
> KT/KP erst nach bewusster Qualitäts-/Haftungsentscheidung und idealerweise nach erledigter Dev-UI-Smoke (KR8b).

**KR9 = Doc-Haken + Freigabe-Satz — Training (KT/KP) startet nicht ohne erneute Freigabe.**

---

## 4. Phase B — Rechtskorpus (**KL** — nach KR9)

> Eigenes Indexieren freier Normen/Urteile. Weiterhin **kein** Fine-Tuning nötig.

| ID | Inhalt | Status |
|----|--------|--------|
| **KL0** | Quellenentscheid (Doc) | [x] 2026-11-27 · §4.1 |
| **KL1** | Import-Pipeline: TOC → AO/UStG/EStG (+ ggf. HGB) → Chunks | [x] 2026-11-27 · §4.2 |
| **KL2** | Speicher Index (Volltext zuerst) | [x] 2026-11-27 · §4.3 |
| **KL3** | Retriever → `type=statute` | [x] 2026-11-27 · §4.4 |
| **KL4** | Update-Job TOC-Diff | [x] 2026-11-27 · §4.5 |
| **KL5** | Pilot Urteile (später, eng) | [x] 2026-11-27 · §4.6 · BFH+Themenfilter |

### 4.1 KL0 — Quellenentscheid ✅ 2026-11-27

#### Entscheidung (verbindlich)

| Rolle | Quelle | Format / Einstieg | Nutzung in DG |
|-------|--------|-------------------|---------------|
| **Primär Normen** | [gesetze-im-internet.de](https://www.gesetze-im-internet.de/) (BMJV/BfJ) | XML + TOC [`gii-toc.xml`](https://www.gesetze-im-internet.de/gii-toc.xml) · DTD `gii-norm.dtd` | **KL1+** Import; Antwortgrundlage = **unser Index** |
| **Optional Refresh/Suche** | Bund-API [docs.rechtsinformationen.bund.de](https://docs.rechtsinformationen.bund.de/) | JSON REST; Datensatz **noch unvollständig**; Bulk-XML geplant | ergänzend, **nicht** alleinige Wahrheitsquelle |
| **Urteile (später KL5)** | [rechtsprechung-im-internet.de](https://www.rechtsprechung-im-internet.de/) | XML + [`rii-toc.xml`](https://www.rechtsprechung-im-internet.de/rii-toc.xml) | Pilot BFH/Themenfilter — kein Vollbestand |
| **Community optional** | Open Legal Data | API / HuggingFace-Dumps (ODbL Sammlung) | nur Experiment; Qualität ≠ Juris |

**Nicht als Quelle:** Beck/Juris/Kommentare; Scraping von Verlagsseiten; Live-API als einzige Antwortbasis ohne gespeicherten Stand.

#### Warum GII primär

- Freie Weiterverwertung der DE-Normtexte (Hinweise GII §6); maschinenlesbares XML.
- Tagesaktuelles TOC (Erreichbarkeit 2026-11-27: `gii-toc.xml` HTTP 200).
- Konsolidierte Fassungen — **nicht** die amtliche BGBl-Fassung (Hinweis in Antworten/Quellen: „konsolidiert GII, Stand …“).
- Passt zur Produktregel: prüfbarer Indexstand bei uns (KR-Haftung).

#### Pilot-Gesetze für KL1 (Festlegung)

Start-Whitelist (Abkürzungen wie in GII üblich):

1. **AO**  
2. **UStG**  
3. **EStG**  
4. optional danach **HGB** (Kern, wenn Import stabil)

Kein Massenimport aller Bundesgesetze in KL1.

#### Recht / Produkt

- Normen/amtliche Entscheidungen: typisch § 5 UrhG nutzbar.  
- Erläuterungen weiter nur **eigene** Kurztexte (`tax-shorttexts` / Knowledge) oder lizenzierte Inhalte.  
- Endnutzer: Orientierungshilfe, keine Rechtsberatung; Quelle + Abruf-/Index-Stand nennen (KL3/KR6).

#### Abnahme KL0

- [x] Primärquelle GII XML/TOC festgelegt  
- [x] Bund-API = optional, nicht Primär  
- [x] Urteile = später RII, eng  
- [x] Pilot-Gesetze AO/UStG/EStG (+ optional HGB)  
- [x] kein Code-Import in KL0  

**Nächste Mini-ID:** **KL1** ✅ → **KL2**.

### 4.2 KL1 — Import-Pipeline (Whitelist) ✅ 2026-11-27

| Teil | Inhalt | Status |
|------|--------|--------|
| **KL1a** | TOC `gii-toc.xml` → Slug-Whitelist AO/`ao_1977`, UStG/`ustg_1980`, EStG/`estg` (+ HGB/`hgb`) · https-Links | [x] |
| **KL1b** | GII-XML `<norm>` → Plaintext · Chunks `type=statute` · § mit ≥2 Abs. → Abs.-Chunks · Href `…/{slug}/__….html` | [x] |
| **KL1c** | CLI + JSONL unter `storage/kichel/gii/` + Selftest (Fixtures offline) | [x] |

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelGiiImporter.php` | Whitelist, TOC, Download/ZIP, `writeChunks` |
| `src/Kichel/KichelGiiXmlParser.php` | XML → §/Abs.-Chunks |
| `bin/kichel-gii-import.php` | CLI (`--dry-run`, `--laws`, `--toc`, `--xml`) |
| `bin/kichel-gii-import-selftest.php` | Offline-Abnahme |
| `tests/fixtures/kichel/gii-*.xml` | Mini-TOC + UStG-Mini |

**Nicht in KL1:** Retriever-Anbindung (→ **KL3**), Update-Diff (→ **KL4**), Urteile.

#### Abnahme KL1

- [x] TOC löst nur Whitelist-Slugs (Titel-Nadel)  
- [x] Unbekanntes Gesetz → Fehler  
- [x] Parser: §/Anlage, Abs.-Split, Standhinweis „konsolidiert GII“  
- [x] Selftest grün ohne Netz  

**Nächste Mini-ID:** **KL2** ✅ → **KL3**.

### 4.3 KL2 — Speicher-Index (Volltext) ✅ 2026-11-27

| Teil | Inhalt | Status |
|------|--------|--------|
| **KL2a** | JSONL aus `storage/kichel/gii/` laden · Dedup `source_ref` · Manifests | [x] |
| **KL2b** | Token-Volltext-Score (Gesetz/§-Boost) · `factsFromHits` → `type=statute` | [x] |
| **KL2c** | Selftest offline (Temp-JSONL aus UStG-Mini-Fixture) | [x] |

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelStatuteIndex.php` | Load / Search / Facts |
| `bin/kichel-statute-index-selftest.php` | Abnahme |

**Nicht in KL2:** Verdrahtung in `KichelRetriever` (→ **KL3**).

#### Abnahme KL2

- [x] Leerer Ordner → 0 Chunks  
- [x] `§ 19 UStG` → Top `enbez=§ 19`  
- [x] Inhaltsquery „Fahrzeug / Erwerb“ → `§ 1b`  
- [x] Bad JSONL / Dedup robust  

**Nächste Mini-ID:** **KL3** ✅ → **KL4**.

### 4.4 KL3 — Retriever ← Statute-Index ✅ 2026-11-27

| Teil | Inhalt | Status |
|------|--------|--------|
| **KL3a** | `collectCandidates` + `statuteCandidates` (Min-Score 8) · Config `rag_statute_*` | [x] |
| **KL3b** | `KichelCorpus` mappt `kind=statute` → `type=statute` / Label GII | [x] |
| **KL3c** | Selftest + Suite-Haken (KL1–3) | [x] |

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelRetriever.php` | KL3-Verdrahtung |
| `src/Kichel/KichelCorpus.php` | statute source_label |
| `bin/kichel-statute-retriever-selftest.php` | Abnahme |
| `config/kichel.local.php.example` | `rag_statute_enabled` / `limit` / `dir` |

**Nicht in KL3:** TOC-Diff/Update-Job (→ **KL4**), Urteile.

#### Abnahme KL3

- [x] `§ 19 UStG` → facts `type=statute`, ref `gii:ustg:§19…`  
- [x] USt-ID-Frage bleibt CRM-Navigation (kein Fantasy-Top-§)  
- [x] KR3-Retriever-Selftest weiter grün  

**Nächste Mini-ID:** **KL4** ✅ → **KL5** geparkt / optional KR8b.

### 4.5 KL4 — TOC-Diff Update ✅ 2026-11-27

| Teil | Inhalt | Status |
|------|--------|--------|
| **KL4a** | Snapshot `toc-snapshot.json` (Slug/Titel/Link-Fingerprint) · Diff added/changed/removed | [x] |
| **KL4b** | `--check` / `--apply` / `--force` / `--dry-run` · nur geänderte Whitelist neu laden | [x] |
| **KL4c** | Selftest offline + Suite-Haken | [x] |

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelGiiUpdater.php` | Snapshot, Diff, Apply |
| `bin/kichel-gii-update.php` | CLI |
| `bin/kichel-gii-update-selftest.php` | Abnahme |

Nach `--apply` mit Download: `KichelStatuteIndex::reset()`. Snapshot ist Instanz-Datei (gitignore).

**Hinweis:** TOC-Diff erkennt Titel/Link-Änderungen der Whitelist-Einträge — nicht stillschweigende XML-Inhalte bei gleichem TOC-Eintrag (`--force` als Fallback).

#### Abnahme KL4

- [x] Erster Lauf → alle Whitelist `added`  
- [x] Gleicher TOC → `to_import=[]`  
- [x] Titeländerung UStG → nur UStG  
- [x] `removed` wenn Snapshot-Gesetz fehlt  
- [x] `--force` reimportiert  

**Nächste Mini-ID:** **KL5** ✅ · KR8b ✅ · **KH0** ✅ → optional KH1.

### 4.6 KL5 — Pilot Urteile (RII / BFH) ✅ 2026-11-27

| Teil | Inhalt | Status |
|------|--------|--------|
| **KL5a** | TOC `rii-toc.xml` → nur `gericht` BFH · Cap · Themenfilter (EStG/UStG/AO/…) | [x] |
| **KL5b** | XML → `type=caselaw` (Leitsatz/Tenor) · JSONL `storage/kichel/rii/` | [x] |
| **KL5c** | `KichelCaselawIndex` + Retriever · Selftest offline | [x] |

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelRiiImporter.php` | TOC/Filter/Import |
| `src/Kichel/KichelRiiXmlParser.php` | Entscheidungs-XML |
| `src/Kichel/KichelCaselawIndex.php` | Volltext-Index |
| `bin/kichel-rii-import.php` | CLI |
| `bin/kichel-rii-import-selftest.php` | Abnahme |
| `tests/fixtures/kichel/rii-*` | Mini-TOC + BFH-XML |

**Kein Vollbestand** — Default `--max=15`. Themenfilter nach Download (TOC hat keine Normen).

#### Abnahme KL5

- [x] TOC filtert BVerwG weg  
- [x] EStG-Entscheidung bleibt, BGB-only nicht  
- [x] Retriever → `type=caselaw`  
- [x] KR3-Retriever-Selftest weiter grün  

**Nächste Mini-ID:** **KR8b** ✅ · **KH0** ✅ → optional KH1–3. **KT/KP** weiter gesperrt.

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
| **KH0** ✅ | Nutzungs-/Hinweistext: „Unterstützung, keine Rechtsberatung; Quelle prüfen“ — `KichelDisclaimer` · Partial Widget+Super · Selftest |
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

**KH0 ✅.** Nächste optional **KH1–3**. Phase B (KL) + KR8b fertig.  
**Kein** KT/KP ohne neues Gate.

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-27 | Erstfassung: KR Microschritte · KL/KI/KH · KT/KP/KU beschrieben · RAG zuerst |
| 2026-11-27 | **KR1 ✅** Ist-Inventar: Pipeline, phrase nur Money, CodeSearch unverdrahtet, Lücken |
| 2026-11-27 | **KR2 ✅** `KichelRagFact` + Prompt-Vertrag + Fallback/`compose` · Money angebunden · Selftest |
| 2026-11-27 | **KR3 ✅** `KichelRetriever` · Pflichtpfade · Budget · `rag_enabled` · Assistant compose |
| 2026-11-27 | **KR4 ✅** `KichelCorpus` · Tax-Kurztexte · einheitliche Chunks |
| 2026-11-27 | **KR5 ✅** `KichelRanker` · Leerfall · Top-K · nummerierte Mehrfachtreffer |
| 2026-11-27 | **KR6 ✅** `sources[]` UI · Protokoll RAG/Refs ohne Migration |
| 2026-11-27 | **KR7 ✅** eine Hilfe-Pipeline Widget=Super-Kichel · Selftest |
| 2026-11-27 | **KR8a ✅** Smoke+Suite Selftests · **KR8b** Dev-UI-Checkliste offen |
| 2026-11-27 | **KR8b ✅** Dev-Browser Widget+Super-Kichel · Ollama an/aus · Protokoll RAG/Quellen |
| 2026-11-27 | **KR9 ✅** RAG-Gate offen für **KL** · KT/KP gesperrt bis neuer Freigabe |
| 2026-11-27 | **KL0 ✅** Quellenentscheid: GII primär · Bund-API optional · Pilot AO/UStG/EStG |
| 2026-11-27 | **KL1 ✅** GII-Import Whitelist · XML→§/Abs.-Chunks · JSONL `storage/kichel/gii/` · Selftest |
| 2026-11-27 | **KL2 ✅** `KichelStatuteIndex` Volltext über JSONL · Selftest · noch ohne Retriever |
| 2026-11-27 | **KL3 ✅** Retriever ← Statute-Index · `type=statute` in facts/sources · Suite |
| 2026-11-27 | **KL4 ✅** TOC-Diff Update · Snapshot · CLI check/apply/force · Selftest |
| 2026-11-27 | **KL5 ✅** RII BFH-Pilot · Themenfilter · Caselaw-Index · Retriever |
| 2026-11-27 | **KH0 ✅** Hinweistext Widget+Super · `KichelDisclaimer` · Selftest |
