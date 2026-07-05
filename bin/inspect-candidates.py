#!/usr/bin/env python3
import re
from pathlib import Path
import fitz

CANDIDATES = [
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\2026\Rechnung_4154918057.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\datevRückTransport\Rechnung 405295.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\datevRückTransport\Rechnung_4147008879_Anschreiben_ES.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\datevRückTransport\Rechnung_4146160970.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\datevRückTransport\Rechnung RE-545025-2025-05.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\2026\Rechnung_RE0189_23.01.2026.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\datevRückTransport\1021131889-0_coeo.pdf",
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege\datevRückTransport\s254657784011.pdf",
]

AMOUNT = re.compile(r"6[,.]32")

for path_str in CANDIDATES:
    p = Path(path_str)
    if not p.exists():
        print(f"MISSING {p.name}")
        continue
    doc = fitz.open(p)
    text = "\n".join(page.get_text() or "" for page in doc)
    has_amt = bool(AMOUNT.search(text))
    low = text.lower()
    tags = []
    for k in ("paypal", "inkasso", "dhl", "internetmarke", "deutsche post", "pair", "troy", "coeo"):
        if k in low:
            tags.append(k)
    print(f"\n=== {p.name} | 6.32={has_amt} | {','.join(tags)} ===")
    if has_amt or "inkasso" in tags:
        for line in text.splitlines():
            l = line.strip()
            if not l:
                continue
            if any(x in l.lower() for x in ("paypal", "inkasso", "6,32", "6.32", "dhl", "internet", "post", "pair", "troy", "coeo", "offen", "forderung")):
                print(l[:200])
