#!/usr/bin/env python3
from __future__ import annotations

import re
import sys
import time
import datetime
from pathlib import Path

import fitz

BELEGE = Path(
    r"C:\Users\dietr\OneDrive\Desktop\Arbeit\Benzler\IQ-Strom-Cloud\Buchhaltung\Belege"
)
DAYS = 400
NAME_PAT = re.compile(
    r"paypal|inkasso|dhl|internetmarke|internet-marke|pair|troy|coeo|porto|post",
    re.I,
)
AMOUNT_PAT = re.compile(r"6[,.]32")
AMOUNT_PAT_BYTES = re.compile(rb"6[,.]32")


def iter_pdfs(root: Path):
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


def scan() -> None:
    cutoff = time.time() - 60 * 60 * 24 * DAYS
    hits = []

    for p in iter_pdfs(BELEGE):
        try:
            if p.stat().st_mtime < cutoff:
                continue
        except OSError:
            continue

        score = 0
        reasons: list[str] = []
        if NAME_PAT.search(p.name):
            score += 2
            reasons.append("name")

        try:
            data = p.read_bytes()
            low = data.lower()
            if b"paypal" in low:
                score += 1
                reasons.append("bytes-paypal")
            if b"inkasso" in low:
                score += 1
                reasons.append("bytes-inkasso")
            if AMOUNT_PAT_BYTES.search(data):
                score += 5
                reasons.append("bytes-6.32")
            if any(x in low for x in (b"dhl", b"internetmarke", b"deutsche post")):
                score += 1
                reasons.append("bytes-post")
        except OSError:
            pass

        try:
            doc = fitz.open(p)
            title = (doc.metadata.get("title") or "").encode("ascii", "replace").decode()
            text = "".join((page.get_text() or "") for page in doc)
            blob = f"{title} {text} {p.name}".lower()
            if "paypal" in blob:
                score += 2
                reasons.append("text-paypal")
            if "inkasso" in blob:
                score += 2
                reasons.append("text-inkasso")
            if any(
                x in blob
                for x in ("dhl", "internetmarke", "deutsche post", "internet-marke")
            ):
                score += 2
                reasons.append("text-post")
            if AMOUNT_PAT.search(blob):
                score += 5
                reasons.append("text-6.32")
            meta_title = title
        except Exception:
            meta_title = ""

        if score >= 2:
            hits.append((score, p.stat().st_mtime, str(p), reasons, meta_title[:100]))

    hits.sort(reverse=True)
    print(f"HITS {len(hits)} in {BELEGE} (last {DAYS} days)")
    for score, mtime, path, reasons, title in hits[:80]:
        dt = datetime.datetime.fromtimestamp(mtime).strftime("%Y-%m-%d")
        print(f"{score:2d} | {dt} | {Path(path).name}")
        print(f"    {','.join(reasons)}")
        print(f"    {path}")
        if title.strip():
            print(f"    title: {title}")


if __name__ == "__main__":
    scan()
