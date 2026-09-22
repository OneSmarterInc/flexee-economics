# Halden Week 4 Data Package

This normalized package repairs the supplied Week 4 materials into a self-contained package root.

Run notebooks from this package root or from their own `student/` / `faculty/` directories. Both notebooks resolve `data/` by relative path and do not use machine-specific absolute paths.

Install the minimal Python dependency with:

```bash
pip install -r requirements.txt
```

Then run:

```bash
python ../scripts/validate_week4_package.py
```
