# Weg A — Desktop-Agent (Cline/Roo + GEX45-Ollama)

> Stand: **2026-11-11** · Ziel: Du programmierst/recherchierst agentisch ohne Cursor-Auto-Volumen.  
> **Nicht** Kichel/Tablet (das ist Weg B).  
> Server: Ollama nur localhost am GEX · vom PC per SSH-Tunnel → `http://127.0.0.1:11434/v1` (SKB1d) · Modelle `qwen2.5-coder:14b`, `llama3.1:8b`.

---

## Was geht **schon jetzt** (ohne A)

| Nutzung | Geht? |
|---------|--------|
| Cursor-Chat mit Qwen/Llama (Override URL) | ja — Text/Code erklären, skizzieren, kurze Diffs diskutieren |
| Websuche / Browser / Download-Agent | nein — dafür A (oder Cursor-Auto) |
| Tablet / Kichel-Kunden | nein — Weg B |

**Fazit:** Modelle sind **nicht unnutzbar**. Ohne A = günstiger Chat-Assistent. Mit A = Agent inkl. Recherche.

---

## Mini-IDs

| ID | Aufgabe | Geschätzt | Status |
|----|---------|-----------|--------|
| **KA0** | Entscheidung: **Cline** (nicht Roo; Roo optional später) · Doc-Haken hier | 🟢 0,5 h | [x] 2026-11-11 |
| **KA1** | Plugin installieren · Tunnel + Base URL `http://127.0.0.1:11434/v1` · Modell `qwen2.5-coder:14b` · API-Key beliebig · Smoke „Hallo“ | 🔴 0,5–1 Tag | [ ] |
| **KA2** | Websuche: Brave **oder** Tavily API-Key im Plugin · Test: „Suche X, 5 Bulletpoints“ | 🔴 0,5–1 Tag | [ ] |
| **KA3** | Repo-Smoke im DG-Clone: kleine Doc-Datei anlegen lassen (ohne Auto) | 🔴 0,5 Tag | [ ] |
| **KA4** | Laptop gleiche Config · Firewall/URL ok · Kurztest | 🔴 0,5 Tag | [ ] |
| **KA5** | Absicherung 11434 planen/umsetzen (nach Alltag) — siehe `TODOS.md` Ops-Merkposten | 🔴 1–2 Tage | [ ] später |
| **KA6** | Statusnotiz: A grün / Grenzen · Verweis Weg B für Kichel/Tablet | 🟢 0,5 h | [ ] |

**Reihenfolge:** KA0 → KA1 → KA2 → KA3 → KA4 · KA5 nach stabil · KA6 Abschluss.

**Chat-Prompt:**  
`Nur Mini-ID KAx laut docs/KI-AGENT-WEG-A-PLAN.md. Kein Weg B. Kein CRM-Cutover.`

---

## Erfolg

- Agent-Chat ohne Cursor-Auto erledigt eine **Web-Recherche** und eine **Repo-Änderung**.  
- PC + Laptop nutzbar. Tablet bewusst offen (B).

---

## Abgrenzung

| Weg | Wer | Wann |
|-----|-----|------|
| **A** (dieser Plan) | du, Desktop | jetzt |
| **B** | Kichel + Browser/Tablet | nach A oder parallel Spec |
