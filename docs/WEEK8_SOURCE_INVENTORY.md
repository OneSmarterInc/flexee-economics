# Week 8 Source Inventory

Batch 18A ingested the authoritative Week 8 reference package:

`halden-week8-data-package/`

The original source zip remains outside the repository at:

`C:\Users\sakas\Documents\Flexee-economics\halden-week8-data-package.zip`

## Package Files

| Path                                                                   | Type     | Purpose                                                | Authority                      | Hash                                                               | Visibility | Economic validation | Notes                                             |
| ---------------------------------------------------------------------- | -------- | ------------------------------------------------------ | ------------------------------ | ------------------------------------------------------------------ | ---------- | ------------------- | ------------------------------------------------- |
| `halden-week8-data-package/MANIFEST.md`                                | Manifest | Package contents, validation summary, source hierarchy | Authoritative package          | `5FB699E475F0B4D22D129C2EF7330947AEF35C0681CC4733A9C25FB3C6CFF681` | Shared     | Yes                 | Week 8-specific OPEC package manifest             |
| `halden-week8-data-package/data/baseline_state.csv`                    | CSV      | Pre-shock WTI, Brent, crack, pump base                 | Canonical data                 | `4FAFE209ACABE71766CF09627DCBDCCE4F5C3159255004B9E7BDED149C5BCE04` | Shared     | Yes                 | Baseline state                                    |
| `halden-week8-data-package/data/compliance_history.csv`                | CSV      | OPEC compliance episodes for probability reasoning     | Canonical data                 | `6ADF5EDA7595C26870FBF1934ED6E3BE5E9EC33046FFBE1CBF0BECBC3162B453` | Shared     | Yes                 | Student probability-estimation basis              |
| `halden-week8-data-package/data/opec_scenarios.csv`                    | CSV      | Scenario probabilities, resolved WTI, delta WTI        | Canonical data                 | `2619E5DF08EB007A92B0DB1E04C9F0E4D3ACF40A3C3D41366E3958B7BBA9BB0D` | Shared     | Yes                 | Golden scenario oracle                            |
| `halden-week8-data-package/data/propagation_coefficients.csv`          | CSV      | OPEC shock propagation coefficients                    | Canonical data                 | `9BD43603B6A9669E3DCDF689F035587B03B274A43B2ED53FC4C505D0B60A26D3` | Shared     | Yes                 | Not the Week 6 -> Week 8 cohort response function |
| `halden-week8-data-package/data/worked_example_prior.csv`              | CSV      | Prior shock worked example                             | Canonical data                 | `7B02B08D41648C03479A5747D64BBD02A949BBAB737AD018E668C6A452F0B749` | Shared     | Yes                 | Worked-example input                              |
| `halden-week8-data-package/halden_week8.xlsx`                          | Workbook | Student workbook                                       | Authoritative student artifact | `FE5341E4F074B42A91D3B28ADBF92E45CDD1B83DA95E4F1BE04CFA5354E5824E` | Student    | Yes                 | Current-week probability inputs blank             |
| `halden-week8-data-package/halden_week8_analysis.ipynb`                | Notebook | Student notebook                                       | Authoritative student artifact | `701E5AB0BF9FCF7DF2376B303B6E55D4AB3C9CE65863B62B91B6E03698302B2D` | Student    | Yes                 | Executes against canonical CSV paths              |
| `halden-week8-data-package/faculty/halden_week8_FACULTY_SOLUTION.xlsx` | Workbook | Faculty solution workbook                              | Authoritative faculty artifact | `DA834FF85F3436A55601A195CA6294F14008955501D8EC3E39400294CCA2B7BF` | Solution   | Yes                 | Contains sim weights and solved current-week view |
| `halden-week8-data-package/fixtures/week8_golden.json`                 | JSON     | Golden expected outputs                                | Authoritative oracle           | `838BA5B630F1E5B8E859D9339C31217EA20B9D220767D87FB35785521AA46CC7` | Solution   | Yes                 | Regression oracle for Batch 18B                   |
| `halden-week8-data-package/fixtures/provenance.json`                   | JSON     | Artifact hashes and package version                    | Authoritative provenance       | `D86FDC9F69E07037854265D5C6C03F939B8C00FED484AF90B80CE67E63D1B52A` | Solution   | Yes                 | Does not include its own hash                     |

## CSV Inventory

| CSV                            | Rows | Columns                                                       | Data types                            | Units                | Missing values | Duplicates | Consumed by                           |
| ------------------------------ | ---: | ------------------------------------------------------------- | ------------------------------------- | -------------------- | -------------- | ---------- | ------------------------------------- |
| `baseline_state.csv`           |    4 | `parameter,value,unit,note`                                   | text, decimal, text, text             | `usd_bbl`, `usd_gal` | None           | None       | workbook, notebook, golden validation |
| `compliance_history.csv`       |    5 | `episode,announced_cut_mbd,delivered_mbd,compliance_pct,note` | text, decimal, decimal, decimal, text | `mb/d`, fraction     | None           | None       | student probability reasoning         |
| `opec_scenarios.csv`           |    3 | `scenario,probability,wti_resolved,delta_wti`                 | text, decimal, decimal, decimal       | fraction, `usd_bbl`  | None           | None       | scenario reconciliation               |
| `propagation_coefficients.csv` |    4 | `channel,coefficient,unit,note`                               | text, decimal, text, text             | coefficient-specific | None           | None       | OPEC shock propagation                |
| `worked_example_prior.csv`     |    3 | `scenario,probability,delta_wti`                              | text, decimal, decimal                | fraction, `usd_bbl`  | None           | None       | worked example                        |

## Authority Notes

- The canonical CSVs are the package source of truth.
- The workbook and notebook consume the canonical CSV values.
- `fixtures/week8_golden.json` is the regression oracle for future Laravel economics.
- `propagation_coefficients.csv` describes Week 8 OPEC shock propagation only. The Week 6 aggregate Gulf Coast capacity additions -> Week 8 refining margin response is supplied separately by `halden-window2-cohort-addendum/`.
