"""Python port of the backend's rule-based trust score (config/trust.php + TrustScoreCalculator).

This is the baseline the AI model has to beat. It reproduces the same points,
caps, anti-collusion rules and tiers, computed from the shared history format.
"""

from __future__ import annotations

from dataclasses import dataclass

from .history import DAY, HOUR, History

BASELINE = 500
TIERS = [("trusted", 700, 100), ("basic", 400, 20), ("restricted", 0, 0)]

CAPS = {"repayment": 300, "marketplace": 125, "vouches": 75}
POINTS = {
    "loan_repaid_on_time": 40,
    "loan_repaid_late": 10,
    "loan_overdue": -60,
    "loan_defaulted": -150,
    "sale_completed": 20,
    "vouch_received": 25,
}
NEUTRAL_STARS, POINTS_PER_STAR = 3, 10

MAX_REWARDS_PER_PAIR = 2
PAIR_WINDOW_DAYS = 90
MIN_LOAN_HOURS = 48
FULL_WEIGHT_LENDERS = 3
MUTUAL_VOUCH_WEIGHT = 0.5
MIN_ACCOUNT_DAYS_FOR_TOP_TIER = 14


@dataclass
class RuleScore:
    score: int
    tier: str
    max_loan: int
    breakdown: dict[str, int]
    flags: list[str]


def _events(history: History) -> list[dict]:
    """The trust events the backend would have recorded by `history.now`."""
    now = history.now
    events: list[dict] = []

    for loan in history.borrowed:
        outcome = loan.outcome(now)
        overdue_at = loan.due_at + DAY  # The daily job notices it the next day.

        if outcome in ("late", "overdue", "defaulted") and overdue_at <= now:
            events.append({"component": "repayment", "points": POINTS["loan_overdue"], "cp": loan.lender_id, "at": overdue_at})
        if outcome == "defaulted":
            events.append({"component": "repayment", "points": POINTS["loan_defaulted"], "cp": loan.lender_id, "at": loan.due_at + 14 * DAY})
        if outcome in ("on_time", "late"):
            too_short = (loan.repaid_at - loan.activated_at) < MIN_LOAN_HOURS * HOUR
            if not too_short:
                kind = "loan_repaid_on_time" if outcome == "on_time" else "loan_repaid_late"
                events.append({"component": "repayment", "points": POINTS[kind], "cp": loan.lender_id, "at": loan.repaid_at})

    for sale in history.sales:
        at = int(sale.get("sold_at", now))
        events.append({"component": "marketplace", "points": POINTS["sale_completed"], "cp": int(sale["buyer_id"]), "at": at})
        if sale.get("stars") is not None:
            stars_points = (int(sale["stars"]) - NEUTRAL_STARS) * POINTS_PER_STAR
            events.append({"component": "marketplace", "points": stars_points, "cp": int(sale["buyer_id"]), "at": at + 1})

    for vouch in history.vouches:
        if not vouch.get("eligible", True):
            continue
        weight = MUTUAL_VOUCH_WEIGHT if vouch.get("mutual") else 1.0
        events.append({"component": "vouches", "points": POINTS["vouch_received"] * weight, "cp": None, "at": 0})

    return events


def _pair_capped(events: list[dict]) -> list[dict]:
    accepted: dict[str, list[int]] = {}
    kept = []
    for event in sorted(events, key=lambda e: e["at"]):
        if event["cp"] is None or event["points"] <= 0:
            kept.append(event)
            continue
        key = f'{event["component"]}:{event["cp"]}'
        recent = [t for t in accepted.get(key, []) if (event["at"] - t) < PAIR_WINDOW_DAYS * DAY]
        if len(recent) >= MAX_REWARDS_PER_PAIR:
            continue
        accepted.setdefault(key, []).append(event["at"])
        kept.append(event)
    return kept


def score(history: History) -> RuleScore:
    events = _pair_capped(_events(history))

    repayment_rewards = [e for e in events if e["component"] == "repayment" and e["points"] > 0]
    repayment_penalties = [e for e in events if e["component"] == "repayment" and e["points"] <= 0]
    lenders = {e["cp"] for e in repayment_rewards}
    diversity = min(1.0, len(lenders) / FULL_WEIGHT_LENDERS) if repayment_rewards else 1.0

    raw = {
        "repayment": round(sum(e["points"] for e in repayment_rewards) * diversity) + sum(e["points"] for e in repayment_penalties),
        "marketplace": sum(e["points"] for e in events if e["component"] == "marketplace"),
        "vouches": round(sum(e["points"] for e in events if e["component"] == "vouches")),
    }
    breakdown = {name: int(max(-CAPS[name], min(CAPS[name], points))) for name, points in raw.items()}
    total = int(max(0, min(1000, BASELINE + sum(breakdown.values()))))

    flags = []
    if not history.email_verified:
        flags.append("unverified_email")
    if (history.now - history.account_created_at) / DAY < MIN_ACCOUNT_DAYS_FOR_TOP_TIER:
        flags.append("new_account")
    if history.circular_lending:
        flags.append("circular_lending")

    tier = next(t for t in TIERS if total >= t[1])
    if "unverified_email" in flags:
        tier = TIERS[-1]
    elif tier == TIERS[0] and ({"new_account", "circular_lending"} & set(flags)):
        tier = TIERS[1]

    return RuleScore(total, tier[0], tier[2], breakdown, flags)
