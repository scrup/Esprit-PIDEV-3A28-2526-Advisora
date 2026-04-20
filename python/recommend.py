import json
import math
import pickle
import re
import sys
import unicodedata
from pathlib import Path
from typing import Any

import pandas as pd

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

BASE_DIR = Path(__file__).resolve().parent
MODELS_DIR = BASE_DIR / "models"

MODEL_PATH = MODELS_DIR / "strategy_model.pkl"
TARGET_ENCODER_PATH = MODELS_DIR / "target_encoder.pkl"
FEATURE_COLUMNS_PATH = MODELS_DIR / "feature_columns.pkl"

TYPE_KEYWORDS = {
    "DIGITALE": [
        "digital",
        "digitale",
        "numerique",
        "cloud",
        "saas",
        "web",
        "mobile",
        "application",
        "plateforme",
        "erp",
        "crm",
        "data",
        "automatisation",
        "logiciel",
    ],
    "FINANCIERE": [
        "finance",
        "financier",
        "financiere",
        "budget",
        "rentabilite",
        "profit",
        "revenu",
        "marge",
        "tresorerie",
        "investissement",
    ],
    "OPERATIONNELLE": [
        "optimisation",
        "operation",
        "operationnelle",
        "process",
        "processus",
        "efficacite",
        "production",
        "qualite",
        "logistique",
        "lean",
        "agilite",
        "reactivite",
    ],
    "RH": [
        "rh",
        "ressources humaines",
        "recrutement",
        "talent",
        "formation",
        "employe",
        "collaborateur",
        "culture entreprise",
        "engagement",
    ],
    "CROISSANCE": [
        "croissance",
        "expansion",
        "diversification",
        "marche",
        "penetration",
        "partenariat",
        "alliance",
        "international",
        "developpement",
    ],
    "COMMERCIALE": [
        "vente",
        "commercial",
        "commerciale",
        "client",
        "fidelisation",
        "prospection",
        "acquisition",
        "conversion",
        "offre",
        "pricing",
        "prix",
        "b2b",
    ],
    "JURIDIQUE": [
        "juridique",
        "contrat",
        "conformite",
        "risque",
        "reglementation",
        "compliance",
        "rgpd",
        "litige",
        "securite",
    ],
    "MARKETING": [
        "marketing",
        "campagne",
        "publicite",
        "marque",
        "positionnement",
        "image de marque",
        "ux",
    ],
}

TYPE_DEFAULTS = {
    "MARKETING": {"gain": 18.0, "duree": 4},
    "FINANCIERE": {"gain": 14.0, "duree": 6},
    "OPERATIONNELLE": {"gain": 20.0, "duree": 8},
    "DIGITALE": {"gain": 28.0, "duree": 6},
    "RH": {"gain": 12.0, "duree": 9},
    "CROISSANCE": {"gain": 32.0, "duree": 12},
    "COMMERCIALE": {"gain": 22.0, "duree": 5},
    "JURIDIQUE": {"gain": 10.0, "duree": 3},
}


def to_float(value: Any, default: float = 0.0) -> float:
    if value is None:
        return default
    if isinstance(value, (int, float)):
        return float(value)

    text = str(value).strip()
    if not text:
        return default

    text = text.replace(" ", "").replace(",", ".")
    try:
        return float(text)
    except (TypeError, ValueError):
        return default


def to_nullable_float(value: Any) -> float | None:
    if value is None:
        return None
    text = str(value).strip()
    if not text:
        return None
    try:
        return float(text.replace(" ", "").replace(",", "."))
    except (TypeError, ValueError):
        return None


def to_nullable_int(value: Any) -> int | None:
    numeric_value = to_nullable_float(value)
    if numeric_value is None:
        return None
    return int(round(numeric_value))


def clamp(value: float, minimum: float, maximum: float) -> float:
    return max(minimum, min(maximum, value))


def strip_accents(value: str) -> str:
    normalized = unicodedata.normalize("NFKD", value)
    return "".join(char for char in normalized if not unicodedata.combining(char))


def normalize_text(value: Any) -> str:
    text = str(value or "").strip().lower()
    text = strip_accents(text)
    text = re.sub(r"\s+", " ", text)
    return text


def normalize_feature_text(value: Any) -> str:
    return normalize_text(value)


def build_text_features(type_proj: str, state_proj: str, description_proj: str) -> str:
    return " ".join(part for part in [type_proj, state_proj, description_proj] if part).strip()


def softmax(scores: list[float]) -> list[float]:
    if not scores:
        return []

    max_score = max(scores)
    exps = [math.exp(score - max_score) for score in scores]
    total = sum(exps) or 1.0
    return [exp_value / total for exp_value in exps]


def normalize_scores_for_output(scores: list[float]) -> list[float]:
    if not scores:
        return []
    total = sum(score for score in scores if score > 0)
    if total > 0:
        return [max(score, 0.0) / total for score in scores]
    return softmax(scores)


def score_type_from_keywords(strategy_name: str, project_description: str, project_type: str) -> dict[str, int]:
    text = " ".join(
        [
            normalize_text(strategy_name),
            normalize_text(project_description),
            normalize_text(project_type),
        ]
    )

    scores = {strategy_type: 0 for strategy_type in TYPE_KEYWORDS}
    for strategy_type, keywords in TYPE_KEYWORDS.items():
        for keyword in keywords:
            if normalize_text(keyword) in text:
                scores[strategy_type] += 1
    return scores


def infer_strategy_type(strategy_name: str, project_description: str, project_type: str) -> str:
    scores = score_type_from_keywords(strategy_name, project_description, project_type)
    best_type = max(scores, key=scores.get)
    if scores[best_type] == 0:
        return "COMMERCIALE"
    return best_type


def enrich_from_type(strategy_type: str, budget: float, avancement: float) -> tuple[float, int]:
    defaults = TYPE_DEFAULTS.get(strategy_type, {"gain": 15.0, "duree": 6})
    gain = float(defaults["gain"])
    duree = int(defaults["duree"])

    if budget >= 100000:
        gain += 3.0
        duree += 2
    if avancement >= 80:
        duree = max(2, duree - 2)
        gain = max(8.0, gain - 2.0)

    return round(gain, 2), int(duree)


def load_model_artifacts() -> tuple[Any, Any, list[str]]:
    missing_paths = [
        path
        for path in [MODEL_PATH, TARGET_ENCODER_PATH, FEATURE_COLUMNS_PATH]
        if not path.exists()
    ]
    if missing_paths:
        missing = ", ".join(str(path) for path in missing_paths)
        raise FileNotFoundError(
            f"Model artifacts not found: {missing}. Run python/train_model.py first."
        )

    with open(MODEL_PATH, "rb") as model_file:
        model = pickle.load(model_file)
    with open(TARGET_ENCODER_PATH, "rb") as target_encoder_file:
        target_encoder = pickle.load(target_encoder_file)
    with open(FEATURE_COLUMNS_PATH, "rb") as feature_columns_file:
        feature_columns = pickle.load(feature_columns_file)

    if not isinstance(feature_columns, list):
        raise ValueError("Invalid feature columns artifact.")

    return model, target_encoder, feature_columns


def build_model_input(data: dict[str, Any], feature_columns: list[str]) -> pd.DataFrame:
    budget = max(0.0, to_float(data.get("budgetProj", 0)))
    avancement = clamp(to_float(data.get("avancementProj", 0)), 0.0, 100.0)
    type_proj = normalize_feature_text(data.get("typeProj", ""))
    state_proj = normalize_feature_text(data.get("stateProj", "PENDING") or "PENDING")
    description_proj = normalize_feature_text(data.get("descriptionProj", ""))

    row: dict[str, Any] = {
        "budgetProj": budget,
        "avancementProj": avancement,
        "typeProj": type_proj,
        "stateProj": state_proj,
        "descriptionProj": description_proj,
        "textFeatures": build_text_features(type_proj, state_proj, description_proj),
    }

    normalized_row: dict[str, Any] = {}
    for column in feature_columns:
        if column in row:
            normalized_row[column] = row[column]
        elif column in {"budgetProj", "avancementProj"}:
            normalized_row[column] = 0.0
        else:
            normalized_row[column] = ""

    return pd.DataFrame([normalized_row], columns=feature_columns)


def predict_model_scores(data: dict[str, Any]) -> list[dict[str, Any]]:
    model, target_encoder, feature_columns = load_model_artifacts()
    model_input = build_model_input(data, feature_columns)

    if not hasattr(model, "predict_proba"):
        raise ValueError("Loaded model does not support predict_proba.")

    probabilities = model.predict_proba(model_input)
    if len(probabilities) == 0:
        return []

    labels = [str(label) for label in target_encoder.classes_]
    scores = [
        {
            "strategy_name": label,
            "score": float(probability),
            "type": None,
            "gain_estime": None,
            "duree_terme": None,
        }
        for label, probability in zip(labels, probabilities[0])
    ]
    scores.sort(key=lambda item: item["score"], reverse=True)
    return scores


def normalize_db_strategies(raw_candidates: Any) -> list[dict[str, Any]]:
    if not isinstance(raw_candidates, list):
        return []

    normalized_candidates: list[dict[str, Any]] = []
    seen_names: set[str] = set()

    for raw_candidate in raw_candidates:
        if not isinstance(raw_candidate, dict):
            continue

        strategy_name = str(raw_candidate.get("nomStrategie", "") or "").strip()
        if not strategy_name:
            continue

        strategy_key = normalize_text(strategy_name)
        if strategy_key in seen_names:
            continue
        seen_names.add(strategy_key)

        normalized_candidates.append(
            {
                "nomStrategie": strategy_name,
                "type": str(raw_candidate.get("type", "") or "").strip() or None,
                "gainEstime": to_nullable_float(raw_candidate.get("gainEstime")),
                "DureeTerme": to_nullable_int(
                    raw_candidate.get("DureeTerme", raw_candidate.get("dureeTerme"))
                ),
            }
        )

    return normalized_candidates


def compute_db_strategy_scores(
    data: dict[str, Any],
    model_scores: list[dict[str, Any]],
) -> list[dict[str, Any]]:
    db_candidates = normalize_db_strategies(data.get("dbStrategies"))
    if not db_candidates:
        return []

    model_score_by_name = {
        normalize_text(item["strategy_name"]): float(item["score"]) for item in model_scores
    }

    scored_db_candidates: list[dict[str, Any]] = []
    for index, candidate in enumerate(db_candidates):
        strategy_name = candidate["nomStrategie"]
        model_score = model_score_by_name.get(normalize_text(strategy_name), 0.0)
        # Keep DB order (newest first from backend) as tie-breaker.
        recency_bonus = max(0.0, 0.02 - (index * 0.001))
        final_score = model_score + recency_bonus

        scored_db_candidates.append(
            {
                "strategy_name": strategy_name,
                "score": final_score,
                "type": candidate.get("type"),
                "gain_estime": candidate.get("gainEstime"),
                "duree_terme": candidate.get("DureeTerme"),
            }
        )

    scored_db_candidates.sort(key=lambda item: item["score"], reverse=True)
    return scored_db_candidates


def recommend_strategy(data: dict[str, Any]) -> dict[str, Any]:
    model_scores = predict_model_scores(data)
    db_scored_candidates = compute_db_strategy_scores(data, model_scores)

    ranking_pool = db_scored_candidates if db_scored_candidates else model_scores
    if not ranking_pool:
        raise ValueError("No strategy could be predicted from the trained model.")

    best = ranking_pool[0]

    budget = max(0.0, to_float(data.get("budgetProj", 0)))
    avancement = clamp(to_float(data.get("avancementProj", 0)), 0.0, 100.0)
    type_proj_raw = str(data.get("typeProj", "") or "").strip()
    description_proj = str(data.get("descriptionProj", "") or "").strip()

    predicted_strategy = best["strategy_name"]
    predicted_type = (
        str(best.get("type", "") or "").strip()
        or infer_strategy_type(predicted_strategy, description_proj, type_proj_raw)
    )

    gain_estime = best.get("gain_estime")
    duree = best.get("duree_terme")
    if gain_estime is None or duree is None:
        fallback_gain, fallback_duree = enrich_from_type(predicted_type, budget, avancement)
        gain_estime = fallback_gain if gain_estime is None else float(gain_estime)
        duree = fallback_duree if duree is None else int(duree)
    else:
        gain_estime = float(gain_estime)
        duree = int(duree)

    top_candidates = ranking_pool[:3]
    probabilities = normalize_scores_for_output([float(candidate["score"]) for candidate in top_candidates])

    top_3 = []
    for candidate, probability in zip(top_candidates, probabilities):
        label = candidate["strategy_name"]
        inferred_type = (
            str(candidate.get("type", "") or "").strip()
            or infer_strategy_type(label, description_proj, type_proj_raw)
        )
        top_3.append(
            {
                "label": label,
                "score": round(float(probability), 4),
                "type": inferred_type,
            }
        )

    return {
        "nomStrategie": predicted_strategy,
        "type": predicted_type,
        "budgetTotal": round(budget, 2),
        "gainEstime": round(gain_estime, 2),
        "DureeTerme": int(duree),
        "statusStrategie": "En_attente",
        "top_3": top_3,
    }


if __name__ == "__main__":
    try:
        raw_input = sys.argv[1] if len(sys.argv) > 1 else sys.stdin.read()
        data = json.loads(raw_input or "{}")

        if not isinstance(data, dict):
            raise ValueError("JSON payload must be an object.")

        result = recommend_strategy(data)
        print(json.dumps(result, ensure_ascii=True))
    except Exception as error:
        print(
            json.dumps(
                {
                    "error": True,
                    "message": str(error),
                },
                ensure_ascii=True,
            )
        )
        sys.exit(1)
