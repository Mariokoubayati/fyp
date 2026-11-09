import numpy as np
import pytest

from unicircle_ml import rec_train
from unicircle_ml.rec_simulate import Campus, RecConfig
from unicircle_ml.recommend import DAY, GROUP_FEATURES, LISTING_FEATURES, group_features, listing_features
from unicircle_ml.service import create_app

NOW = 100 * DAY


def user() -> dict:
    return {
        "id": 1, "major": "CS", "study_year": 2, "course_ids": [3, 5],
        "co_members": {"17": 2}, "past_join_hours": [18],
        "course_names": ["Database Systems", "Data Structures"],
        "purchases": [{"title": "Calculus textbook", "category": "books", "price": 20}],
        "co_member_ids": [17],
    }


def group(gid: int, course: int, members: list[dict], hour: int = 18) -> dict:
    return {"id": gid, "course_id": course, "starts_at": NOW + 3 * DAY + hour * 3600, "capacity": 6, "members": members}


def classmate(mid: int) -> dict:
    return {"id": mid, "major": "CS", "study_year": 2, "course_ids": [3]}


def stranger(mid: int) -> dict:
    return {"id": mid, "major": "Design", "study_year": 4, "course_ids": [40]}


def listing(lid: int, title: str, course: int | None, category: str = "books", price: float = 15, seller: int = 99) -> dict:
    return {"id": lid, "title": title, "description": "", "category": category, "course_id": course,
            "price": price, "seller_id": seller, "created_at": NOW - DAY}


# --- features ---


def test_group_features():
    row = group_features(user(), group(1, 3, [classmate(17), classmate(18), stranger(19)], hour=20), NOW)

    assert list(row) == GROUP_FEATURES
    assert row["course_enrolled"] == 1
    assert row["classmates"] == 2
    assert row["co_members"] == 2
    assert row["same_major_share"] == pytest.approx(2 / 3)
    assert row["hour_distance"] == 2
    assert row["days_until_start"] == pytest.approx(3 + 20 / 24)


def test_listing_features_match_course_text_and_people():
    rows = listing_features(user(), [
        listing(1, "Database Systems textbook", 3, seller=17),
        listing(2, "Desk lamp", None, category="other", price=40),
    ], NOW)

    assert list(rows[0]) == LISTING_FEATURES
    assert rows[0]["course_enrolled"] == 1 and rows[1]["course_enrolled"] == 0
    assert rows[0]["text_similarity"] > rows[1]["text_similarity"]
    assert rows[0]["category_affinity"] == 1.0 and rows[1]["category_affinity"] == 0.0
    assert rows[0]["seller_is_co_member"] == 1.0
    assert rows[0]["age_days"] == pytest.approx(1)


def test_listing_features_with_no_profile_text():
    rows = listing_features({"course_ids": []}, [listing(1, "Lamp", None)], NOW)
    assert rows[0]["text_similarity"] == 0.0


# --- simulation and evaluation run end to end on a small campus ---


def test_small_campus_trains_and_evaluates():
    campus = Campus(RecConfig(seed=5, students=150, days=60, cutoff_day=45)).run()
    gX, gy, lX, ly = rec_train.training_rows(campus, np.random.default_rng(0))
    assert gy.sum() > 0 and lX.shape[1] == len(LISTING_FEATURES)

    results = rec_train.evaluate(campus, rec_train.fit(gX, gy), rec_train.fit(lX, ly))
    assert results["groups_model"]["decisions"] > 0
    assert 0 <= results["listings_model"]["mrr"] <= 1


def test_same_seed_same_campus():
    a = Campus(RecConfig(seed=9, students=100, days=30)).run()
    b = Campus(RecConfig(seed=9, students=100, days=30)).run()
    assert a.joins == b.joins and a.purchases == b.purchases


# --- service ---


@pytest.fixture
def client():
    return create_app(token="").test_client()


def test_groups_with_classmates_in_my_course_rank_first(client):
    response = client.post("/recommend/groups", json={
        "now": NOW,
        "user": user(),
        "candidates": [group(1, 40, [stranger(30)]), group(2, 3, [classmate(17), classmate(18)]), group(3, 41, [])],
    })

    assert response.status_code == 200
    ranked = response.get_json()["ranked"]
    assert [r["id"] for r in ranked][0] == 2
    assert len(ranked) == 3


def test_listings_for_my_courses_rank_first(client):
    response = client.post("/recommend/listings", json={
        "now": NOW,
        "user": user(),
        "candidates": [listing(1, "Mini fridge", None, "other", 60), listing(2, "Database Systems textbook", 3), listing(3, "Concert ticket", None, "other", 30)],
    })

    assert response.status_code == 200
    assert response.get_json()["ranked"][0]["id"] == 2


def test_empty_candidates_return_an_empty_ranking(client):
    response = client.post("/recommend/listings", json={"now": NOW, "user": user(), "candidates": []})
    assert response.status_code == 200 and response.get_json()["ranked"] == []


def test_invalid_recommendation_requests_return_422(client):
    assert client.post("/recommend/groups", json={"candidates": []}).status_code == 422
    assert client.post("/recommend/groups", json={"user": user(), "candidates": [{"id": 1}]}).status_code == 422
    assert client.post("/recommend/listings", json={"user": user(), "candidates": [{}] * 501}).status_code == 422
