#!/usr/bin/env python3
"""Kompatibilitäts-Wrapper für SKR03."""
import subprocess
import sys
from pathlib import Path

raise SystemExit(
    subprocess.call([sys.executable, str(Path(__file__).with_name("parse-datev-skr.py")), "skr03", *sys.argv[1:]])
)
