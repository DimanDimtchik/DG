# Super-Kichel — Install-Entscheidung (SKB0)

> **Entscheidungstabelle, keine Installation in dieser ID.** Grundlage: [`SUPERKICHEL-INVENTAR-VORLAEUFIG.md`](SUPERKICHEL-INVENTAR-VORLAEUFIG.md) (SKA0-12v).  
> Live-Inventar **SKA0-1…12** kann Einzelentscheidungen nachziehen — dann SKB0-Nachtrag, nicht stillschweigend installieren.  
> Plan: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md)

Stand: **2026-11-12** (Nachtrag) · Erstfassung 2026-11-11

---

## 0. Hinweis zum Stopp 2026-11-11

„Jetzt nein“ in der Erstfassung war **kein Produkt-Nein**. Vom Tablet war **SSH/Secret** blockiert → Install auf GEX war nicht möglich. Cline + Brave (Anmeldung/Limits) sind verworfen. Kriterien für den Stack: **kostenlos · legal · ohne Anmeldung · ohne Limits**.

---

## 1. Verbindliche Entscheidungen

| Komponente | Entscheidung | Nächste ID | Begründung |
|------------|--------------|------------|------------|
| **Ollama** (bereits da) | **Behalten · härten · nutzen** — **nicht** neu installieren | **SKB1** (localhost + UFW) → später **SKC0** | Läuft; öffentlich = Risiko |
| **Docker** | **Ja — installieren** (Voraussetzung Container-Stack) | **SKB2a** | Nur weil SearXNG/OpenHands Container brauchen; CRM bleibt nativ |
| **SearXNG** | **Ja — installieren** | **SKB2b** (nach SKB2a) | Websuche self-host, ohne API-Key/Anmeldung |
| **Playwright** | **Ja — installieren** (GEX, für Agent/UI) | **SKB2c** | Browser-Automation ohne Cloud-Abo |
| **OpenHands** | **Ja — installieren** | **SKB2d** (nach SKB2a) | Agent-Harness statt Cline; self-host, Ollama |
| **ComfyUI** | **Ja — installieren** | **SKB2e** | Bild/GPU-Nebenlinie auf GEX laut Disk-Layout |
| **Forgejo** | **Nein** (bewusst) | — | Produkt-Repo bleibt GitHub |
| **GPU-Treiber** | **Nur prüfen** (SKA0-8), Install nur wenn nötig | SKA0-8 → ggf. Freigabe | kein Blind-Install |

**PHP-Regel unverändert:** Ollama formuliert nur; Beträge/Steuer = PHP ([`KICHEL-PHASE2.md`](KICHEL-PHASE2.md)).

**CRM:** bleibt **nativ** (nginx/PHP/MariaDB). Docker nur für Ops-/KI-Hilfen — kein CRM in Containern.

---

## 2. Reihenfolge (Mini-IDs)

1. **SSH-Secret** ok (`Hetzner_Private_Key` = Private Key) — Voraussetzung  
2. **SKA0-1…12** Live-Inventar (bestätigt SKB0; optional parallel lesen)  
3. **SKB1** Ollama → localhost + UFW — eigene Freigabe  
4. **SKB2a** Docker — eigene Freigabe  
5. **SKB2b** SearXNG — eigene Freigabe (braucht Docker)  
6. **SKB2c** Playwright — eigene Freigabe  
7. **SKB2d** OpenHands — eigene Freigabe (braucht Docker)  
8. **SKB2e** ComfyUI — eigene Freigabe  
9. **SKC0** Dev `ganz-om.de` → Ollama  

**Eine Chat-Session = eine Mini-ID.** Keine Pakete ohne ausdrückliche Freigabe genau dieser ID.

---

## 3. Abnahme SKB0

- [x] Entscheidungstabelle dokumentiert (Erstfassung)  
- [x] Nachtrag 2026-11-12: Docker / SearXNG / Playwright / OpenHands / Comfy = **ja** · Forgejo = nein  
- [x] Stopp-Ursache (SSH Tablet) vs. Produktentscheidung klargestellt  
- [x] Keine Pakete in SKB0 selbst  

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-11 | **SKB0** Erstentscheidung (viele „jetzt nein“ wegen fehlendem SSH) |
| 2026-11-12 | **Nachtrag:** Docker, SearXNG, Playwright, OpenHands, Comfy → **ja** + Mini-IDs SKB2a–e; Forgejo bleibt nein |
