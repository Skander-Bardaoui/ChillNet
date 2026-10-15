"""Point d'entrée FastAPI du microservice IA canicule.

Laravel envoie un CONTEXTE BRUT et reçoit un niveau final + une explication.
La classification combine un modèle scikit-learn et les règles déterministes,
avec un PLANCHER DE SÉCURITÉ : le niveau final n'est jamais inférieur aux règles.

Lancement : ``uvicorn app.main:app --port 8001`` (depuis ai-service/).
"""

from __future__ import annotations

import csv
import subprocess
import sys
import threading
from typing import Any

from fastapi import Depends, FastAPI, Header, HTTPException

from . import rules
from .config import BASE_DIR, settings
from .features import FEATURE_ORDER, build_features
from .model import VigilanceModel
from .nlg import generer_message
from .schemas import (
    AnalyzeRequest,
    AnalyzeResponse,
    BatchRequest,
    BatchResponse,
    MessageRequest,
    MessageResponse,
    ModelInfoResponse,
)
from .coupures import anomalies as coupures_anomalies
from .coupures import etr as coupures_etr
from .coupures import risk as coupures_risk
from .coupures.etr import CoupureEtrModel
from .coupures.model import CoupureRiskModel
from .coupures.schemas import (
    AnomaliesRequest,
    AnomaliesResponse,
    EtrRequest,
    EtrResponse,
    Incident,
    InsightsResponse,
    RiskRequest,
    RiskResponse,
    ZoneResultat,
)

ORDRE: dict[str, int] = {"normal": 0, "jaune": 1, "orange": 2, "rouge": 3}

app = FastAPI(title="ChillNet — IA vigilance canicule", version="1.0.0")

model = VigilanceModel(settings.model_path, settings.metadata_path)

# Modèle du module 2 (coupures). Artefacts DÉDIÉS (`artifacts/coupures/`). Le
# chargement ne lève jamais : en l'absence d'artefact, `available` vaut False et
# le service retombe sur les règles déterministes (plancher de sécurité).
coupures_model = CoupureRiskModel(
    settings.coupures_model_path, settings.coupures_metadata_path
)

# Estimateur ETR (temps de rétablissement) du module 2 — artefact dédié.
etr_model = CoupureEtrModel(settings.coupures_etr_model_path)


def require_token(x_ai_token: str | None = Header(default=None)) -> None:
    """Vérifie l'en-tête X-AI-TOKEN partagé avec Laravel.

    Si aucun jeton n'est configuré (``AI_SERVICE_TOKEN`` vide), l'accès est
    laissé ouvert — réservé au développement local.
    """

    expected = settings.token

    if expected and x_ai_token != expected:
        raise HTTPException(status_code=401, detail="jeton IA invalide ou absent")


def _plus_grave(a: str, b: str) -> str:
    return a if ORDRE.get(a, 0) >= ORDRE.get(b, 0) else b


def _raison(
    niveau_regles: str,
    classe: str,
    confiance: float,
    finale: str,
    modele_ok: bool,
    temperature: float,
    seuil: float,
) -> str:
    if not modele_ok:
        return (
            f"Règles seules : température {temperature:.1f} °C → {niveau_regles} "
            f"(seuil {seuil:.1f} °C, seuils +3/+6). Modèle IA indisponible — "
            f"classification déterministe appliquée."
        )

    if ORDRE.get(finale, 0) > ORDRE.get(niveau_regles, 0):
        complement = "Le modèle a escaladé le niveau (il ne peut jamais le réduire)."
    elif classe == niveau_regles:
        complement = "Modèle et règles sont alignés."
    else:
        complement = "Le modèle suggère « pas d'alerte » ; le plancher des règles est conservé."

    return (
        f"Règles : {niveau_regles} — Modèle : {classe} "
        f"(confiance {int(round(confiance * 100))} %). "
        f"Niveau final : {finale}. {complement}"
    )


def analyser(item: AnalyzeRequest) -> AnalyzeResponse:
    """Classification hybride (modèle + règles) d'un contexte brut."""

    contexte: dict[str, Any] = item.model_dump()
    features = build_features(contexte)

    niveau_regles = rules.niveau_regles(
        features["temp"],
        item.humidite,
        features["jours_chauds_consecutifs"],
        item.seuil_temperature,
    )

    modele_ok = model.available

    if modele_ok:
        classe, probabilites, confiance = model.predict(features)
        facteurs = model.facteurs(features)
        finale = _plus_grave(classe, niveau_regles)
    else:
        classe, probabilites, confiance, facteurs = "normal", {}, 0.0, []
        finale = niveau_regles

    desaccord = modele_ok and classe != niveau_regles

    message = None
    if item.avec_message:
        message = generer_message(
            _message_contexte(contexte, features, finale),
            item.profils,
            item.alerte_id,
        )

    return AnalyzeResponse(
        niveau_final=finale,
        niveau_modele=classe,
        niveau_regles=niveau_regles,
        desaccord=desaccord,
        probabilites=probabilites,
        confiance=confiance,
        facteurs=facteurs,
        raison=_raison(
            niveau_regles, classe, confiance, finale, modele_ok,
            features["temp"], item.seuil_temperature,
        ),
        message=message,
        source="modele" if modele_ok else "regles",
        model_version=model.version if modele_ok else None,
        modele_disponible=modele_ok,
    )


def _message_contexte(
    contexte: dict[str, Any],
    features: dict[str, float],
    finale: str,
) -> dict[str, Any]:
    return {
        "niveau": finale,
        "lieu_type": contexte.get("lieu_type"),
        "temperature_actuelle": contexte.get("temperature"),
        "seuil_temperature": contexte.get("seuil_temperature"),
        "heure_pic": features.get("heure_pic"),
    }


def _count_corrections() -> int | None:
    chemin = settings.corrections_csv

    if not chemin.exists():
        return None

    try:
        with open(chemin, "r", encoding="utf-8") as fh:
            return max(0, sum(1 for _ in csv.reader(fh)) - 1)
    except OSError:
        return None


@app.get("/health")
def health() -> dict[str, Any]:
    return {"status": "ok", "model_available": model.available}


@app.get("/model-info", response_model=ModelInfoResponse, dependencies=[Depends(require_token)])
def model_info() -> ModelInfoResponse:
    meta = model.metadata()

    return ModelInfoResponse(
        version=meta.get("version"),
        date=meta.get("date") or meta.get("trained_at"),
        trained_at=meta.get("trained_at") or meta.get("date"),
        model_available=model.available,
        classes=model.classes or list(meta.get("classes", [])),
        features=list(meta.get("features", FEATURE_ORDER)),
        metrics=dict(meta.get("metrics", {})),
        n_corrections=_count_corrections(),
        n_samples=meta.get("n_samples"),
    )


@app.post("/analyze", response_model=AnalyzeResponse, dependencies=[Depends(require_token)])
def analyze(item: AnalyzeRequest) -> AnalyzeResponse:
    return analyser(item)


@app.post("/analyze/batch", response_model=BatchResponse, dependencies=[Depends(require_token)])
def analyze_batch(payload: BatchRequest) -> BatchResponse:
    return BatchResponse(results=[analyser(item) for item in payload.items])


@app.post("/message", response_model=MessageResponse, dependencies=[Depends(require_token)])
def message(payload: MessageRequest) -> MessageResponse:
    texte = generer_message(payload.model_dump(), payload.profils, payload.seed)

    return MessageResponse(message=texte)


# --------------------------------------------------------------------------- #
# Module 2 — Coupures
# --------------------------------------------------------------------------- #


@app.post("/coupures/risk", response_model=RiskResponse, dependencies=[Depends(require_token)])
def coupures_risk_endpoint(payload: RiskRequest) -> RiskResponse:
    """Batch de zones → risque (modèle + règles, plancher de sécurité)."""

    resultats = coupures_risk.evaluate_zones(payload.zones, coupures_model)

    return RiskResponse(results=[ZoneResultat(**r) for r in resultats])


@app.post(
    "/coupures/anomalies",
    response_model=AnomaliesResponse,
    dependencies=[Depends(require_token)],
)
def coupures_anomalies_endpoint(payload: AnomaliesRequest) -> AnomaliesResponse:
    """Afflux de signalements → incidents suspects (cluster + surprise)."""

    incidents = coupures_anomalies.detect_anomalies(payload)

    return AnomaliesResponse(incidents=[Incident(**i) for i in incidents])


@app.post("/coupures/etr", response_model=EtrResponse, dependencies=[Depends(require_token)])
def coupures_etr_endpoint(payload: EtrRequest) -> EtrResponse:
    """Temps de rétablissement estimé (quantiles) des coupures en cours."""

    resultats = coupures_etr.estimate_etr(payload, etr_model)

    return EtrResponse(results=resultats)


@app.get(
    "/coupures/insights",
    response_model=InsightsResponse,
    dependencies=[Depends(require_token)],
)
def coupures_insights_endpoint() -> InsightsResponse:
    """Courbe de dépendance température → risque, importances et métriques."""

    if coupures_model is None or not getattr(coupures_model, "available", False):
        return InsightsResponse(available=False, model_available=False)

    meta = coupures_model.metadata()

    return InsightsResponse(
        available=True,
        model_available=True,
        version=meta.get("version"),
        trained_at=meta.get("trained_at") or meta.get("date"),
        partial_dependence=list(meta.get("partial_dependence", [])),
        importances=list(meta.get("importances", [])),
        metrics=dict(meta.get("metrics", {})),
        evaluation=dict(meta.get("evaluation", {})),
        n_snapshots_labelises=meta.get("n_snapshots_labelises"),
    )


@app.post("/reload", dependencies=[Depends(require_token)])
def reload_model() -> dict[str, Any]:
    disponible = model.reload()
    disponible_coupures = coupures_model.reload()
    disponible_etr = etr_model.reload()

    return {
        "status": "reloaded" if disponible else "unavailable",
        "model_available": disponible,
        "coupures_model_available": disponible_coupures,
        "etr_model_available": disponible_etr,
    }


@app.post("/retrain", dependencies=[Depends(require_token)])
def retrain() -> dict[str, Any]:
    """Lance ``scripts/train.py`` en tâche de fond, puis recharge le modèle."""

    def _job() -> None:
        try:
            subprocess.run(
                [sys.executable, str(BASE_DIR / "scripts" / "train.py")],
                cwd=str(BASE_DIR),
                check=True,
            )
            model.reload()
        except Exception:  # noqa: BLE001 — le ré-entraînement ne doit jamais tuer le service
            pass

    threading.Thread(target=_job, daemon=True).start()

    return {"status": "running"}


@app.post("/retrain-coupures", dependencies=[Depends(require_token)])
def retrain_coupures() -> dict[str, Any]:
    """Ré-entraîne le modèle COUPURES (artefacts dédiés) puis le recharge.

    Endpoint séparé de ``/retrain`` : on n'écrase jamais les artefacts canicule
    avec les données du module 2 (et inversement).
    """

    def _job() -> None:
        try:
            subprocess.run(
                [sys.executable, str(BASE_DIR / "scripts" / "simulate_grid.py")],
                cwd=str(BASE_DIR),
                check=True,
            )
            subprocess.run(
                [sys.executable, str(BASE_DIR / "scripts" / "train_coupures.py")],
                cwd=str(BASE_DIR),
                check=True,
            )
            coupures_model.reload()
            etr_model.reload()
        except Exception:  # noqa: BLE001 — le ré-entraînement ne doit jamais tuer le service
            pass

    threading.Thread(target=_job, daemon=True).start()

    return {"status": "running", "target": "coupures"}
