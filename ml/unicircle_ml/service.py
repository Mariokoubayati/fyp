"""Flask service the Laravel backend calls.

    python -m unicircle_ml.service            # http://127.0.0.1:5001

GET  /health         -> {"status": "ok", "model_version": ...}
POST /trust/assess   -> default risk for one student at one or more loan amounts

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

DEFAULT_MODEL = Path(__file__).resolve().parent.parent / "models" / "trust_model.joblib"


def create_app(model_path: Path | str | None = None, token: str | None = None) -> Flask:
    app = Flask(__name__)
    bundle = joblib.load(model_path or os.environ.get("TRUST_MODEL_PATH", DEFAULT_MODEL))
    model, features, version, threshold = bundle["model"], bundle["features"], bundle["version"], bundle["threshold"]
    token = token if token is not None else os.environ.get("ML_SERVICE_TOKEN", "")

    @app.before_request
    def check_token():
        if token and request.endpoint != "health" and request.headers.get("Authorization") != f"Bearer {token}":
            abort(401)

    @app.get("/health")
    def health():
        return jsonify(status="ok", model_version=version)

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

    return app


if __name__ == "__main__":
    create_app().run(host=os.environ.get("ML_HOST", "127.0.0.1"), port=int(os.environ.get("ML_PORT", "5001")))
