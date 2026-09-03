# Arbeitsvertrag-Vorlagen — Handoff und Anforderungen

Stand: 2026-08-27

Diese Notiz fasst die Anforderungen aus dem Muster-Arbeitsvertrag fuer Kontur
Cosmetics zusammen, damit spaeter im DG-CRM Vertragsvorlagen erstellt werden
koennen. Personenbezogene Daten aus konkreten Arbeitsvertraegen sollen nicht in
die Repo-Historie committed werden.

## Ziel

Im CRM sollen spaeter wiederverwendbare Vorlagen fuer Arbeitsvertraege entstehen:

- geringfuegige Beschaeftigung / Minijob
- minderjaehrige Arbeitnehmerinnen und Arbeitnehmer
- Teilzeitbeschaeftigung
- Vollzeitbeschaeftigung
- Anlagen zu Unterweisung, Hygiene, Datenschutz, Rentenversicherung und ggf.
  Elternzustimmung

## Keine personenbezogenen Vertragsdaten im Repo

Der konkrete Vertrag aus der Session wurde als Arbeitsdatei unter
`/workspace/artifacts/arbeitsvertrag.*` erzeugt. Diese Dateien enthalten
personenbezogene Daten und sollen nicht committed werden.

Fuer CRM-Vorlagen stattdessen Platzhalter verwenden, z. B.:

- Arbeitgeber/in, Anschrift, Vertretung
- Arbeitnehmer/in, Geburtsdatum, Anschrift
- gesetzliche Vertreter, falls erforderlich
- Beginn, Probezeit, Taetigkeit, Arbeitsort
- Wochen-/Monatsarbeitszeit
- Stundenlohn, Zahlungsfaelligkeit, Urlaub
- Ansprechpartner/in fuer Krankheit, Unterweisung, Beschwerden und Notfaelle

## Rechtliche Bausteine

Je nach Vertragstyp sollten diese Regelungsbereiche als modulare Bausteine
abgebildet werden:

- `NachwG`: wesentliche Vertragsbedingungen schriftlich bzw. nachweisbar
- `MiLoG`: Mindestlohn, Arbeitszeitaufzeichnung, keine unbezahlte Probearbeit
- `SGB IV § 8`: Geringfuegigkeitsgrenze fuer Minijobs
- `SGB VI § 6 Abs. 1b`: Befreiung von der Rentenversicherungspflicht im Minijob
- `JArbSchG`: Minderjaehrigenschutz, Arbeitszeiten, Pausen, verbotene Arbeiten,
  aerztliche Untersuchung, Elternzustimmung
- `ArbZG`: Arbeitszeit, Ruhezeit, Sonn-/Feiertage fuer Volljaehrige
- `BUrlG` und `JArbSchG § 19`: Urlaub nach Arbeitstagen und Alter
- `EFZG`: Krankheit, Arbeitsunfaehigkeit, Entgeltfortzahlung
- `IfSG §§ 42, 43`: nur bei tatsaechlichem Lebensmittelkontakt
- `BioStoffV`, Arbeitsschutz, Gefaehrdungsbeurteilung
- Datenschutz, Verschwiegenheit, Fotos, Videos, Social Media

## Minijob-Werte

Vorlagen sollten Werte datumsabhaengig pflegen und nicht hart verdrahten:

| Jahr | Mindestlohn | Geringfuegigkeitsgrenze |
| ---- | ----------- | ----------------------- |
| 2026 | 13,90 EUR/St. | 603 EUR/Monat im Jahresdurchschnitt |
| 2027 | 14,60 EUR/St. | 633 EUR/Monat im Jahresdurchschnitt |
| 2028 | amtlich bekannt zu machen | dynamisch nach `SGB IV § 8 Abs. 1a` |

Bei hoeherem Stundenlohn sinkt die zulaessige Stundenanzahl entsprechend.

## Kosmetikstudio-spezifische Taetigkeitsbeschreibung

Fuer einfache Hilfstaetigkeiten im Kosmetikstudio:

- Empfang, Terminorganisation, einfache Kundenbetreuung
- Vorbereitung und Nachbereitung von Arbeitsplaetzen
- Waesche-, Handtuch-, Material- und Lagerpflege
- Aufraeum-, Reinigungs- und Hygienetaetigkeiten
- einfache Hilfstaetigkeiten im Nagel-/Wellnessbereich nur nach Einweisung,
  unter Aufsicht und ohne erlaubnis-/ausbildungspflichtige Behandlung

Auszuschliessen bzw. nur nach gesonderter Einweisung/Freigabe:

- medizinische, therapeutische oder invasive Behandlungen
- Diagnosen, Heilversprechen, Behandlung unklarer Hautveraenderungen
- Blutkontakt, offene Hautstellen, infektiöses Material
- Nadeln, Klingen, scharfe Instrumente
- eigenstaendiger Umgang mit Gefahrstoffen, aggressiven Loesungsmitteln,
  Primern, Saeuren, Acryl-/Gel-Daempfen, Staub oder Desinfektionskonzentraten
- Alleinarbeit, wenn minderjaehrig oder betrieblich nicht freigegeben

## Kurzschulung als Vertragsanlage

Aus den Lebensmittel-/Gesundheitsamt-Unterlagen wurde ein verkürzter,
kosmetikstudio-tauglicher Schulungsanhang abgeleitet. Dieser soll nicht nur eine
Dateiliste sein, sondern die Schulungsinhalte direkt enthalten.

Empfohlene Struktur:

1. Persoenliche Hygiene
   - saubere Arbeitskleidung, Haende, Arbeitsplatz
   - Haendewaschen/-desinfektion vor Arbeitsbeginn, nach Toilette, Husten,
     Niesen, Naseputzen, Abfall, Reinigung und Taetigkeitswechsel
2. Wunden, Hautveraenderungen und Krankheit
   - offene, naessende, entzuendete oder infektionsverdaechtige Stellen sofort
     melden
   - Einsatz am Kunden nur nach Freigabe und mit geeignetem Schutz
3. Arbeitsplatz- und Betriebshygiene
   - Behandlungsplatz, Liegen, Arbeitsflaechen, Geraete, Handtuecher, Waesche,
     Abfall sauber und getrennt halten
4. Reinigung und Desinfektion
   - nur nach Einweisung, Dosierung und Einwirkzeit beachten, Mittel nicht
     mischen, Lueftung und Hautschutz beachten
5. Grenzen der Taetigkeit
   - nur einfache Hilfstaetigkeiten; keine medizinischen/therapeutischen/
     invasiven Behandlungen
6. Kundenschutz und Verhalten
   - Arbeit bei Infektionsrisiko, Blutkontakt, Belästigung oder Ueberforderung
     unterbrechen und Ansprechpartner/in informieren
7. Datenschutz und Social Media
   - Kundendaten, Fotos, Videos und betriebliche Informationen vertraulich
8. Lebensmittel-/IfSG-Regeln nur bei tatsaechlichem Lebensmittelkontakt
   - `IfSG §§ 42, 43`, Erstbelehrung, Folgebelehrung alle zwei Jahre
   - Hygieneschulung mindestens jaehrlich
   - `LMHV § 4` nur bei leicht verderblichen Lebensmitteln

Der Anhang braucht Unterschriftenfelder fuer Arbeitgeber/in bzw. Unterweisende,
Arbeitnehmer/in und optional Eltern/gesetzliche Vertreter.

## Schulungsquellen und Pfade

Die folgenden Dateien wurden in der Session als Grundlage fuer die
Kurzschulung herangezogen. Sie sollen nicht automatisch committed werden,
sondern als Quellen-/Ablagehinweis fuer spaetere Vorlagen und Schulungsanlagen
dienen.

Lokale Ausgangsordner laut Anwender:

- `C:\Users\dietr\OneDrive\Desktop\Arbeit\MaxGrill\GbR\Lebensmittel-Ueberwachung\Gesundheitsamt\`
- `C:\Users\dietr\OneDrive\Desktop\Arbeit\MaxGrill\GbR\Lebensmittel-Ueberwachung\Gesundheitsamt2\`
- `C:\Users\dietr\Downloads\`

In der Cloud-Session hochgeladene Dateien:

- `/home/ubuntu/.cursor/projects/workspace/uploads/Anmeldebest_tigung-Quittung_02fe.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/cert__1__0abf.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/cert_5841.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/rkn2.de_merkblatt_45e6.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Anleitung-Haendewaschen_a6d4.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Betriebshygiene_7ed7.docx`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Fliessdiagramm-GastroImbiss-Gefahren_c737.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Lagerung-Leitfaden_258c.docx`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Leitfaden-Vo-EG-852-2004-1_fb68.pdf`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Personalhygiene_3710.docx`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Produktionshygiene_2ee3.docx`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Schulungen_d639.docx`
- `/home/ubuntu/.cursor/projects/workspace/uploads/Schulungsprotokoll_b3c1.pdf`

Vom Anwender auf den Webserver hochgeladenes Video:

- lokaler Dateiname: `belerung.mp4`
- Serverpfad: `www/htdocs/w0217246/kontur-cosmetics.de/schulung/belerung.mp4`
- oeffentliche URL: `https://kontur-cosmetics.de/schulung/belerung.mp4`

Hinweise zur Einordnung:

- `rkn2.de_merkblatt_45e6.pdf`, `cert*.pdf` und
  `Anmeldebest_tigung-Quittung_02fe.pdf` betreffen `IfSG §§ 42, 43` und sind
  nur bei tatsaechlichem Lebensmittelkontakt relevant.
- `Personalhygiene_3710.docx`, `Betriebshygiene_7ed7.docx`,
  `Anleitung-Haendewaschen_a6d4.pdf` und `Schulungen_d639.docx` enthalten
  allgemein nutzbare Hygiene-/Schulungspunkte, die fuer Kosmetik angepasst
  wurden.
- `Lagerung-Leitfaden_258c.docx`, `Produktionshygiene_2ee3.docx`,
  `Fliessdiagramm-GastroImbiss-Gefahren_c737.pdf` und
  `Leitfaden-Vo-EG-852-2004-1_fb68.pdf` sind stark lebensmittel-/gastrobezogen
  und sollten in Kosmetik-Vorlagen nur optional verwendet werden.

## Video-Unterweisung

Fuer Kontur Cosmetics wurde ein oeffentlich erreichbarer Schulungsvideo-Pfad
vorbereitet:

`https://kontur-cosmetics.de/schulung/belerung.mp4`

In Vorlagen sollte das als optionales Feld modelliert werden:

`Schulungsvideo / Online-Link: {{training_video_url}}`

## Rentenversicherungsbefreiung

Minijob-Vorlagen sollten am Ende eine separate Anlage enthalten:

- Antrag auf Befreiung von der Rentenversicherungspflicht nach
  `SGB VI § 6 Abs. 1b`
- Hinweis, dass die Befreiung fuer die Dauer der geringfuegigen Beschaeftigung
  gilt und nicht widerrufen werden kann
- Eingangsstempel/-datum Arbeitgeber/in
- Unterschrift Arbeitnehmer/in
- bei Minderjaehrigkeit zusaetzlich Eltern/gesetzliche Vertreter

## Offene Punkte fuer spaetere CRM-Umsetzung

- Vertragsgenerator mit Platzhaltern statt festen Namen/Adressen
- datumsabhaengige Mindestlohn- und Minijob-Werte
- Vertragstypen als Module: Minijob, Teilzeit, Vollzeit, Minderjaehrige
- optionale Anlagen: Elternzustimmung, aerztliche Untersuchung, IfSG,
  Kurzschulung, Rentenversicherungsbefreiung
- PDF-/DOCX-Ausgabe mit Logo, klickbaren Links und sauberem Seitenumbruch
- keine Speicherung sensibler Gesundheitsunterlagen im Git-Repo
