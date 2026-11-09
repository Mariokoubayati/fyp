"""Flask service the Laravel backend calls.

    python -m unicircle_ml.service            # http://127.0.0.1:5001

GET  /health               -> {"status": "ok", "model_version": ..., "recommender_version": ...}
POST /trust/assess         -> default risk for one student at one or more loan amounts
POST /recommend/groups     -> open study groups ranked for one student
POST /recommend/listings   -> available listings ranked for one student (formats in recommend.py)

Request:
    {"history": {...shared history format...}, "amounts": [20, 100]}
Response:
    {"model_version": "trust-hgb-1", "risk_threshold": 0.3,
     "assessments": [{"amount": 20, "p_default": 0.07, "allowed": true}, ...]}

Set ML_SERVICE_TOKEN to require "Authorization: Bearer <token>".
"""

from __future__ import annotations

import os
from pathlib import Path

import joblib
import numpy as np
from flask import Flask, abort, jsonify, request

from .features import extract, vector
from .history import History
from .recommend import group_features, listing_features, rank

MODELS = Path(__file__).resolve().parent.parent / "models"
DEFAULT_MODEL = MODELS / "trust_model.joblib"
DEFAULT_RECOMMENDER = MODELS / "recommender.joblib"
MAX_CANDIDATES = 500


def create_app(model_path: Path | str | None = None, token: str | None = None, recommender_path: Path | str | None = None) -> Flask:
    app = Flask(__name__)
    bundle = joblib.load(model_path or os.environ.get("TRUST_MODEL_PATH", DEFAULT_MODEL))
    model, features, version, threshold = bundle["model"], bundle["features"], bundle["version"], bundle["threshold"]
    recommender = joblib.load(recommender_path or os.environ.get("RECOMMENDER_PATH", DEFAULT_RECOMMENDER))
    token = token if token is not None else os.environ.get("ML_SERVICE_TOKEN", "")

    @app.before_request
    def check_token():
        if token and request.endpoint != "health" and request.headers.get("Authorization") != f"Bearer {token}":
            abort(401)

    @app.get("/health")
    def health():
        return jsonify(status="ok", model_version=version, recommender_version=recommender["version"])

    @app.post("/trust/assess")
    def assess():
        body = request.get_json(silent=True) or {}
        amounts = body.get("amounts") or [20]
        if not isinstance(body.get("history"), dict) or not all(isinstance(a, (int, float)) and 0 < a <= 1000 for a in amounts):
            return jsonify(error="Send a history object and amounts between 0 and 1000."), 422

        try:
            history = History.from_dict(body["history"])
        except (KeyError, TypeError, ValueError) as exc:
            return jsonify(error=f"Invalid history: {exc}"), 422

        rows = np.array([vector(extract(history, float(amount))) for amount in amounts])
        assert rows.shape[1] == len(features)
        risks = model.predict_proba(rows)[:, 1]

        return jsonify(
            model_version=version,
            risk_threshold=threshold,
            assessments=[
                {"amount": float(amount), "p_default": round(float(p), 4), "allowed": bool(p < threshold)}
                for amount, p in zip(amounts, risks)
            ],
        )

    def recommendation_request() -> tuple[dict, list[dict], int] | None:
        body = request.get_json(silent=True) or {}
        user, candidates = body.get("user"), body.get("candidates")
        if not isinstance(user, dict) or not isinstance(candidates, list) or len(candidates) > MAX_CANDIDATES:
            return None
        return user, candidates, int(body.get("now") or 0)

    @app.post("/recommend/groups")
    def recommend_groups():
        parsed = recommendation_request()
        if parsed is None:
            return jsonify(error=f"Send a user object and at most {MAX_CANDIDATES} candidates."), 422
        user, candidates, now = parsed
        try:
            rows = [group_features(user, group, now) for group in candidates]
        except (KeyError, TypeError, ValueError) as exc:
            return jsonify(error=f"Invalid group: {exc}"), 422
        ranked = rank(recommender["groups"], recommender["group_features"], rows, [g["id"] for g in candidates])
        return jsonify(recommender_version=recommender["version"], ranked=ranked)

    @app.post("/recommend/listings")
    def recommend_listings():
        parsed = recommendation_request()
        if parsed is None:
            return jsonify(error=f"Send a user object and at most {MAX_CANDIDATES} candidates."), 422
        user, candidates, now = parsed
        try:
            rows = listing_features(user, candidates, now)
        except (KeyError, TypeError, ValueError) as exc:
            return jsonify(error=f"Invalid listing: {exc}"), 422
        ranked = rank(recommender["listings"], recommender["listing_features"], rows, [l["id"] for l in candidates])
        return jsonify(recommender_version=recommender["version"], ranked=ranked)

    return app


if __name__ == "__main__":
    create_app().run(host=os.environ.get("ML_HOST", "127.0.0.1"), port=int(os.environ.get("ML_PORT", "5001")))
