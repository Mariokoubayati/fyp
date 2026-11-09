"""Recommender: ranks study groups and marketplace listings for one student.

Laravel sends the student's profile and the candidate items as plain JSON;
this module turns each (student, item) pair into features and scores it with a
small learned model. Same principle as the trust model: one feature
implementation, in Python.

Group request:
{
  "now": 1762400000,
  "user": {"id": 1, "major": "CS", "study_year": 2, "course_ids": [3, 5],
           "co_members": {"17": 2, "21": 1},       # student id -> groups shared before
           "past_join_hours": [18, 19]},
  "candidates": [{"id": 9, "course_id": 3, "starts_at": ..., "capacity": 6,
                  "members": [{"id": 17, "major": "CS", "study_year": 2, "course_ids": [3]}]}]
}

Listing request:
{
  "now": ...,
  "user": {"id": 1, "course_ids": [3], "course_names": ["Databases"],
           "purchases": [{"title": "...", "category": "books", "price": 15}],
           "co_member_ids": [17, 21]},
  "candidates": [{"id": 4, "title": "...", "description": "...", "category": "books",
                  "course_id": 3, "price": 12, "seller_id": 17, "created_at": ...}]
}
"""

from __future__ import annotations

from collections import Counter
from statistics import median

import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

DAY = 86400

GROUP_FEATURES = [
    "course_enrolled",
    "classmates",
    "co_members",
    "same_major_share",
    "same_year_share",
    "fill_ratio",
    "days_until_start",
    "hour_distance",
    "member_count",
]

LISTING_FEATURES = [
    "course_enrolled",
    "text_similarity",
    "category_affinity",
    "relative_price",
    "age_days",
    "seller_is_co_member",
]


def group_features(user: dict, group: dict, now: int) -> dict[str, float]:
    courses = set(user.get("course_ids", []))
    members = [m for m in group.get("members", []) if m.get("id") != user.get("id")]
    co = {int(k): v for k, v in (user.get("co_members") or {}).items()}
    hours = user.get("past_join_hours") or []
    start_hour = (int(group["starts_at"]) // 3600) % 24

    def share(predicate) -> float:
        return sum(1 for m in members if predicate(m)) / len(members) if members else 0.0

    return {
        "course_enrolled": 1.0 if group.get("course_id") in courses else 0.0,
        "classmates": float(sum(1 for m in members if courses & set(m.get("course_ids", [])))),
        "co_members": float(sum(min(3, co.get(int(m["id"]), 0)) for m in members)),
        "same_major_share": share(lambda m: user.get("major") and m.get("major") == user.get("major")),
        "same_year_share": share(lambda m: user.get("study_year") and m.get("study_year") == user.get("study_year")),
        "fill_ratio": len(members) / max(1, int(group.get("capacity", 6))),
        "days_until_start": max(0.0, (int(group["starts_at"]) - now) / DAY),
        # Circular distance to the hours this student usually joins at; 6 when unknown.
        "hour_distance": float(min(min(abs(start_hour - h), 24 - abs(start_hour - h)) for h in hours)) if hours else 6.0,
        "member_count": float(len(members)),
    }


def listing_features(user: dict, listings: list[dict], now: int) -> list[dict[str, float]]:
    """Features for every candidate listing at once (TF-IDF is fitted on the request itself)."""
    courses = set(user.get("course_ids", []))
    purchases = user.get("purchases") or []
    co_members = set(user.get("co_member_ids") or [])

    profile = " ".join([*user.get("course_names", []), *(p.get("title", "") for p in purchases)]).strip()
    texts = [f"{l.get('title', '')} {l.get('description') or ''}" for l in listings]
    if profile and listings:
        matrix = TfidfVectorizer(stop_words="english", sublinear_tf=True).fit_transform([profile, *texts])
        similarity = cosine_similarity(matrix[0], matrix[1:]).ravel()
    else:
        similarity = np.zeros(len(listings))

    categories = Counter(p.get("category") for p in purchases)
    prices: dict[str, list[float]] = {}
    for listing in listings:
        prices.setdefault(listing.get("category"), []).append(float(listing.get("price", 0)))
    typical = {category: max(1.0, median(values)) for category, values in prices.items()}

    return [
        {
            "course_enrolled": 1.0 if listing.get("course_id") in courses else 0.0,
            "text_similarity": float(sim),
            "category_affinity": categories[listing.get("category")] / len(purchases) if purchases else 0.0,
            "relative_price": float(listing.get("price", 0)) / typical[listing.get("category")],
            "age_days": max(0.0, (now - int(listing.get("created_at", now))) / DAY),
            "seller_is_co_member": 1.0 if listing.get("seller_id") in co_members else 0.0,
        }
        for listing, sim in zip(listings, similarity)
    ]


def rank(model, feature_names: list[str], rows: list[dict[str, float]], ids: list[int]) -> list[dict]:
    if not rows:
        return []
    X = np.array([[row[name] for name in feature_names] for row in rows])
    scores = model.predict_proba(X)[:, 1]
    order = np.argsort(-scores, kind="stable")
    return [{"id": int(ids[i]), "score": round(float(scores[i]), 4)} for i in order]
