
import os
import json
import joblib
import numpy as np
import pandas as pd

from sklearn.model_selection import train_test_split
from sklearn.compose import ColumnTransformer
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder
from sklearn.impute import SimpleImputer
from sklearn.ensemble import RandomForestRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score

from xgboost import XGBRegressor

# ==================================
# KONFIGURASI
# ==================================

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

DATA_PATH = os.path.join(
    BASE_DIR, "dataset", "data_barang.csv"
)

MODEL_DIR = os.path.join(BASE_DIR, "models")

os.makedirs(MODEL_DIR, exist_ok=True)

FEATURES = [
    "category",
    "brand",
    "model",
    "new_price",
    "age_years",
    "ram_gb",
    "storage_gb",
    "physical_condition",
    "functionality",
    "completeness"
]

TARGET = "market_price"

CATEGORICAL = [
    "category",
    "brand",
    "model",
    "physical_condition",
    "functionality",
    "completeness"
]

NUMERICAL = [
    "new_price",
    "age_years",
    "ram_gb",
    "storage_gb"
]

# ==================================
# MEMBACA DATASET
# ==================================

df = pd.read_csv(DATA_PATH)

df.columns = df.columns.str.strip()

required = FEATURES + [TARGET]

missing = set(required) - set(df.columns)

if missing:
    raise ValueError(
        f"Kolom dataset tidak lengkap: {missing}"
    )

df = df[required].copy()

for col in NUMERICAL + [TARGET]:
    df[col] = pd.to_numeric(
        df[col], errors="coerce"
    )

df = df.dropna(subset=[TARGET])

df = df[
    (df["market_price"] > 0) &
    (df["new_price"] > 0) &
    (df["age_years"] >= 0)
]

if len(df) < 30:
    raise ValueError(
        "Dataset minimal 30 baris untuk demo pelatihan. "
        "Untuk penelitian, gunakan dataset lebih besar "
        "dan representatif."
    )

X = df[FEATURES]
y = df[TARGET]

# ==================================
# PREPROCESSING
# ==================================

numeric_pipeline = Pipeline([
    ("imputer", SimpleImputer(strategy="median"))
])

categorical_pipeline = Pipeline([
    ("imputer", SimpleImputer(strategy="most_frequent")),
    ("encoder", OneHotEncoder(
        handle_unknown="ignore"
    ))
])

preprocessor = ColumnTransformer([
    ("num", numeric_pipeline, NUMERICAL),
    ("cat", categorical_pipeline, CATEGORICAL)
])

# ==================================
# SPLIT DATA
# ==================================

X_train, X_test, y_train, y_test = train_test_split(
    X,
    y,
    test_size=0.20,
    random_state=42
)

# ==================================
# MODEL
# ==================================

models = {
    "Random Forest Regression":
        RandomForestRegressor(
            n_estimators=300,
            max_depth=None,
            min_samples_split=2,
            random_state=42,
            n_jobs=-1
        ),

    "XGBoost Regression":
        XGBRegressor(
            n_estimators=300,
            max_depth=5,
            learning_rate=0.05,
            objective="reg:squarederror",
            random_state=42,
            n_jobs=-1
        )
}

results = []

best_mae = float("inf")
best_model = None
best_name = None

# ==================================
# TRAINING & EVALUATION
# ==================================

for name, estimator in models.items():

    pipeline = Pipeline([
        ("preprocessor", preprocessor),
        ("model", estimator)
    ])

    pipeline.fit(X_train, y_train)

    predictions = pipeline.predict(X_test)

    mae = mean_absolute_error(
        y_test, predictions
    )

    rmse = np.sqrt(
        mean_squared_error(y_test, predictions)
    )

    r2 = r2_score(
        y_test, predictions
    )

    print("\nMODEL:", name)
    print("MAE :", round(mae, 2))
    print("RMSE:", round(rmse, 2))
    print("R2  :", round(r2, 4))

    results.append({
        "model": name,
        "mae": float(mae),
        "rmse": float(rmse),
        "r2": float(r2)
    })

    if mae < best_mae:
        best_mae = mae
        best_model = pipeline
        best_name = name

# ==================================
# SAVE MODEL
# ==================================

joblib.dump(
    best_model,
    os.path.join(MODEL_DIR, "best_model.joblib")
)

metadata = {
    "best_model": best_name,
    "features": FEATURES,
    "target": TARGET,
    "results": results,
    "training_rows": len(df),
    "test_rows": len(X_test)
}

with open(
    os.path.join(MODEL_DIR, "metadata.json"),
    "w"
) as f:
    json.dump(metadata, f, indent=4)

print("\n==============================")
print("MODEL TERBAIK:", best_name)
print("MAE:", round(best_mae, 2))
print("MODEL BERHASIL DISIMPAN")
print("==============================")