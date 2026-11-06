from unicircle_ml import rules
from unicircle_ml.features import FEATURE_NAMES, extract
from unicircle_ml.history import DAY, HOUR, BorrowedLoan, History

NOW = 400 * DAY


def history(**kwargs) -> History:
    return History(now=NOW, account_created_at=NOW - 60 * DAY, **kwargs)


def repaid(lender: int, days_ago: int, hours: int = 72, amount: float = 5) -> BorrowedLoan:
    start = NOW - days_ago * DAY
    return BorrowedLoan(lender, amount, start, start + 10 * DAY, start + hours * HOUR)


# --- the Python port matches the backend's numbers (see CollusionAttackTest.php) ---


def test_new_verified_student_is_500_basic():
    result = rules.score(history())
    assert (result.score, result.tier, result.max_loan) == (500, "basic", 20)


def test_unverified_student_cannot_borrow():
    assert rules.score(history(email_verified=False)).tier == "restricted"


def test_five_different_lenders_reach_trusted():
    result = rules.score(history(borrowed=[repaid(lender, 60 - i * 3) for i, lender in enumerate(range(11, 16))]))
    assert (result.score, result.tier) == (700, "trusted")


def test_supervisor_attack_lands_at_592_like_the_backend():
    loans = [repaid(2, 60 - i * 5) for i in range(6)]
    sales = [{"buyer_id": 2, "stars": 5, "sold_at": NOW - (20 - i) * DAY} for i in range(3)]
    vouches = [{"voucher_id": 2, "mutual": False, "eligible": True}]
    result = rules.score(history(borrowed=loans, sales=sales, vouches=vouches))
    assert (result.score, result.tier) == (592, "basic")


def test_short_loans_earn_nothing():
    result = rules.score(history(borrowed=[repaid(lender, 30, hours=1) for lender in (11, 12, 13)]))
    assert result.breakdown["repayment"] == 0


def test_default_costs_210_points():
    start = NOW - 60 * DAY
    loan = BorrowedLoan(11, 20, start, start + 10 * DAY, None)
    result = rules.score(history(borrowed=[loan]))
    assert (result.score, result.tier) == (290, "restricted")


def test_circular_lending_holds_the_tier():
    loans = [repaid(lender, 60 - i * 3) for i, lender in enumerate(range(11, 16))]
    assert rules.score(history(borrowed=loans, circular_lending=True)).tier == "basic"


# --- features ---


def test_features_for_an_empty_history():
    features = extract(history(), 20)
    assert list(features) == FEATURE_NAMES
    assert features["on_time_rate"] == 0.5
    assert features["amount_to_max_repaid"] == 4.0
    assert features["top_lender_share"] == 0.0


def test_features_see_only_the_past():
    future = BorrowedLoan(11, 50, NOW + DAY, NOW + 10 * DAY, NOW + 2 * DAY)
    features = extract(history(borrowed=[future]), 20)
    assert features["loans_repaid"] == 0
    assert features["max_repaid_amount"] == 0


def test_loan_outcomes_follow_the_backend_timeline():
    start = NOW - 40 * DAY
    on_time = BorrowedLoan(1, 10, start, start + 10 * DAY, start + 5 * DAY)
    late = BorrowedLoan(2, 10, start, start + 10 * DAY, start + 15 * DAY)
    overdue = BorrowedLoan(3, 10, NOW - 15 * DAY, NOW - 5 * DAY, None)
    defaulted = BorrowedLoan(4, 10, start, start + 10 * DAY, None)
    assert [l.outcome(NOW) for l in (on_time, late, overdue, defaulted)] == ["on_time", "late", "overdue", "defaulted"]


def test_collusion_signals_show_up_in_features():
    loans = [repaid(2, 60 - i * 5, hours=5) for i in range(4)]
    lent = [{"borrower_id": 2, "activated_at": NOW - 30 * DAY}]
    vouches = [{"voucher_id": 2, "mutual": True}]
    features = extract(history(borrowed=loans, lent=lent, vouches=vouches, circular_lending=True), 20)
    assert features["top_lender_share"] == 1.0
    assert features["reciprocal_share"] == 1.0
    assert features["short_loan_share"] == 1.0
    assert features["mutual_vouch_share"] == 1.0
    assert features["circular_lending"] == 1.0
