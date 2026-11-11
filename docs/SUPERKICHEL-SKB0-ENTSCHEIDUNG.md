# Super-Kichel — Install-Entscheidung (SKB0)

> **Entscheidungstabelle, keine Installation.** Grundlage: [`SUPERKICHEL-INVENTAR-VORLAEUFIG.md`](SUPERKICHEL-INVENTAR-VORLAEUFIG.md) (SKA0-12v).  
> Live-Inventar **SKA0-1…12** kann Einzelentscheidungen nachziehen — dann SKB0-Nachtrag, nicht stillschweigend installieren.  
> Plan: [`SUPERKICHEL-GEX-PLAN.md`](SUPERKICHEL-GEX-PLAN.md)

Stand: **2026-11-11**

---

## 1. Verbindliche Entscheidungen

| Komponente | Entscheidung | Nächste ID | Begründung |
|------------|--------------|------------|------------|
| **Ollama** (bereits da) | **Behalten · härten · nutzen** — **nicht** neu installieren | **SKB1** (localhost + UFW) → später **SKC0** | Läuft `0.40.16` mit brauchbaren Modellen; öffentlich = Risiko |
| **Docker** | **Jetzt nein** | nur bei neuer Freigabe vor SearXNG/OpenHands | API außen zu; Nutzen erst wenn Container-Stack gewählt |
| **SearXNG** | **Jetzt nein** (Zielbild optional) | eigene ID nach Produktbedarf | Super-Kichel braucht das nicht für MVP (PHP+Ollama-Formulierung) |
| **Playwright** | **Nicht auf GEX für Super-Kichel** | Akademie/CI lokal oder später | kein Netzdienst-Nachweis; kein Blocker CRM-Hilfe |
| **OpenHands** | **Nein** (bewusst) | — | Cloud-Agent + GitHub reichen; Server-Agent unnötig komplex |
| **Forgejo** | **Nein** (bewusst) | — | Produkt-Repo bleibt GitHub |
| **ComfyUI** | **Jetzt nein** für Super-Kichel | optional später Disk-Layout | Bild/Video-Nebenlinie, nicht Kichel-Phase-2 |
| **GPU-Treiber** | **Nur prüfen** (SKA0-8), Install nur wenn Ollama/GPU sonst unbrauchbar | SKA0-8 → ggf. eigene Freigabe | Hardware da; kein Blind-Install |

**PHP-Regel unverändert:** Ollama formuliert nur; Beträge/Steuer = PHP ([`KICHEL-PHASE2.md`](KICHEL-PHASE2.md)).

---

## 2. Reihenfolge nach SKB0

1. **SSH-Secret fix** (`Hetzner_Private_Key` = Private Key)  
2. **SKA0-1…** Live-Inventar (kann SKB0-Zeilen bestätigen)  
3. **SKB1** Ollama binden (localhost) + UFW — **erste Server-Änderung**, eigene Freigabe  
4. **SKC0** Dev `ganz-om.de` → Ollama (nach Härten)  
5. **SKB2-*** nur wenn diese Tabelle später auf „ja“ geändert wird  

**Kein SKB2** für OpenHands/Forgejo/SearXNG/Docker/Comfy ohne neue ausdrückliche Freigabe.

---

## 3. Abnahme SKB0

- [x] Entscheidungstabelle dokumentiert  
- [x] „Jetzt nein“ vs. „härten/nutzen“ klar  
- [x] Keine Pakete installiert in dieser ID  

---

## Änderungshistorie

| Datum | Inhalt |
|-------|--------|
| 2026-11-11 | **SKB0** Erstentscheidung auf Basis SKA0-12v |
