"""Train the AI trust model and compare it with the rule-based baseline.

    python -m unicircle_ml.train

Writes models/trust_model.joblib, models/metadata.json, reports/metrics.json,
reports/RESULTS.md and the plots in reports/.

Task: predict whether a loan request will default, from the borrower's history
before the request. Three test sets:
  in_distribution  a fresh world with the same settings (new seed)
  shifted          twice the bad actors, worse students, bigger loans, another exam season
  unseen_type      the model is trained on a world WITHOUT strategic defaulters,
                   then tested on a world that has them
"""

from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

import joblib
import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402
from sklearn.ensemble import HistGradientBoostingClassifier  # noqa: E402
from sklearn.linear_model import LogisticRegression  # noqa: E402
from sklearn.metrics import brier_score_loss, roc_auc_score, roc_curve  # noqa: E402
from sklearn.pipeline import make_pipeline  # noqa: E402
from sklearn.preprocessing import StandardScaler  # noqa: E402

from .features import FEATURE_NAMES  # noqa: E402
from .simulate import WorldConfig, generate, shifted, without  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
MODELS = ROOT / "models"
REPORTS = ROOT / "reports"

MODEL_VERSION = "trust-hgb-1"

# A loan is blocked when its predicted default risk is at or above this.
RISK_THRESHOLD = 0.30
# For the "money lost" comparison: approve this share of requests, least risky first.
APPROVAL_RATE = 0.80


def baseline_model(train: pd.DataFrame) -> LogisticRegression:
    """Turns the rule score into a probability (Platt scaling), so it can be compared on Brier and calibration."""
    model = LogisticRegression()
    model.fit(train[["rule_score"]].to_numpy(), train["defaulted"].to_numpy())
    return model


BASELINE_PLUS_AMOUNT = ["rule_score", "amount"]


def baseline_plus_amount_model(train: pd.DataFrame):
    """A stronger baseline: the rule score and the loan size, so the AI is not credited just for seeing the amount."""
    model = make_pipeline(StandardScaler(), LogisticRegression())
    model.fit(train[BASELINE_PLUS_AMOUNT].to_numpy(), train["defaulted"].to_numpy())
    return model


def candidate_models() -> dict:
    return {
        "logistic_regression": make_pipeline(StandardScaler(), LogisticRegression(C=1.0, max_iter=5000)),
        "gradient_boosting": HistGradientBoostingClassifier(
            max_iter=300, learning_rate=0.05, max_leaf_nodes=15, l2_regularization=1.0, random_state=0
        ),
    }


def expected_calibration_error(y: np.ndarray, p: np.ndarray, bins: int = 10) -> float:
    edges = np.linspace(0, 1, bins + 1)
    ece = 0.0
    for low, high in zip(edges[:-1], edges[1:]):
        mask = (p >= low) & (p < high) if high < 1 else (p >= low) & (p <= high)
        if mask.any():
            ece += mask.mean() * abs(p[mask].mean() - y[mask].mean())
    return float(ece)


def evaluate(df: pd.DataFrame, p: np.ndarray) -> dict:
    y = df["defaulted"].to_numpy()
    amount = df["amount"].to_numpy()

    order = np.argsort(p)
    approved = np.zeros(len(p), dtype=bool)
    approved[order[: int(len(p) * APPROVAL_RATE)]] = True
    lost = float(amount[approved & (y == 1)].sum())
    total_default_money = float(amount[y == 1].sum())

    riskiest = np.zeros(len(p), dtype=bool)
    riskiest[order[-int(len(p) * 0.2) :]] = True
    exits = df["bad_actor_exit"].to_numpy() == 1
    strategic_exits = exits & (df["student_type"].to_numpy() == "strategic")
    ring_exits = exits & (df["student_type"].to_numpy() == "ring")

    return {
        "requests": int(len(df)),
        "default_rate": round(float(y.mean()), 4),
        "auc": round(float(roc_auc_score(y, p)), 4),
        "brier": round(float(brier_score_loss(y, p)), 4),
        "ece": round(expected_calibration_error(y, p), 4),
        "money_lost_at_80pct_approval": round(lost, 0),
        "share_of_default_money_avoided": round(1 - lost / total_default_money, 4) if total_default_money else None,
        "bad_actor_cashouts_in_riskiest_20pct": round(float(riskiest[exits].mean()), 4) if exits.any() else None,
        "strategic_cashouts_caught": round(float(riskiest[strategic_exits].mean()), 4) if strategic_exits.any() else None,
        "ring_cashouts_caught": round(float(riskiest[ring_exits].mean()), 4) if ring_exits.any() else None,
    }


def policy(df: pd.DataFrame, p: np.ndarray | None) -> dict:
    """What each lending policy would have let through.

    rules       a request is allowed when amount <= the rule tier's max loan
    rules + AI  the same, and the model's risk must be below the threshold
    """
    y = df["defaulted"].to_numpy()
    amount = df["amount"].to_numpy()
    allowed = amount <= df["rule_max_loan"].to_numpy()
    if p is not None:
        allowed &= p < RISK_THRESHOLD

    good = y == 0
    return {
        "good_requests_allowed": round(float(allowed[good].mean()), 4),
        "bad_requests_allowed": round(float(allowed[~good].mean()), 4),
        "default_money_allowed": round(float(amount[allowed & ~good].sum()), 0),
        "good_money_allowed": round(float(amount[allowed & good].sum()), 0),
    }


def reliability_plot(name: str, y: np.ndarray, curves: dict[str, np.ndarray]) -> None:
    plt.figure(figsize=(5.5, 5))
    plt.plot([0, 1], [0, 1], "k--", linewidth=1, label="Perfectly calibrated")
    edges = np.linspace(0, 1, 11)
    for label, p in curves.items():
        xs, ys = [], []
        for low, high in zip(edges[:-1], edges[1:]):
            mask = (p >= low) & (p < high)
            if mask.sum() >= 15:
                xs.append(p[mask].mean())
                ys.append(y[mask].mean())
        plt.plot(xs, ys, marker="o", label=label)
    plt.xlabel("Predicted default probability")
    plt.ylabel("Observed default rate")
    plt.title(f"Reliability diagram ({name})")
    plt.legend(fontsize=8)
    plt.tight_layout()
    plt.savefig(REPORTS / f"reliability_{name}.png", dpi=150)
    plt.close()


def roc_plot(name: str, y: np.ndarray, curves: dict[str, np.ndarray]) -> None:
    plt.figure(figsize=(5.5, 5))
    for label, p in curves.items():
        fpr, tpr, _ = roc_curve(y, p)
        plt.plot(fpr, tpr, label=f"{label} (AUC {roc_auc_score(y, p):.3f})")
    plt.plot([0, 1], [0, 1], "k--", linewidth=1)
    plt.xlabel("False positive rate")
    plt.ylabel("True positive rate")
    plt.title(f"ROC ({name})")
    plt.legend(fontsize=8)
    plt.tight_layout()
    plt.savefig(REPORTS / f"roc_{name}.png", dpi=150)
    plt.close()


def main() -> dict:
    MODELS.mkdir(exist_ok=True)
    REPORTS.mkdir(exist_ok=True)

    train = generate(WorldConfig(seed=7))
    validation = generate(WorldConfig(seed=8))
    tests = {
        "in_distribution": generate(WorldConfig(seed=11)),
        "shifted": generate(shifted(seed=99)),
    }

    X, y = train[FEATURE_NAMES].to_numpy(), train["defaulted"].to_numpy()
    baseline = baseline_model(train)
    baseline_amount = baseline_plus_amount_model(train)
    models = {name: model.fit(X, y) for name, model in candidate_models().items()}

    def predict(df: pd.DataFrame) -> dict[str, np.ndarray]:
        out = {
            "rule_baseline": baseline.predict_proba(df[["rule_score"]].to_numpy())[:, 1],
            "rule_baseline_plus_amount": baseline_amount.predict_proba(df[BASELINE_PLUS_AMOUNT].to_numpy())[:, 1],
        }
        for name, model in models.items():
            out[name] = model.predict_proba(df[FEATURE_NAMES].to_numpy())[:, 1]
        return out

    val_scores = {name: roc_auc_score(validation["defaulted"], p) for name, p in predict(validation).items() if not name.startswith("rule_baseline")}
    chosen = max(val_scores, key=val_scores.get)

    results: dict = {"validation_auc": {k: round(v, 4) for k, v in val_scores.items()}, "chosen_model": chosen, "tests": {}, "policy": {}}

    for name, df in tests.items():
        preds = predict(df)
        results["tests"][name] = {method: evaluate(df, p) for method, p in preds.items()}
        results["policy"][name] = {"rules": policy(df, None), "rules_plus_ai": policy(df, preds[chosen])}
        labels = {"Rule baseline": preds["rule_baseline"], "Rules + amount": preds["rule_baseline_plus_amount"], "Logistic regression": preds["logistic_regression"], "Gradient boosting": preds["gradient_boosting"]}
        reliability_plot(name, df["defaulted"].to_numpy(), labels)
        roc_plot(name, df["defaulted"].to_numpy(), labels)

    # Unseen type: retrain without strategic defaulters, test on the in-distribution world that has them.
    blind_train = generate(without("strategic", seed=7))
    blind = candidate_models()[chosen].fit(blind_train[FEATURE_NAMES].to_numpy(), blind_train["defaulted"].to_numpy())
    blind_baseline = baseline_model(blind_train)
    blind_baseline_amount = baseline_plus_amount_model(blind_train)
    unseen = tests["in_distribution"]
    results["tests"]["unseen_type"] = {
        "rule_baseline": evaluate(unseen, blind_baseline.predict_proba(unseen[["rule_score"]].to_numpy())[:, 1]),
        "rule_baseline_plus_amount": evaluate(unseen, blind_baseline_amount.predict_proba(unseen[BASELINE_PLUS_AMOUNT].to_numpy())[:, 1]),
        chosen: evaluate(unseen, blind.predict_proba(unseen[FEATURE_NAMES].to_numpy())[:, 1]),
    }

    joblib.dump({"model": models[chosen], "features": FEATURE_NAMES, "version": MODEL_VERSION, "threshold": RISK_THRESHOLD}, MODELS / "trust_model.joblib")
    metadata = {
        "version": MODEL_VERSION,
        "algorithm": chosen,
        "features": FEATURE_NAMES,
        "risk_threshold": RISK_THRESHOLD,
        "trained_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "training_requests": int(len(train)),
        "training_default_rate": round(float(y.mean()), 4),
    }
    (MODELS / "metadata.json").write_text(json.dumps(metadata, indent=2) + "\n")
    (REPORTS / "metrics.json").write_text(json.dumps(results, indent=2) + "\n")
    write_results(results, metadata)
    return results


def write_results(results: dict, metadata: dict) -> None:
    chosen = results["chosen_model"]
    lines = [
        "# AI trust model: results",
        "",
        "Generated by `python -m unicircle_ml.train`. Do not edit by hand.",
        "",
        f"Model: **{metadata['algorithm']}** (`{metadata['version']}`), trained on {metadata['training_requests']} synthetic loan requests "
        f"({metadata['training_default_rate']:.1%} defaulted). Chosen on validation AUC: "
        + ", ".join(f"{k} {v}" for k, v in results["validation_auc"].items())
        + ".",
        "",
        "Task: predict whether a loan request defaults, using only the borrower's history before it.",
        "",
    ]
    columns = [
        ("auc", "AUC ↑"),
        ("brier", "Brier ↓"),
        ("ece", "ECE ↓"),
        ("money_lost_at_80pct_approval", "$ lost at 80% approval ↓"),
        ("strategic_cashouts_caught", "Strategic cash-outs caught ↑"),
        ("ring_cashouts_caught", "Ring cash-outs caught ↑"),
    ]
    titles = {
        "in_distribution": "In distribution (new world, same settings)",
        "shifted": "Shifted world (2x bad actors, worse students, bigger loans)",
        "unseen_type": "Unseen type (trained without strategic defaulters)",
    }
    for test, methods in results["tests"].items():
        lines += [f"## {titles[test]}", "", "| Method | " + " | ".join(c[1] for c in columns) + " |", "|---" * (len(columns) + 1) + "|"]
        for method, m in methods.items():
            cells = []
            for key, _ in columns:
                value = m.get(key)
                if value is None:
                    cells.append("n/a")
                elif key.endswith("caught"):
                    cells.append(f"{value:.0%}")
                elif key.startswith("money"):
                    cells.append(f"${value:,.0f}")
                else:
                    cells.append(f"{value:.3f}")
            lines.append(f"| {method.replace('_', ' ')} | " + " | ".join(cells) + " |")
        lines.append("")
    lines += [
        "Caught = share of bad-actor cash-out loans ranked in the riskiest 20% of requests.",
        "",
        f"## Lending decision: rules alone vs rules + AI (block when risk ≥ {RISK_THRESHOLD:.0%})",
        "",
        "| World | Policy | Good requests allowed | Bad requests allowed | Default $ allowed | Good $ allowed |",
        "|---|---|---|---|---|---|",
    ]
    for world, policies in results["policy"].items():
        for name, m in policies.items():
            lines.append(
                f"| {world.replace('_', ' ')} | {name.replace('_', ' ')} | {m['good_requests_allowed']:.1%} | {m['bad_requests_allowed']:.1%} "
                f"| ${m['default_money_allowed']:,.0f} | ${m['good_money_allowed']:,.0f} |"
            )
    lines += [
        "",
        "Plots: `reliability_*.png` (calibration) and `roc_*.png`.",
        "",
        "## Limitations",
        "",
        "- All data is synthetic. We designed the generator, so these numbers show the method works on behaviors we modelled, not on real students.",
        "- The shifted and unseen-type tests reduce, but do not remove, that circularity. A pilot with real loans would be the real test.",
        "- A new kind of fraud is the weak spot: trained without strategic defaulters, the model ranks few of their cash-outs in the riskiest 20%. "
        "It still beats the rules on AUC there, but it does not discover a strategy it has never seen. Retraining as new patterns appear is part of the design.",
        "- Most of the gain on rings comes from behavior the rules also look at (short loans, reciprocity, few partners). The model combines these signals; the rules can only cap them one by one.",
        f"- Requests are scored as if every one were allowed, so both methods are judged on the same requests. In the app, the rules also cap {chosen.replace('_', ' ')}-approved amounts.",
        "",
    ]
    (REPORTS / "RESULTS.md").write_text("\n".join(lines))


if __name__ == "__main__":
    out = main()
    print(json.dumps({k: out[k] for k in ("validation_auc", "chosen_model")}, indent=2))
    print((REPORTS / "RESULTS.md").read_text())
