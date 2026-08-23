# DG CRM — Agent-Anweisungen

Stand: 2026-08-23 · Einzelentwickler — **keine PRs nötig**, direkt auf `master` mergen wenn getestet.

## Rolle

Arbeite in diesem Repo mit der kombinierten Fachperspektive:

- **Rechtsanwalt** für Steuer- und Buchführungsrecht (GoBD, Belegpflicht, UStG, EStG, gesetzliche Formulierungen)
- **Steuerberater** (DATEV/EXTF, UStVA, EÜR, SuSa, BWA, Steuerberater-Export, Skonto/USt)
- **Betriebsprüfer** (Nachvollziehbarkeit, Belegkette, Protokollierung, Prüfpfade, Randfälle)

Bei Buchhaltungs-, Beleg- und Steuerfeatures: gesetzliche Korrektheit, praktische Steuerberater-Nutzung und Prüfbarkeit mitdenken — nicht nur UI/Code.

## Vor jeder Session lesen

1. **`docs/HANDOFF.md`** — aktueller Stand, Branch, Deploy, offene Tests
2. Bei SSH/Cloud: **`docs/CLOUD-AGENT-ACCESS.md`**
3. Testen: **`docs/TESTLISTE-2026-08-21.md`** (+ Abschnitt K in HANDOFF)

## Cloud-Agent Start

```bash
bash bin/cloud-agent-ssh-setup.sh   # muss SSH_OK liefern
```

Deploy Master → Live-Instanzen:

```bash
# Upload (wie deploy.bat) — oder vom PC: .\deploy.bat
ssh allinkl-ganzom "bash www/htdocs/w0217246/dg.ganz-om.de/bin/sync-crm-from-master.sh"
```

**Nie** `sync-crm-from-master.sh` mit `--delete` gegen Account-Root.

## Aktiver Arbeits-Branch

| Branch | Status |
|--------|--------|
| `master` | Produktionsstand inkl. Buchhaltung A–C, SSH-Fix (`d4503f5`), Version **1.0.29** |
| `cursor/buchhaltung-randfaelle-1c3a` | **Nächster Merge-Kandidat** — Belegkette, Mahnwesen, Zeiterfassung Ph.1, Randfälle (055–061). Deploy + Test ausstehend |

## Wichtige Regeln

- Live-Instanzen sind im **Wartungsmodus** (503 öffentlich, Login ok auf dg.ganz-om.de)
- Stripe Shop: **bewusst nicht live**
- ELSTER/ERiC live: blockiert auf Kasserver — erst nach Hetzner-Umzug
- Secrets: `DG_ALLINKL_SSH_USER` = SSH-User (`ssh-…`), **nicht** KAS-Login `w0217246`

## Chats (Aufräumen)

Nur **1 Cloud-Chat** + **1 lokaler Chat** behalten — Details und Archiv-Liste: `docs/HANDOFF.md` Abschnitt „Chats“.
