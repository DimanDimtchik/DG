#!/usr/bin/env python3
"""Find PDFs with PayPal + 6,32 + (DHL|internetmarke|Inkasso)."""
from __future__ import annotations

import re
from pathlib import Path

import fitz

ROOTS = [
    Path(r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege"),
    Path(r"C:\Users\dietr\Downloads"),
    Path(r"C:\Users\dietr\OneDrive\Desktop"),
]
AMOUNT = re.compile(r"6[,.]32")
KEYWORDS = ("paypal", "inkasso", "dhl", "internetmarke", "internet-marke", "deutsche post", "portokasse")


def iter_pdfs(root: Path):
    if not root.exists():
        return
    stack = [root]
    while stack:
        current = stack.pop()
        try:
            for entry in current.iterdir():
                try:
                    if entry.is_dir():
                        stack.append(entry)
                    elif entry.suffix.lower() == ".pdf":
                        yield entry
                except OSError:
                    pass
        except OSError:
            pass


def text_of(p: Path) -> str:
  try:
    doc = fitz.open(p)
    return " ".join(page.get_text() or "" for page in doc).lower()
  except Exception:
    return ""


def main() -> None:
    hits = []
    for root in ROOTS:
        for p in iter_pdfs(root):
            blob = (p.name + " " + text_of(p)).lower()
            if not AMOUNT.search(blob):
                continue
            kw = [k for k in KEYWORDS if k in blob]
            if "paypal" not in blob and "inkasso" not in blob:
                continue
            hits.append((len(kw), p, kw))

    hits.sort(key=lambda x: (-x[0], str(x[1])))
    print(f"MATCHES {len(hits)}")
    for score, p, kw in hits[:40]:
        print(f"{score} | {','.join(kw)} | {p}")


if __name__ == "__main__":
    main()
