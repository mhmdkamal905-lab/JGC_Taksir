
from flask import Flask, request, jsonify
import joblib
import os
import json
import pandas as pd

app = Flask(__name__)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

MODEL_PATH = os.path.join(
    BASE_DIR, "models", "best_model.joblib"
)

META_PATH = os.path.join(
    BASE_DIR, "models", "metadata.json"
)

model = joblib.load(MODEL_PATH)

with open(META_PATH, "r") as f:
    metadata = json.load(f)


@app.route("/", methods=["GET"])
def home():
    return jsonify({
        "application": "JGC Machine Learning API",
        "status": "running",
        "model": metadata["best_model"]
    })


@app.route("/predict", methods=["POST"])
def predict():

    try:
        data = request.get_json(silent=True)

        if not isinstance(data, dict):
            return jsonify({
                "success": False,
                "message": "Input JSON tidak valid"
            }), 400

        features = metadata["features"]

        if any(
            key not in data or data[key] is None
            for key in features
        ):
            return jsonify({
                "success": False,
                "message": "Data input belum lengkap"
            }), 400

        numeric_fields = [
            "new_price",
            "age_years",
            "ram_gb",
            "storage_gb"
        ]

        for field in numeric_fields:
            data[field] = float(data[field])

        if (
            data["new_price"] <= 0 or
            data["age_years"] < 0 or
            data["ram_gb"] < 0 or
            data["storage_gb"] < 0
        ):
            return jsonify({
                "success": False,
                "message": "Nilai numerik tidak valid"
            }), 400

        input_data = pd.DataFrame(
            [{key: data[key] for key in features}]
        )

        prediction = float(
            model.predict(input_data)[0]
        )

        prediction = max(0, prediction)

        return jsonify({
            "success": True,
            "predicted_market_price": round(
                prediction, 2
            ),
            "model": metadata["best_model"]
        })

    except Exception as e:

        app.logger.exception("Prediction error")

        return jsonify({
            "success": False,
            "message": "Terjadi kesalahan saat prediksi"
        }), 500


if __name__ == "__main__":

    app.run(
        host="127.0.0.1",
        port=5000,
        debug=False
    )