# DG CRM — Konsolidierter Handoff

Stand: **2026-08-23** · Alle Cloud-/Lokal-Chats hier zusammengeführt.  
Agent-Rolle: siehe **`AGENTS.md`** (Rechtsanwalt Steuer-/Buchführungsrecht · Steuerberater · Betriebsprüfer).

---

## 1. Chats aufräumen (1 Cloud + 1 Lokal)

Cursor-Chats können vom Agent **nicht** gelöscht werden — bitte manuell archivieren.

### Cloud — BEHALTEN (1)

| Chat | URL | Warum |
|------|-----|-------|
| **Aktueller stand** | https://cursor.com/agents/bc-a92ca25a-953a-480c-b202-17d0bd621c3a | Hauptarbeit: Buchhaltung Randfälle, Belegkette, Mahnwesen, Zeiterfassung |

### Cloud — ARCHIVIEREN (6)

| Chat | Grund |
|------|-------|
| Ssh deploy functionality (`bc-bc0affd4`) | Erledigt → Fix auf `master`; Inhalt hier + AGENTS.md |
| Ssh bereitstellung (`bc-cec1bc7f`) | Redundant |
| Ssh bereitstellung (`bc-1b6845c9`) | Redundant |
| Ssh bereitstellung (`bc-20e8eb99`) | Redundant |
| Umgang mit unklarer Anweisung (`bc-3d52fe8b`) | Nur Chat-Wiederherstellung |

Archivieren: Cursor Dashboard → Agent-Run → Menü → Archive.

### Lokal (Desktop) — BEHALTEN (1)

Den Chat behalten, der **Buchhaltung / Belegkette / Zeiterfassung** betrifft (nicht die reinen SSH-Fehlerversuche).  
SSH funktioniert jetzt — alte SSH-Debug-Chats können geschlossen/archiviert werden.

---

## 2. Git-Stand

| Branch | Commit (Kurz) | Bedeutung |
|--------|---------------|-----------|
| **`master`** | `d4503f5` | Live-Basis: Buchhaltung A–C, Import, Website-Bootstrap, **SSH-Key-Fix** |
| **`cursor/buchhaltung-randfaelle-1c3a`** | `659cf3e` | **Offene Arbeit** — siehe Abschnitt 4 |

Version: **`1.0.29`** (`config/version.php`)

Alte Branches (`buchhaltung-phase-abc`, `install-data-import`, SSH-Branches) sind obsolet — nach Merge ggf. remote löschen.

---

## 3. SSH & Deploy (seit 23.08.2026 OK)

### Secrets (Cursor Dashboard → Cloud Agents → Secrets)

| Secret | Wert |
|--------|------|
| `DG_ALLINKL_SSH_PRIVATE_KEY` | PC-Key `id_ed25519_ganzom` (einzeilig OK — Setup reformatiert) |
| `DG_ALLINKL_SSH_USER` | **`ssh-…`-User** — **nicht** KAS `w0217246` |
| `DG_ALLINKL_SSH_HOST` | `….kasserver.com` |
| `DG_CRM_SSH_HOST` | `dg.ganz-om.de` |

### Cloud-Agent

```bash
bash bin/cloud-agent-ssh-setup.sh    # → SSH_OK
```

Fix in `master`: einzeilige Keys werden automatisch mehrzeilig geschrieben (`d4503f5`).

### PC

```powershell
ssh allinkl-ganzom
.\deploy.bat
.\deploy.ps1 -WithPlugins    # optional
```

### Sync zu Live-Instanzen (auf Server)

```bash
bash bin/sync-crm-from-master.sh
```

**Verboten:** `--delete` gegen `/www/htdocs/w0217246` (Geschwister-Domains).

Details: [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md)

---

## 4. Nächste Arbeit: Branch `cursor/buchhaltung-randfaelle-1c3a`

**Noch nicht auf `master` gemergt.** Enthält u. a.:

| Block | Migration |
|-------|-----------|
| Belegkette (Angebot→AB→Lieferschein→Abschlag→Schlussrechnung) | 055 |
| Dokument-Workflow / Statusfilter | 056 |
| Freitext vor/nach Positionen | 057 |
| Gesetzliche Klauseln (§19, §13b, PV, igL, …) | 058 |
| Skonto-Stufen, Mahnwesen + Autostart | 059 |
| Teilzahlungen | 060 |
| Zeiterfassung Ph.1 (Stempeluhr, Zwangspause, Autoclose) | 061 |
| Randfälle PRAP/Rabatt/Trinkgeld | — |
| Terminkalender-Landing bei Install | — |

Doku neu: `BUCHHALTUNG-BELEGKETTE.md`, `CRON-AUTOSTART.md`, `ZEITERFASSUNG-PLAN.md`

### Workflow (Solo)

```powershell
git fetch origin
git checkout cursor/buchhaltung-randfaelle-1c3a
git pull
# testen, dann:
git checkout master && git merge cursor/buchhaltung-randfaelle-1c3a && git push
```

Deploy nach Merge: `.\deploy.bat` oder Cloud-Agent SSH + scp.

Migrationen **055–061** laufen beim nächsten CRM-Login automatisch.

---

## 5. Live-Instanzen

| Instanz | Wartung | URL |
|---------|---------|-----|
| Master-CRM | ON | https://dg.ganz-om.de (Login ok) |
| ganz-soft.de | ON | 503 öffentlich |
| kontur-cosmetics.de | ON | Adresse ggf. prüfen |
| shop.ganz-soft.de | — | Stripe **nicht live** |

---

## 6. Prioritäten

1. Branch `cursor/buchhaltung-randfaelle-1c3a` deployen + testen
2. Migrationen 055–061 prüfen, Nummernkreise einrichten
3. Manuelle Tests (Abschnitt 7) — dann Merge → `master`
4. Danach wählen: Zeiterfassung Ph.2 **oder** Wartung aus **oder** Stripe

---

## 7. Test-Checkliste (Randfälle-Branch)

Nach Deploy auf **einer** Test-Instanz (z. B. ganz-soft.de, Wartung bleibt an):

- [ ] Belegkette: Angebot → AB → Lieferschein → Rechnung, Panel-Links
- [ ] Workflow-Status: Entwurf → Versendet → Angenommen → Abgerechnet
- [ ] Gesetzliche Klauseln in PDF (§19, §13b, PV)
- [ ] Skonto-Stufen + Mahnlauf (Autostart ohne KAS-Cron)
- [ ] Teilzahlungen, Status `partial`
- [ ] Zeiterfassung: Stempeln, Zwangspause, Team-Ansicht, Autoclose
- [ ] Randfälle: PRAP-Einnahmen, negative Rabattzeilen, Trinkgeld
- [ ] Terminkalender-Landing nach frischer Installation

Basis-Tests Buchhaltung A–C: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) Abschnitt J.

---

## 8. Bewusst zurückgestellt

| Thema | Doku |
|-------|------|
| Stripe Live | `SHOP-TODO.md` |
| ELSTER/ERiC live | `ELSTER-ERIC-TODO.md` (Hetzner) |
| AGB/Widerruf juristisch | Anwalt extern |
| OCR/Beleg-Automatisierung | später |
| Zeiterfassung Ph.2+ | `ZEITERFASSUNG-PLAN.md` |

---

## 9. Weitere Handoff-Dateien (Detail)

| Datei | Inhalt |
|-------|--------|
| [`PC-HANDOFF.md`](PC-HANDOFF.md) | PC-Deploy, Import, Website-Bootstrap |
| [`BUCHHALTUNG-PC-HANDOFF.md`](BUCHHALTUNG-PC-HANDOFF.md) | Buchhaltung A–C Detail |
| [`HANDY-HANDOFF.md`](HANDY-HANDOFF.md) | Kurz für Mobile |
| [`CLOUD-AGENT-ACCESS.md`](CLOUD-AGENT-ACCESS.md) | Secrets, Hosts, SSH |
