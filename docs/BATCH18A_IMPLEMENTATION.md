# Batch 18A - Week 8 Reference Package Ingestion

Batch 18A ingests and validates the authoritative Week 8 reference package. It does not implement Week 8 runtime economics.

## Package Tree

```text
halden-week8-data-package/
    MANIFEST.md
    halden_week8.xlsx
    halden_week8_analysis.ipynb
    data/
        baseline_state.csv
        compliance_history.csv
        opec_scenarios.csv
        propagation_coefficients.csv
        worked_example_prior.csv
    faculty/
        halden_week8_FACULTY_SOLUTION.xlsx
    fixtures/
        provenance.json
        week8_golden.json
```

## Validation Boundary

`scripts/validate_week8_package.py` validates:

- package structure;
- provenance hashes;
- CSV schemas, row counts, missing values, and duplicate rows;
- golden fixture math;
- student notebook execution;
- student workbook structure;
- faculty solution workbook structure.

The script intentionally validates package readiness only. It does not create a Laravel economic engine.

## Laravel Content Package Registration

`Week8ContentPackageManifest` now reflects the authoritative package:

- status: `authoritative_package_available`;
- actual CSV artifact names;
- provenance-backed expected hashes;
- 11 package artifacts.

Week 8 package registration now validates against real artifacts and can activate through the generic content package activation path.

## Workbook And Notebook

Student workbook:

- sheets: `README`, `Data - Scenarios & Propagation`, `Data - Compliance`, `Worked Example`, `Your Analysis`;
- no hidden sheets;
- no external links;
- current-week probability cells are blank.

Faculty solution workbook:

- same sheets;
- no hidden sheets;
- no external links;
- current-week probability cells use sim weights `0.35`, `0.40`, `0.25`;
- cached values match the golden fixture where applicable.

Notebook:

- executes with package-relative CSV paths;
- computes expected WTI `80.70`;
- computes expected upstream impact `+6.70/bbl`;
- computes expected crack `19.16/bbl`;
- leaves student analysis as TODO.

## Readiness Result

READY WITH NON-BLOCKING QUESTIONS.

The authoritative package is present, internally consistent, and validates. The remaining caveats are downstream/non-blocking:

- native Excel recalculation was not performed in this environment;
- Week 6 -> Week 8 cohort response parameters remain unresolved and separate;
- runtime economics are deferred to Batch 18B.

## Verification

Run in Batch 18A:

```text
python scripts/validate_week8_package.py
```

Additional Laravel/tooling verification is recorded in the final Batch 18A report.
