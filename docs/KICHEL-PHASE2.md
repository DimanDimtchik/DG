# Kichel Phase 2 — Ollama & Regeln

Stand: 2026-09-13 · Branch `cursor/kichel-assistant-1dc6`

## Verbindliche Architektur

| Aufgabe | Wer rechnet / entscheidet | Wer formuliert |
|---------|---------------------------|----------------|
| **Geldbeträge** (Skonto, USt, Summen, Fälligkeit) | **PHP** (`KichelMoneyLogic`, CRM-Buchhaltung) | optional Ollama |
| **Fachinhalt** (Was ist Skonto?, Wo einstellen?) | **Wissensbasis** (`KichelKnowledge`) | optional Ollama |
| **Code / DB** | **PHP-Suche** (`KichelCodeSearch`, `KichelSchemaCatalog`) | optional Ollama |
| **Freie Formulierung** | — | **Ollama** (nur mit vorgegebenen Fakten) |

**Ollama darf keine Beträge berechnen oder ändern.**  
Grund: Lokale Modelle verwechseln Fachbegriffe (Skonto als Aufschlag) trotz korrekter Grundrechenarten.

## Ablauf Phase 2

```text
Nutzerfrage
    → KichelAssistant (Phase 1: Regeln, Code, DB)
    → KichelMoneyLogic (falls Betragsfrage → PHP-Ergebnis)
    → strukturierte Fakten (JSON)
    → KichelOllamaClient::phrase() — nur Erklärtext
    → Antwort an UI (Zahlen aus PHP, Text aus Ollama oder Fallback)
```

## Ollama-Anbindung

- Entwicklung: Laptop `http://127.0.0.1:11434` (z. B. `llama3.1:8b`)
- Server: optional Hetzner, Modell 7B–13B empfohlen
- Konfiguration: `config/kichel.local.php` (Beispiel: `config/kichel.local.php.example`)
- Modus `ollama_enabled = false` → Phase 1 wie bisher (regelbasiert)

## Getestete Modelle (Laptop)

| Modell | Lauffähig | Für Kichel |
|--------|-----------|------------|
| llama3.1:8b | ja | Formulierung |
| qwen2.5-coder:7b | ja | Code-Kontext |
| qwen2-math:7b | ja | **nicht** für Skonto-Fachlogik |
| qwen2.5:32b | GPU OOM | erst Server mit mehr RAM |

## Implementierung im Repo

| Datei | Rolle |
|-------|--------|
| `src/Kichel/KichelMoneyLogic.php` | Beträge in PHP |
| `src/Kichel/KichelOllamaClient.php` | Ollama nur `phrase()`, Fakten unveränderlich |
| `src/Kichel/KichelAssistant.php` | Orchestrierung |
| `src/Kichel/KichelLegalPages.php` | Pflichtseiten mit Ansehen-/Bearbeiten-Links |
| `src/Kichel/KichelProtocolRepository.php` | Anfragen-Protokoll (Admin) |
| Migration **067** | Tabelle `dg_kichel_log` |
| `/app?page=kichel-protokoll` | Protokoll-Ansicht (nur Admin) |
| `bin/kichel-protocol-list.php` | JSON auf dem Server (CLI) |
| `bin/cloud-agent-kichel-protocol.sh` | **Cloud-Agent** holt Protokoll per SSH |

## Cloud-Agent: Protokoll abrufen

Wenn der Nutzer Kichel-Antworten besprechen will, **zuerst Protokoll laden**:

```bash
bash bin/cloud-agent-kichel-protocol.sh 30
```

Ausgabe: JSON mit `query`, `answer`, `username`, `created_at` — dann gezielt Antworten verbessern.
