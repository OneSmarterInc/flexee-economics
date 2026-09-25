#!/usr/bin/env python3
"""Validate the authoritative multi-week package batch without running economics."""

from __future__ import annotations

import csv
import hashlib
import json
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
APPROVED_WEEKS = [1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13]
EXCLUDED_WEEKS: list[int] = []
REL_TOL = 1e-3
ABS_TOL = 1e-5


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def load_json(path: Path) -> dict:
    with path.open("r", encoding="utf-8") as handle:
        return json.load(handle)


def validate_csv(path: Path) -> None:
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.reader(handle)
        rows = list(reader)

    if not rows:
        raise AssertionError(f"{path} is empty")

    header = rows[0]
    if not header or any(column == "" for column in header):
        raise AssertionError(f"{path} has an invalid header")

    for index, row in enumerate(rows[1:], start=2):
        if len(row) != len(header):
            raise AssertionError(f"{path} row {index} has {len(row)} cells, expected {len(header)}")


def validate_week(week: int) -> tuple[int, int]:
    package = ROOT / f"halden-week{week}-data-package"
    if not package.is_dir():
        raise AssertionError(f"Week {week} package directory missing")

    required = [
        package / "MANIFEST.md",
        package / f"halden_week{week}.xlsx",
        package / f"halden_week{week}_analysis.ipynb",
        package / "fixtures" / "provenance.json",
        package / "fixtures" / f"week{week}_golden.json",
    ]

    if week not in [6, 8]:
        required.append(package / "VALIDATION_16A.md")
        required.append(package / "faculty" / f"halden_week{week}_FACULTY_SOLUTION.ipynb")

    required.append(package / "faculty" / f"halden_week{week}_FACULTY_SOLUTION.xlsx")

    for path in required:
        if not path.is_file():
            raise AssertionError(f"Required artifact missing: {path.relative_to(ROOT)}")

    provenance = load_json(package / "fixtures" / "provenance.json")
    artifacts = provenance.get("artifacts")
    if not isinstance(artifacts, dict) or not artifacts:
        raise AssertionError(f"Week {week} provenance has no artifacts")

    for relative_path, expected_hash in artifacts.items():
        path = package / relative_path
        if not path.is_file():
            raise AssertionError(f"Week {week} provenance artifact missing: {relative_path}")
        actual_hash = sha256(path)
        if actual_hash.lower() != str(expected_hash).lower():
            raise AssertionError(f"Week {week} hash mismatch for {relative_path}")

    for csv_path in sorted((package / "data").glob("*.csv")):
        validate_csv(csv_path)

    notebook = load_json(package / f"halden_week{week}_analysis.ipynb")
    if "cells" not in notebook:
        raise AssertionError(f"Week {week} student notebook is not a notebook JSON document")

    golden = load_json(package / "fixtures" / f"week{week}_golden.json")
    tolerance = golden.get("meta", {}).get("tolerance", {})
    if week not in [6, 8] and (tolerance.get("rel") != REL_TOL or tolerance.get("abs") != ABS_TOL):
        raise AssertionError(f"Week {week} golden tolerance is not rel={REL_TOL} abs={ABS_TOL}")

    assertions = golden.get("ordering_assertions", [])
    if not assertions:
        raise AssertionError(f"Week {week} golden fixture has no ordering assertions")

    failed_assertions = [item for item in assertions if item.get("holds") is not True]
    if failed_assertions:
        raise AssertionError(f"Week {week} golden fixture has failed assertions: {failed_assertions}")

    return len(artifacts), len(list((package / "data").glob("*.csv")))


def main() -> None:
    total_artifacts = 0
    total_csvs = 0

    for week in APPROVED_WEEKS:
        artifacts, csvs = validate_week(week)
        total_artifacts += artifacts
        total_csvs += csvs
        print(f"Week {week}: ok ({artifacts} provenance artifacts, {csvs} csvs)")

    for week in EXCLUDED_WEEKS:
        if (ROOT / f"halden-week{week}-data-package").exists():
            raise AssertionError(f"Week {week} package must remain excluded from repo ingestion")

    print(f"Validated {len(APPROVED_WEEKS)} packages, {total_artifacts} artifacts, {total_csvs} csvs.")


if __name__ == "__main__":
    main()
