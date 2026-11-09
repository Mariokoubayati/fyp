"""Behavior features for the AI trust model.

Every feature is computed only from what is known at `history.now`, so a model
trained on past loans never sees the future. The backend computes the exact
same features in PHP (App\\Services\\Trust\\TrustFeatureExtractor); the parity
fixture in tests/fixtures keeps the two in line.
"""

from __future__ import annotations

from collections import Counter

from .history import DAY, HOUR, History

SHORT_LOAN_HOURS = 48

# Order matters: it is the column order the model was trained on.
FEATURE_NAMES = [
    "loans_repaid",
    "on_time_rate",
    "late_count",
    "overdue_count",
    "defaulted_count",
    "distinct_lenders",
    "top_lender_share",
    "reciprocal_share",
    "short_loan_share",
    "max_repaid_amount",
    "distinct_borrowers",
    "circular_lending",
    "vouches_received",
    "mutual_vouch_share",
    "sales_count",
    "avg_rating",
    "account_age_days",
    "amount",
    "amount_to_max_repaid",
]


def extract(history: History, amount: float) -> dict[str, float]:
    """Features for a loan of `amount` requested at `history.now`."""
    now = history.now
    outcomes = [loan.outcome(now) for loan in history.borrowed]
    repaid = [loan for loan, outcome in zip(history.borrowed, outcomes) if outcome in ("on_time", "late")]

    on_time = outcomes.count("on_time")
    late = outcomes.count("late")
    closed = on_time + late + outcomes.count("defaulted")

    lenders = Counter(loan.lender_id for loan in history.borrowed if loan.activated_at <= now)
    lent_to = {int(loan["borrower_id"]) for loan in history.lent if int(loan.get("activated_at", 0)) <= now}

    short = sum(1 for loan in repaid if (loan.repaid_at - loan.activated_at) < SHORT_LOAN_HOURS * HOUR)
    max_repaid = max((loan.amount for loan in repaid), default=0.0)

    vouches = len(history.vouches)
    mutual = sum(1 for vouch in history.vouches if vouch.get("mutual"))

    ratings = [int(sale["stars"]) for sale in history.sales if sale.get("stars") is not None]

    return {
        "loans_repaid": float(len(repaid)),
        # Smoothed so a student with no history sits in the middle rather than at 0 or 1.
        "on_time_rate": (on_time + 1) / (closed + 2),
        "late_count": float(late),
        "overdue_count": float(outcomes.count("overdue")),
        "defaulted_count": float(outcomes.count("defaulted")),
        "distinct_lenders": float(len(lenders)),
        "top_lender_share": (max(lenders.values()) / sum(lenders.values())) if lenders else 0.0,
        "reciprocal_share": (len(set(lenders) & lent_to) / len(lenders)) if lenders else 0.0,
        "short_loan_share": (short / len(repaid)) if repaid else 0.0,
        "max_repaid_amount": float(max_repaid),
        "distinct_borrowers": float(len(lent_to)),
        "circular_lending": 1.0 if history.circular_lending else 0.0,
        "vouches_received": float(vouches),
        "mutual_vouch_share": (mutual / vouches) if vouches else 0.0,
        "sales_count": float(len(history.sales)),
        "avg_rating": (sum(ratings) / len(ratings)) if ratings else 3.0,
        "account_age_days": max(0.0, (now - history.account_created_at) / DAY),
        "amount": float(amount),
        "amount_to_max_repaid": float(amount) / max(5.0, max_repaid),
    }


def vector(features: dict[str, float]) -> list[float]:
    return [float(features[name]) for name in FEATURE_NAMES]
