"""Writes MANIFEST.md: a SHA-256 for every file in the package."""
import hashlib
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
files = sorted(p for p in ROOT.rglob("*") if p.is_file() and p.name != "MANIFEST.md" and "__pycache__" not in p.parts)
lines = ["# MANIFEST · halden-operating-model v0.1", "", "| File | SHA-256 |", "| --- | --- |"]
for p in files:
    lines.append(f"| `{p.relative_to(ROOT)}` | `{hashlib.sha256(p.read_bytes()).hexdigest()}` |")
(ROOT / "MANIFEST.md").write_text("\n".join(lines) + "\n")
