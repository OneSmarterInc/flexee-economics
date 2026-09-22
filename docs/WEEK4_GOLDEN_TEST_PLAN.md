# Week 4 Golden Test Plan

Inventory date: 2026-09-22.

## Test oracle status

Golden-master testing is blocked because the authoritative Week 4 reference package and specification are missing.

Required oracle sources:

- `halden-week4-data-package/`
- Week 4 package MANIFEST
- Week 4 input CSV/Excel/notebook/Python files
- Week 4 expected-output files
- `halden-week4-data-package-spec.md`
- `halden-constants-ledger.md`

The constants ledger is present and authoritative, but it is not a complete golden oracle by itself.

## Reference input cases

| Case                                 | Source                                      | Status  |
| ------------------------------------ | ------------------------------------------- | ------- |
| Primary Week 4 worked reference case | Missing Week 4 package                      | Blocked |
| Edge/validation cases                | Missing Week 4 spec/package                 | Blocked |
| Ledger conflict case                 | Future comparison of package/spec to ledger | Blocked |

## Expected outputs

No expected-output files were found.

Expected output categories must remain separate:

- deterministic economic outputs
- KPIs
- score inputs
- scores
- ranks
- faculty-only traces

## Comparison strategy

Once sources are available:

1. Ingest package inputs as fixtures without rewriting them.
2. Load expected outputs from the reference package.
3. Compare Laravel deterministic economic outputs to the reference outputs.
4. Compare KPIs, scores, and ranks only if authoritative expected outputs exist for those layers.
5. Fail loudly on missing fixture columns, unknown constants, or ledger/spec/package conflicts.

Never rewrite expected results to match the Laravel implementation.

## Numeric precision and rounding

Blocked until the Week 4 spec/package is available.

Open points:

- currency scale
- percentage/rate scale
- per-barrel scale
- intermediate rounding points
- final output rounding
- permitted tolerance for golden comparisons
- rounding mode

Interim recommendation:

- Use decimal-safe arithmetic for calculations.
- Avoid PHP binary floats for parity-sensitive calculations.
- Treat tolerances as `0` unless the authoritative sources define a tolerance.

## Regression fixtures

Expected fixture layout once package exists:

- raw package inputs
- normalized package inputs
- ledger snapshot/checksum
- expected deterministic economic outputs
- expected KPIs
- expected score inputs, if defined
- expected scores/ranks, if defined
- manifest/checksum file

## Drift failures

The test suite should fail when:

- any source fixture changes without a documented source-version change
- ledger values used by Week 4 differ from the ledger snapshot
- package/spec values conflict with the ledger
- calculated outputs differ from expected outputs beyond authorized precision
- rounding behavior changes
- score/rank results are recomputed from the wrong layer
