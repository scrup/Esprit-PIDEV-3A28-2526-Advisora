import json
import pickle
from pathlib import Path

import pandas as pd
from sklearn.compose import ColumnTransformer
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    top_k_accuracy_score,
)
from sklearn.model_selection import train_test_split
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import LabelEncoder, MaxAbsScaler, OneHotEncoder

BASE_DIR = Path(__file__).resolve().parent
DATASET_PATH = BASE_DIR / "dataset" / "training_data.csv"
MODELS_DIR = BASE_DIR / "models"
REPORTS_DIR = MODELS_DIR / "reports"

MODELS_DIR.mkdir(exist_ok=True)
REPORTS_DIR.mkdir(exist_ok=True)


def normalize_feature_text(value: object) -> str:
    return str(value or "").strip().lower()


def normalize_label_text(value: object) -> str:
    return str(value or "").strip()


def load_dataset(dataset_path: Path) -> pd.DataFrame:
    expected_columns = [
        "budgetProj",
        "typeProj",
        "avancementProj",
        "stateProj",
        "descriptionProj",
        "nomStrategie",
    ]

    raw_lines = dataset_path.read_text(encoding="utf-8-sig").splitlines()
    if not raw_lines:
        raise ValueError(f"Dataset vide : {dataset_path}")

    header = [column.strip() for column in raw_lines[0].split(",")]
    if header != expected_columns:
        raise ValueError(
            f"En-têtes invalides dans le dataset. Attendu: {expected_columns}, reçu: {header}"
        )

    rows: list[dict[str, str]] = []

    for line_number, raw_line in enumerate(raw_lines[1:], start=2):
        line = raw_line.strip()

        if not line or line.startswith("#"):
            continue

        parts = [part.strip() for part in line.split(",")]

        if len(parts) < 6:
            raise ValueError(
                f"Ligne {line_number} invalide dans le dataset: {raw_line}"
            )

        row = {
            "budgetProj": parts[0],
            "typeProj": parts[1],
            "avancementProj": parts[2],
            "stateProj": parts[3],
            "descriptionProj": ",".join(parts[4:-1]).strip(),
            "nomStrategie": parts[-1],
        }
        rows.append(row)

    return pd.DataFrame(rows, columns=expected_columns)


def build_text_features(type_proj: str, state_proj: str, description_proj: str) -> str:
    return " ".join(
        part for part in [type_proj, state_proj, description_proj] if part
    ).strip()


def clean_dataset(df: pd.DataFrame) -> pd.DataFrame:
    df = df.copy()
    df = df.dropna(how="all")

    expected_columns = [
        "budgetProj",
        "typeProj",
        "avancementProj",
        "stateProj",
        "descriptionProj",
        "nomStrategie",
    ]
    missing = [col for col in expected_columns if col not in df.columns]
    if missing:
        raise ValueError(f"Colonnes manquantes dans le dataset: {missing}")

    df["budgetProj"] = pd.to_numeric(df["budgetProj"], errors="coerce")
    df["avancementProj"] = pd.to_numeric(df["avancementProj"], errors="coerce")

    df["typeProj"] = df["typeProj"].apply(normalize_feature_text)
    df["stateProj"] = df["stateProj"].apply(normalize_feature_text)
    df["descriptionProj"] = df["descriptionProj"].apply(normalize_feature_text)
    df["nomStrategie"] = df["nomStrategie"].apply(normalize_label_text)

    df = df.dropna(subset=["budgetProj", "avancementProj"])
    df = df[df["nomStrategie"] != ""]
    df = df[df["typeProj"] != ""]
    df = df[df["stateProj"] != ""]
    df = df[df["descriptionProj"] != ""]

    df["textFeatures"] = df.apply(
        lambda row: build_text_features(
            row["typeProj"],
            row["stateProj"],
            row["descriptionProj"],
        ),
        axis=1,
    )

    return df.reset_index(drop=True)


def build_model() -> Pipeline:
    preprocessor = ColumnTransformer(
        transformers=[
            (
                "text",
                TfidfVectorizer(
                    ngram_range=(1, 2),
                    min_df=1,
                    sublinear_tf=True,
                ),
                "textFeatures",
            ),
            (
                "categorical",
                OneHotEncoder(handle_unknown="ignore"),
                ["typeProj", "stateProj"],
            ),
            (
                "numeric",
                MaxAbsScaler(),
                ["budgetProj", "avancementProj"],
            ),
        ],
        remainder="drop",
    )

    classifier = LogisticRegression(
        max_iter=10000,
        class_weight="balanced",
        solver="saga",
    )

    return Pipeline(
        steps=[
            ("preprocessor", preprocessor),
            ("classifier", classifier),
        ]
    )


def save_diagnostics(
    y_test,
    predictions,
    probabilities,
    target_encoder: LabelEncoder,
) -> dict[str, float]:
    class_indices = list(range(len(target_encoder.classes_)))
    class_names = list(target_encoder.classes_)

    accuracy = accuracy_score(y_test, predictions)
    macro_f1 = f1_score(y_test, predictions, average="macro", zero_division=0)
    weighted_f1 = f1_score(y_test, predictions, average="weighted", zero_division=0)

    metrics: dict[str, float] = {
        "accuracy": round(float(accuracy), 4),
        "macro_f1": round(float(macro_f1), 4),
        "weighted_f1": round(float(weighted_f1), 4),
    }

    if probabilities is not None:
        top_3_accuracy = top_k_accuracy_score(
            y_test,
            probabilities,
            k=min(3, len(class_names)),
            labels=class_indices,
        )
        metrics["top_3_accuracy"] = round(float(top_3_accuracy), 4)

    report = classification_report(
        y_test,
        predictions,
        labels=class_indices,
        target_names=class_names,
        zero_division=0,
        output_dict=True,
    )

    confusion = confusion_matrix(
        y_test,
        predictions,
        labels=class_indices,
    )

    confusion_df = pd.DataFrame(confusion, index=class_names, columns=class_names)
    confusion_df.to_csv(REPORTS_DIR / "confusion_matrix.csv", encoding="utf-8-sig")

    (REPORTS_DIR / "classification_report.json").write_text(
        json.dumps(report, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    (REPORTS_DIR / "training_metrics.json").write_text(
        json.dumps(metrics, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    return metrics


def main() -> None:
    if not DATASET_PATH.exists():
        raise FileNotFoundError(f"Dataset introuvable : {DATASET_PATH}")

    df = clean_dataset(load_dataset(DATASET_PATH))

    feature_columns = [
        "budgetProj",
        "avancementProj",
        "typeProj",
        "stateProj",
        "descriptionProj",
        "textFeatures",
    ]
    target_column = "nomStrategie"

    X = df[feature_columns]
    y_text = df[target_column].astype(str)

    target_encoder = LabelEncoder()
    y = target_encoder.fit_transform(y_text)

    model = build_model()

    if len(df) >= 20 and y_text.value_counts().min() >= 2:
        X_train, X_test, y_train, y_test = train_test_split(
            X,
            y,
            test_size=0.2,
            random_state=42,
            stratify=y,
        )
    else:
        X_train, X_test, y_train, y_test = X, X, y, y

    model.fit(X_train, y_train)

    predictions = model.predict(X_test)
    probabilities = model.predict_proba(X_test) if hasattr(model, "predict_proba") else None
    metrics = save_diagnostics(y_test, predictions, probabilities, target_encoder)

    print(f"Accuracy: {metrics['accuracy']:.4f}")
    print(f"Macro F1: {metrics['macro_f1']:.4f}")
    print(f"Weighted F1: {metrics['weighted_f1']:.4f}")
    if "top_3_accuracy" in metrics:
        print(f"Top-3 Accuracy: {metrics['top_3_accuracy']:.4f}")

    with open(MODELS_DIR / "strategy_model.pkl", "wb") as model_file:
        pickle.dump(model, model_file)

    with open(MODELS_DIR / "label_encoders.pkl", "wb") as encoders_file:
        pickle.dump({}, encoders_file)

    with open(MODELS_DIR / "target_encoder.pkl", "wb") as target_encoder_file:
        pickle.dump(target_encoder, target_encoder_file)

    with open(MODELS_DIR / "feature_columns.pkl", "wb") as feature_columns_file:
        pickle.dump(feature_columns, feature_columns_file)

    print(f"Matrice de confusion sauvegardée: {REPORTS_DIR / 'confusion_matrix.csv'}")
    print(f"Rapport de classification sauvegardé: {REPORTS_DIR / 'classification_report.json'}")
    print(f"Métriques sauvegardées: {REPORTS_DIR / 'training_metrics.json'}")
    print("Modèle entraîné et sauvegardé avec succès.")


if __name__ == "__main__":
    main()
