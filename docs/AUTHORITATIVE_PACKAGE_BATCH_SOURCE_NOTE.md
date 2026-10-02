# Halden — Authoritative Package Batch (Batch 16A ingestion sources)

Sakshi,

This bundle is the complete, current handoff set. It holds twelve authoritative week packages plus every current spec and reference document. Each passed the Batch 16A gate: canonical CSVs, student workbook
and notebook, faculty solution workbook (answers are live Excel formulas on the canonical data), faculty
solution notebook (recomputes every answer and asserts it against the golden fixtures), golden
fixtures with ordering assertions, SHA-256 provenance, MANIFEST, and a VALIDATION_16A.md gate report.

## What is in the bundle

| Week | Package                    | 16A status | Notes                                                         |
| ---- | -------------------------- | ---------- | ------------------------------------------------------------- |
| 1    | halden-week1-data-package  | PASS       | Asset register; new book_values.csv (see MANIFEST)            |
| 2    | halden-week2-data-package  | PASS       | 156-week price/volume series stored as data (seed 42)         |
| 3    | halden-week3-data-package  | PASS       | Flag: window-1 response recorded as symmetric                 |
| 5    | halden-week5-data-package  | PASS       | Entity currency flows introduced                              |
| 6    | halden-week6-data-package  | PASS       | Built earlier; NPV/IRR crossover fixtures                     |
| 7    | halden-week7-data-package  | PASS       | Flag: no cluster justifies matching under ledger elasticities |
| 8    | halden-week8-data-package  | PASS       | Built earlier; propagation coefficients                       |
| 9    | halden-week9-data-package  | PASS       | Flag: fills-per-site is a calibration lever                   |
| 10   | halden-week10-data-package | PASS       | Upgraded to the 16A standard; binding rules encoded as data   |
| 11   | halden-week11-data-package | PASS       | Kessana hold-up                                               |
| 12   | halden-week12-data-package | PASS       | Design revised (see below); interlock now gate-verified       |
| 13   | halden-week13-data-package | PASS       | Factor markets                                                |

## Week 12 revision, and what is not in the bundle

Week 12 was initially BLOCKED by the gate: Helix Rotterdam ($1,200M) sat in a bucket with a $900M ceiling, and the
$320M divestment unlocked nothing. The design was revised: the adjacent-transition ceiling is now $1,200M and
divestment proceeds are $550M. The rebuilt package passes. Helix Rotterdam fills the envelope alone, and Helix
Rotterdam plus offshore wind ($1,750M) is affordable only with the divestment. The Week 12 spec, the 7-week Week 12
spec, and the constants ledger were updated to match; replace any earlier copies you hold.

Week 14 has no computational data package by design (board defense and assessment).

Week 4 is already ingested in the stable baseline (7157bc6). Its original reference package is included unchanged
so the set is complete; do not re-ingest it.

Week 10 replaces the earlier reference build. Its convergence read depends on each team's prior-week state, which the
platform supplies at runtime. The package encodes the five binding rules as data (binding_rules.csv) and pins golden
outputs for two reference teams: disciplined (0 constraints bind) and constrained (all 5 bind).

## Ground rules

- Use the golden fixtures as regression targets with the stated tolerance (rel 1e-3, abs 1e-5).
  Never compare with exact string matching.
- Each MANIFEST lists "new week-specific parameters" introduced by the package. These are not yet in
  the constants ledger. Treat them as authoritative for ingestion; they will be added to the ledger.
- Each MANIFEST lists flags. None of the flags in this bundle block ingestion.
- All figures remain provisional design-draft calibration pending the market-data refresh.

## Everything else in this bundle

HANDOFF-README.md (start here), development instructions, constants ledger (current, including the Week 12 revision),
calibration review, role charters, faculty adoption and teaching guides, student guide, all fourteen main-arc specs,
all seven 7-week variant specs, and the design background documents. Any copy you received earlier is superseded.
