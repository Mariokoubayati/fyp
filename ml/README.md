# UniCircle ML service

Python service for the AI trust model. Later it will also serve the recommender.

## How it fits

```
Mobile app ──► Laravel API ──► rule-based trust score (explainable, stored, shown to students)
                    │
                    └──► Flask /trust/assess ──► default risk for each loan size the rules allow
                              │
                              └── the tier drops to the largest size the model considers safe
```

- The rules decide first. The model can only **lower** a tier, never raise it.
- If the service is off or down, the rules decide alone (Laravel stops calling it for a minute after a failure).
- Laravel sends the student's raw history. Python computes the features, so there is only one feature implementation.

## Run it

```sh
cd ml
pip install -r requirements.txt
python -m unicircle_ml.service          # http://127.0.0.1:5001
```

Then in `backend/.env`:

```
TRUST_MODEL_ENABLED=true
TRUST_MODEL_URL=http://127.0.0.1:5001
```

Optional shared secret: set `ML_SERVICE_TOKEN` here and the same value as `TRUST_MODEL_TOKEN` in Laravel.

## Retrain and re-evaluate

```sh
python -m unicircle_ml.train
```

This regenerates the synthetic worlds, retrains, and rewrites `models/` and `reports/` (results table in [`reports/RESULTS.md`](reports/RESULTS.md), plots as PNG). Same seeds give the same numbers.

## Tests

```sh
python -m pytest -q
```

## Files

| File | What it does |
|---|---|
| `unicircle_ml/history.py` | The shared history format Laravel sends |
| `unicircle_ml/features.py` | The 19 behavior features, computed only from the past |
| `unicircle_ml/rules.py` | Python port of the backend's rule-based score (the baseline) |
| `unicircle_ml/simulate.py` | Synthetic campus lending world with injected bad actors |
| `unicircle_ml/train.py` | Training, baselines, evaluation, plots |
| `unicircle_ml/service.py` | Flask API |

## Why the synthetic data is not circular

The generator never uses the trust rules. Each simulated student has a hidden reliability and a behavior type (reliable, flaky, newcomer, strategic defaulter, collusion ring). Defaults depend only on those hidden values, the loan size and an exam-season shock that no feature can see. The rules and the model only see the observable history afterwards.

Two extra tests check that the model did not just memorize our generator:

- **Shifted world:** twice the bad actors, worse students, bigger loans, a different exam season.
- **Unseen type:** trained without strategic defaulters, tested on a world that has them.

We also compare against a stronger baseline (rule score plus loan size), so the model gets no credit just for seeing the amount.
