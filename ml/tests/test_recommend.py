import numpy as np
import pytest

from unicircle_ml import rec_train
from unicircle_ml.rec_simulate import Campus, RecConfig
from unicircle_ml.recommend import (
    DAY,
    EVENT_FEATURES,
    GROUP_FEATURES,
    LISTING_FEATURES,
    PROJECT_FEATURES,
    event_features,
    group_features,
    listing_features,
    project_features,
)
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


def hub_user(**extra) -> dict:
    return {"id": 1, "major": "CS", "interests": ["tech"], "skills": ["Laravel"], "course_ids": [3],
            "co_members": {"17": 2}, "past_event_tags": ["tech", "tech", "career"], "past_event_count": 2,
            "past_event_hours": [18], **extra}


def event(eid: int, tags: list[str], attendees: list[dict], course: int | None = None, capacity: int | None = None, hour: int = 18) -> dict:
    return {"id": eid, "tags": tags, "course_id": course, "starts_at": NOW + 3 * DAY + hour * 3600,
            "capacity": capacity, "attendees": attendees}


def project(pid: int, skills: list[str], tags: list[str], owner: int = 99, members: list[int] | None = None) -> dict:
    return {"id": pid, "owner_id": owner, "owner_major": "CS", "tags": tags, "skills_needed": skills,
            "course_id": None, "team_size": 4, "member_ids": members or [owner], "created_at": NOW - DAY}


def test_event_features():
    row = event_features(hub_user(), event(1, ["tech", "arts"], [{"id": 17, "major": "CS"}, {"id": 40, "major": "Design"}], capacity=4), NOW)
    assert set(row) == set(EVENT_FEATURES)
    assert row["interest_overlap"] == 0.5
    assert row["tag_affinity"] == 0.5  # "tech" twice in 2 past events, over 2 tags
    assert row["co_members_going"] == 2 and row["same_major_share"] == 0.5
    assert row["fill_ratio"] == 0.5 and row["hour_distance"] == 0 and row["no_profile_interests"] == 0


def test_event_features_without_a_profile_or_history():
    row = event_features({"id": 1}, event(1, ["sports"], []), NOW)
    assert row["no_profile_interests"] == 1 and row["interest_overlap"] == 0 and row["tag_affinity"] == 0
    assert row["hour_distance"] == 6 and row["fill_ratio"] == 0


def test_project_features_match_skills_without_case():
    row = project_features(hub_user(), project(1, ["laravel", "UI design"], ["tech"], owner=17, members=[17, 5]), NOW)
    assert set(row) == set(PROJECT_FEATURES)
    assert row["skill_match"] == 0.5 and row["interest_overlap"] == 1
    assert row["owner_is_co_member"] == 1 and row["co_members_in_team"] == 2
    assert row["open_spots"] == 2 and row["same_major_owner"] == 1 and row["no_profile_skills"] == 0


def test_small_campus_trains_events_and_projects():
    campus = Campus(RecConfig(seed=5, students=200, days=60, cutoff_day=45)).run()
    rng = np.random.default_rng(0)
    rec_train.training_rows(campus, rng)
    eX, ey, pX, py = rec_train.hub_training_rows(campus, rng)
    assert ey.sum() > 0 and (ey == 0).sum() > 0 and pX.shape[1] == len(PROJECT_FEATURES)

    results = rec_train.evaluate_hub(campus, rec_train.fit(eX, ey), rec_train.fit(pX, py))
    assert results["events_model"]["decisions"] > 0
    assert 0 <= results["projects_model"]["mrr"] <= 1


def test_same_seed_same_events_and_projects():
    campus = Campus(RecConfig(seed=9, students=100, days=30)).run()
    first_joins, first_purchases = list(campus.joins), list(campus.purchases)
    assert campus.rsvps
    again = Campus(RecConfig(seed=9, students=100, days=30)).run()
    assert again.joins == first_joins and again.purchases == first_purchases and again.rsvps == campus.rsvps


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


def test_events_matching_my_interests_and_friends_rank_first(client):
    body = {"now": NOW, "user": hub_user(), "candidates": [
        event(1, ["sports"], [{"id": 40, "major": "Design"}]),
        event(2, ["tech"], [{"id": 17, "major": "CS"}]),
    ]}
    response = client.post("/recommend/events", json=body)
    assert response.status_code == 200
    assert [r["id"] for r in response.get_json()["ranked"]] == [2, 1]


def test_projects_needing_my_skills_rank_first(client):
    body = {"now": NOW, "user": hub_user(), "candidates": [
        project(1, ["Marketing"], ["career"]),
        project(2, ["Laravel", "SQL"], ["tech"]),
    ]}
    response = client.post("/recommend/projects", json=body)
    assert response.status_code == 200
    assert [r["id"] for r in response.get_json()["ranked"]] == [2, 1]


def test_invalid_event_and_project_requests_return_422(client):
    assert client.post("/recommend/events", json={"user": {}, "candidates": [{"id": 1}]}).status_code == 422
    assert client.post("/recommend/projects", json={"user": {}, "candidates": [{"tags": []}]}).status_code == 422
    assert client.post("/recommend/events", json={"user": "x", "candidates": []}).status_code == 422
