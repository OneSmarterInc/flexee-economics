# Batch 17B - Week 8 Reference Package Discovery

Batch 17B searched for an authoritative Week 8 reference package and prepared the content-ingestion boundary. It does not implement Week 8 economics.

## Discovery Result

Authoritative Week 8 specifications exist in the source folder:

- `C:\Users\sakas\Documents\Flexee-economics\halden-week8-data-package-spec.md`
- `C:\Users\sakas\Documents\Flexee-economics\halden-7wk-week8-spec.md`

No complete Week 8 reference package was found. The source folder does not currently include:

- `halden-week8-data-package/`
- `MANIFEST.md`
- canonical Week 8 CSVs
- student workbook
- student notebook
- faculty solution workbook
- `fixtures/week8_golden.json`
- `fixtures/provenance.json`

Because the package is absent, Batch 17B stops at readiness. No OPEC scenario engine, propagation calculations, student UI, faculty what-if, KPI, ranking, or consequence logic was implemented.

## Expected Package Shape

`Week8ContentPackageManifest` defines the expected package contract:

```text
halden-week8-data-package/
    MANIFEST.md
    halden_week8.xlsx
    halden_week8_analysis.ipynb
    data/
        opec_compliance_history.csv
        current_cut_characteristics.csv
        price_propagation_reference.csv
        segment_position.csv
        hedge_book_balance_sheet.csv
        worked_example_prior.csv
    faculty/
        halden_week8_FACULTY_SOLUTION.xlsx
    fixtures/
        week8_golden.json
        provenance.json
```

The manifest records the package type as `week8_opec_shock`.

## Validation Behavior

`Week8ContentPackageRegistrationService` can register a Week 8 package attempt against the generic content package system. With the current repository state, validation records all artifacts as missing and marks the package invalid.

Invalid Week 8 packages cannot activate.

## Readiness Status

Ready:

- Week 8 specification has been found.
- Expected package structure is encoded.
- Missing package state is explicit and tested.
- Runtime activation is blocked until a valid package exists.

Blocked:

- canonical CSVs;
- workbook/notebook parity;
- faculty solution;
- golden fixtures;
- provenance hashes.

## Deferred

Still deferred until the authoritative Week 8 package arrives:

- OPEC scenario economics;
- propagation engine;
- probability-weighted segment impacts;
- Week 8 student/faculty runtime integration;
- Week 8 what-if;
- KPI/ranking effects;
- Week 10 cash-position consequences;
- LLM interpretation.
