"""Train and evaluate the recommender.

    python -m unicircle_ml.rec_train

Training: every join and purchase in one synthetic campus, each against
randomly drawn items the student could have picked instead at that moment.

Evaluation (a different campus): freeze time at day 90 of 120. For each
student, rank what is open at that moment and check the top 5 against what
they actually joined or bought afterwards. precision@5, recall@5, hit rate@5.
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
from sklearn.linear_model import LogisticRegression  # noqa: E402
from sklearn.pipeline import make_pipeline  # noqa: E402
from sklearn.preprocessing import StandardScaler  # noqa: E402

from .rec_simulate import Campus, RecConfig  # noqa: E402
from .recommend import DAY, GROUP_FEATURES, LISTING_FEATURES, group_features, listing_features  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
MODELS, REPORTS = ROOT / "models", ROOT / "reports"
VERSION = "rec-lr-1"
K = 5
NEGATIVES = 10


def training_rows(campus: Campus, rng: np.random.Generator):
    gX, gy, lX, ly = [], [], [], []

    for sid, gid, t in campus.joins:
        candidates = campus.open_groups_at(sid, t)
        positive = [g for g in candidates if g.id == gid]
        others = [g for g in candidates if g.id != gid]
        if not positive:
            continue
        picked = [others[i] for i in rng.choice(len(others), size=min(NEGATIVES, len(others)), replace=False)] if others else []
        user = campus.group_user(sid, t)
        for g, label in [(positive[0], 1), *[(o, 0) for o in picked]]:
            row = group_features(user, campus.group_payload(g, t), t)
            gX.append([row[n] for n in GROUP_FEATURES])
            gy.append(label)

    for sid, lid, t in campus.purchases:
        available = campus.available_listings_at(sid, t)
        positive = [l for l in available if l.id == lid]
        others = [l for l in available if l.id != lid]
        if not positive:
            continue
        picked = [others[i] for i in rng.choice(len(others), size=min(NEGATIVES, len(others)), replace=False)] if others else []
        items = [positive[0], *picked]
        rows = listing_features(campus.listing_user(sid, t), [campus.listing_payload(l) for l in items], t)
        for row, label in zip(rows, [1] + [0] * len(picked)):
            lX.append([row[n] for n in LISTING_FEATURES])
            ly.append(label)

    return np.array(gX), np.array(gy), np.array(lX), np.array(ly)


def fit(X, y):
    return make_pipeline(StandardScaler(), LogisticRegression(max_iter=2000)).fit(X, y)


def at_k(ranked: list[int], relevant: set[int]) -> tuple[float, float, float]:
    top = ranked[:K]
    hits = len(set(top) & relevant)
    return hits / K, hits / len(relevant), 1.0 if hits else 0.0


def position(ranked: list[int], chosen: int) -> int | None:
    """1-based position of the chosen item, or None when the method does not show it at all."""
    return ranked.index(chosen) + 1 if chosen in ranked else None


def rank_groups(campus: Campus, model, sid: int, t: int) -> tuple[list[int], list[int]]:
    """(model ranking, today's SQL ranking) of the groups open to the student at time t."""
    student = campus.students[sid]
    groups = campus.open_groups_at(sid, t)
    if not groups:
        return [], []
    user = campus.group_user(sid, t)
    rows = [group_features(user, campus.group_payload(g, t), t) for g in groups]
    scores = model.predict_proba(np.array([[r[n] for n in GROUP_FEATURES] for r in rows]))[:, 1]
    ranked = [groups[i].id for i in np.argsort(-scores, kind="stable")]
    mine = [(g, r) for g, r in zip(groups, rows) if g.course in student.courses]
    baseline = [g.id for g, r in sorted(mine, key=lambda x: (-x[1]["classmates"], x[0].starts_at))]
    return ranked, baseline


def rank_listings(campus: Campus, model, sid: int, t: int) -> dict[str, list[int]]:
    student = campus.students[sid]
    listings = campus.available_listings_at(sid, t)
    if not listings:
        return {"model": [], "newest": [], "my_courses_newest": []}
    rows = listing_features(campus.listing_user(sid, t), [campus.listing_payload(l) for l in listings], t)
    scores = model.predict_proba(np.array([[r[n] for n in LISTING_FEATURES] for r in rows]))[:, 1]
    return {
        "model": [listings[i].id for i in np.argsort(-scores, kind="stable")],
        "newest": [l.id for l in sorted(listings, key=lambda l: -l.created_at)],
        "my_courses_newest": [l.id for l in sorted(listings, key=lambda l: (l.course not in student.courses, -l.created_at))],
    }


def decision_metrics(positions: list[int | None], candidates: list[int]) -> dict:
    """Where the item the student actually chose landed in each ranking."""
    n = len(positions)
    if not n:
        return {"decisions": 0}
    found = [p for p in positions if p is not None]
    return {
        "decisions": n,
        "avg_candidates": round(float(np.mean(candidates)), 1),
        "hit_at_1": round(sum(1 for p in found if p <= 1) / n, 4),
        "hit_at_3": round(sum(1 for p in found if p <= 3) / n, 4),
        "hit_at_5": round(sum(1 for p in found if p <= 5) / n, 4),
        "mrr": round(sum(1 / p for p in found) / n, 4),
    }


def evaluate(campus: Campus, group_model, listing_model) -> dict:
    T = campus.config.cutoff_day * DAY

    # 1) Every real choice after the cutoff, ranked against everything open at that moment.
    group_pos = {"groups_model": [], "groups_baseline": []}
    group_sizes = []
    for sid, gid, t in campus.joins:
        if t < T:
            continue
        ranked, baseline = rank_groups(campus, group_model, sid, t)
        if gid not in ranked:
            continue
        group_sizes.append(len(ranked))
        group_pos["groups_model"].append(position(ranked, gid))
        group_pos["groups_baseline"].append(position(baseline, gid))

    listing_pos = {"listings_model": [], "listings_newest": [], "listings_my_courses_newest": []}
    listing_sizes = []
    for sid, lid, t in campus.purchases:
        if t < T:
            continue
        ranks = rank_listings(campus, listing_model, sid, t)
        if lid not in ranks["model"]:
            continue
        listing_sizes.append(len(ranks["model"]))
        for name, ranked in ranks.items():
            listing_pos[f"listings_{name}"].append(position(ranked, lid))

    decisions = {name: decision_metrics(p, group_sizes) for name, p in group_pos.items()}
    decisions |= {name: decision_metrics(p, listing_sizes) for name, p in listing_pos.items()}

    # 2) precision@5 / recall@5: freeze time at the cutoff, compare each student's top 5 with what they chose later.
    snapshot = {name: [] for name in decisions}
    later_joins: dict[int, set[int]] = {}
    later_buys: dict[int, set[int]] = {}
    for sid, gid, t in campus.joins:
        if t >= T:
            later_joins.setdefault(sid, set()).add(gid)
    for sid, lid, t in campus.purchases:
        if t >= T:
            later_buys.setdefault(sid, set()).add(lid)
    for sid in campus.students:
        if later_joins.get(sid):
            ranked, baseline = rank_groups(campus, group_model, sid, T)
            relevant = later_joins[sid] & set(ranked)
            if relevant:
                snapshot["groups_model"].append(at_k(ranked, relevant))
                snapshot["groups_baseline"].append(at_k(baseline, relevant))
        if later_buys.get(sid):
            ranks = rank_listings(campus, listing_model, sid, T)
            relevant = later_buys[sid] & set(ranks["model"])
            if relevant:
                for name, ranked in ranks.items():
                    snapshot[f"listings_{name}"].append(at_k(ranked, relevant))

    def summary(values):
        arr = np.array(values) if values else np.zeros((0, 3))
        return {
            "students": int(len(arr)),
            "precision_at_5": round(float(arr[:, 0].mean()), 4) if len(arr) else 0.0,
            "recall_at_5": round(float(arr[:, 1].mean()), 4) if len(arr) else 0.0,
        }

    return {name: {**decisions[name], **summary(snapshot[name])} for name in decisions}


def coefficients(model, names: list[str]) -> dict[str, float]:
    return {n: round(float(c), 3) for n, c in zip(names, model[-1].coef_[0])}


def main() -> dict:
    MODELS.mkdir(exist_ok=True)
    REPORTS.mkdir(exist_ok=True)
    rng = np.random.default_rng(0)

    train = Campus(RecConfig(seed=31)).run()
    gX, gy, lX, ly = training_rows(train, rng)
    group_model, listing_model = fit(gX, gy), fit(lX, ly)

    tests = {
        "same_size_campus": Campus(RecConfig(seed=32)).run(),
        "larger_campus": Campus(RecConfig(seed=33, students=1000)).run(),
    }
    results = {
        "version": VERSION,
        "training": {"group_rows": int(len(gy)), "listing_rows": int(len(ly))},
        "coefficients": {"groups": coefficients(group_model, GROUP_FEATURES), "listings": coefficients(listing_model, LISTING_FEATURES)},
        "tests": {name: evaluate(campus, group_model, listing_model) for name, campus in tests.items()},
    }

    joblib.dump({"groups": group_model, "listings": listing_model, "group_features": GROUP_FEATURES,
                 "listing_features": LISTING_FEATURES, "version": VERSION}, MODELS / "recommender.joblib")
    (REPORTS / "recommender_metrics.json").write_text(json.dumps(results, indent=2) + "\n")
    plot(results)
    write_report(results)
    return results


def plot(results: dict) -> None:
    test = results["tests"]["same_size_campus"]
    labels = ["Groups:\nSQL today", "Groups:\nmodel", "Listings:\nnewest", "Listings: my\ncourses, newest", "Listings:\nmodel"]
    keys = ["groups_baseline", "groups_model", "listings_newest", "listings_my_courses_newest", "listings_model"]
    values = [test[k]["hit_at_3"] or 0 for k in keys]
    colors = ["#9ca3af", "#2563eb", "#9ca3af", "#9ca3af", "#2563eb"]
    plt.figure(figsize=(7, 4))
    bars = plt.bar(labels, values, color=colors)
    for bar, value in zip(bars, values):
        plt.text(bar.get_x() + bar.get_width() / 2, value + 0.003, f"{value:.3f}", ha="center", fontsize=9)
    plt.ylabel("Hit@3 (chosen item in the top 3)")
    plt.title("Recommender vs today's ordering (held-out campus)")
    plt.tight_layout()
    plt.savefig(REPORTS / "recommender_precision.png", dpi=150)
    plt.close()


def write_report(results: dict) -> None:
    names = {
        "groups_baseline": "Groups: SQL today (my courses, most classmates)",
        "groups_model": "Groups: model",
        "listings_newest": "Listings: newest first (today's feed)",
        "listings_my_courses_newest": "Listings: my courses first, then newest",
        "listings_model": "Listings: model",
    }
    lines = [
        "# Recommender: results",
        "",
        "Generated by `python -m unicircle_ml.rec_train`. Do not edit by hand.",
        "",
        "Time is frozen at day 90 of 120 on a campus the model never saw. For each student, the open groups and",
        "available listings are ranked, and the top 5 are checked against what the student joined or bought later.",
        "",
    ]
    for test, rows in results["tests"].items():
        g, l = rows["groups_model"], rows["listings_model"]
        lines += [
            f"## {test.replace('_', ' ').capitalize()}",
            "",
            f"Ranked at the moment of each real choice: {g['decisions']} group joins (about {g['avg_candidates']:.0f} open groups each time) "
            f"and {l['decisions']} purchases (about {l['avg_candidates']:.0f} listings each time).",
            "",
            "| Method | Hit@1 ↑ | Hit@3 ↑ | Hit@5 ↑ | MRR ↑ | precision@5 ↑ | recall@5 ↑ |",
            "|---|---|---|---|---|---|---|",
        ]
        for key, label in names.items():
            m = rows[key]
            lines.append(
                f"| {label} | {m['hit_at_1']:.3f} | {m['hit_at_3']:.3f} | {m['hit_at_5']:.3f} | {m['mrr']:.3f} "
                f"| {m['precision_at_5']:.3f} | {m['recall_at_5']:.3f} |"
            )
        lines.append("")
    lines += [
        "## What the model learned (logistic regression weights, standardized features)",
        "",
        "| Groups | Weight | Listings | Weight |",
        "|---|---|---|---|",
    ]
    g = list(results["coefficients"]["groups"].items())
    li = list(results["coefficients"]["listings"].items())
    for i in range(max(len(g), len(li))):
        left = f"{g[i][0]} | {g[i][1]:+.3f}" if i < len(g) else " | "
        right = f"{li[i][0]} | {li[i][1]:+.3f}" if i < len(li) else " | "
        lines.append(f"| {left} | {right} |")
    lines += [
        "",
        "## Limitations",
        "",
        "- Synthetic data: students choose from hidden friendships, time preferences and interests that we designed.",
        "  The model only sees their traces (past group partners, past hours, past purchases), never the hidden values.",
        "- Hit@k: the item the student actually chose was in the top k at that moment. MRR: average of 1 / its position.",
        "  precision@5 and recall@5 freeze time at day 90 and compare each student's top 5 with everything they chose later;",
        "  precision@5 stays low because most students only join or buy one or two things in 30 days.",
        "- With real students, we would retrain on real joins and purchases once there are enough of them.",
        "",
    ]
    (REPORTS / "RECOMMENDER.md").write_text("\n".join(lines))


if __name__ == "__main__":
    main()
    print((REPORTS / "RECOMMENDER.md").read_text())
