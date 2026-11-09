import pytest

from unicircle_ml.history import DAY, HOUR
from unicircle_ml.service import create_app

NOW = 400 * DAY


@pytest.fixture
def client():
    return create_app(token="").test_client()


def honest_history() -> dict:
    loans = []
    for i, lender in enumerate(range(11, 17)):
        start = NOW - (90 - i * 10) * DAY
        loans.append({"lender_id": lender, "amount": 20, "activated_at": start, "due_at": start + 14 * DAY, "repaid_at": start + 7 * DAY})
    return {"now": NOW, "account_created_at": NOW - 200 * DAY, "borrowed": loans, "vouches": [{"voucher_id": 30, "mutual": False}]}


def defaulter_history() -> dict:
    start = NOW - 60 * DAY
    loans = [
        {"lender_id": 11, "amount": 20, "activated_at": start, "due_at": start + 10 * DAY, "repaid_at": None},
        {"lender_id": 12, "amount": 20, "activated_at": start, "due_at": start + 10 * DAY, "repaid_at": start + 25 * DAY},
    ]
    return {"now": NOW, "account_created_at": NOW - 90 * DAY, "borrowed": loans}


def test_health(client):
    response = client.get("/health")
    assert response.status_code == 200
    assert response.get_json()["status"] == "ok"


def test_assess_returns_a_risk_per_amount(client):
    response = client.post("/trust/assess", json={"history": honest_history(), "amounts": [20, 100]})
    body = response.get_json()

    assert response.status_code == 200
    assert [a["amount"] for a in body["assessments"]] == [20, 100]
    assert all(0 <= a["p_default"] <= 1 for a in body["assessments"])
    assert body["model_version"]


def test_a_past_defaulter_looks_riskier_than_an_honest_borrower(client):
    honest = client.post("/trust/assess", json={"history": honest_history(), "amounts": [20]}).get_json()
    risky = client.post("/trust/assess", json={"history": defaulter_history(), "amounts": [20]}).get_json()

    assert risky["assessments"][0]["p_default"] > honest["assessments"][0]["p_default"]
    assert honest["assessments"][0]["allowed"] is True


def test_invalid_requests_return_422(client):
    assert client.post("/trust/assess", json={}).status_code == 422
    assert client.post("/trust/assess", json={"history": honest_history(), "amounts": [-5]}).status_code == 422
    assert client.post("/trust/assess", json={"history": {"borrowed": []}}).status_code == 422


def test_token_is_required_when_set():
    secured = create_app(token="secret").test_client()

    assert secured.get("/health").status_code == 200
    assert secured.post("/trust/assess", json={"history": honest_history()}).status_code == 401
    assert (
        secured.post("/trust/assess", json={"history": honest_history()}, headers={"Authorization": "Bearer secret"}).status_code == 200
    )
