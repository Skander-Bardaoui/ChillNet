"""Schémas pydantic (contrat d'API) du module 2 (coupures).

Bornes strictes : toute valeur hors plage déclenche une réponse 422. Les
champs inconnus sont ignorés (``extra="ignore"``) pour rester tolérant aux
évolutions du contexte envoyé par Laravel.
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field

# --------------------------------------------------------------------------- #
# Risque par zone
# --------------------------------------------------------------------------- #


class ZoneContexte(BaseModel):
    """Contexte BRUT d'une zone (quartier) pour le score de risque.

    Les champs de base alimentent les règles ; les champs « features »
    (optionnels) alimentent le modèle entraîné (Phase B).
    """

    model_config = ConfigDict(extra="ignore")

    quartier_id: int | None = None
    nom: str | None = None

    # --- Base des règles ---
    frequence: int = Field(default=0, ge=0, le=1_000_000)
    en_cours: int = Field(default=0, ge=0, le=1_000_000)
    chaudes: int = Field(default=0, ge=0, le=1_000_000)
    confirmations: int = Field(default=0, ge=0, le=100_000_000)
    niveau_chaleur: str | None = None
    temperature: float | None = Field(default=None, ge=-50, le=70)

    # --- Features du modèle (Phase B) ---
    coupures_7j: int = Field(default=0, ge=0, le=1_000_000)
    coupures_14j: int = Field(default=0, ge=0, le=1_000_000)
    part_chaleur_14j: float = Field(default=0.0, ge=0, le=1)
    heures_depuis_derniere: float | None = Field(default=None, ge=0, le=1_000_000)
    confirmations_total: int = Field(default=0, ge=0, le=100_000_000)
    ressentie: float | None = Field(default=None, ge=-50, le=80)
    heat_index: float | None = Field(default=None, ge=-50, le=80)
    max_24h: float | None = Field(default=None, ge=-50, le=80)
    max_48h: float | None = Field(default=None, ge=-50, le=80)
    canicule_niveau: int = Field(default=0, ge=0, le=3)
    jours_chauds_consecutifs: int = Field(default=0, ge=0, le=90)
    heure: int | None = Field(default=None, ge=0, le=23)
    jour_semaine: int | None = Field(default=None, ge=0, le=6)
    nb_logements_zone: int = Field(default=0, ge=0, le=10_000_000)
    nb_residences: int = Field(default=0, ge=0, le=1_000_000)


class RiskRequest(BaseModel):
    zones: list[ZoneContexte] = Field(default_factory=list, max_length=500)


class ZoneResultat(BaseModel):
    quartier_id: int | None = None
    p24: float | None = None
    p48: float | None = None
    score_modele: int | None = None
    score_regles: int
    score_final: int
    niveau_modele: str | None = None
    niveau_regles: str
    niveau_final: str
    desaccord: bool = False
    facteurs: list[str] = Field(default_factory=list)
    raison: str = ""
    source: str = "regles"
    model_version: str | None = None


class RiskResponse(BaseModel):
    results: list[ZoneResultat] = Field(default_factory=list)


# --------------------------------------------------------------------------- #
# Anomalies (afflux de signalements)
# --------------------------------------------------------------------------- #


class SignalementItem(BaseModel):
    model_config = ConfigDict(extra="ignore")

    id: int | None = None
    lat: float | None = Field(default=None, ge=-90, le=90)
    lng: float | None = Field(default=None, ge=-180, le=180)
    quartier_id: int | None = None
    quartier_nom: str | None = None
    created_at: str | None = None
    statut: str | None = None
    type: str | None = None
    confirmations: int = Field(default=0, ge=0, le=1_000_000)
    minutes_ago: float | None = Field(default=None, ge=0, le=100_000)


class ResidenceItem(BaseModel):
    model_config = ConfigDict(extra="ignore")

    id: int | None = None
    lat: float | None = Field(default=None, ge=-90, le=90)
    lng: float | None = Field(default=None, ge=-180, le=180)
    nombre_logements: int = Field(default=0, ge=0, le=1_000_000)
    quartier_id: int | None = None


class ZoneCentroide(BaseModel):
    model_config = ConfigDict(extra="ignore")

    quartier_id: int
    nom: str | None = None
    lat: float = Field(ge=-90, le=90)
    lng: float = Field(ge=-180, le=180)
    # Nombre de signalements équivalents observés sur 30 j dans la même fenêtre
    # de 2 h (baseline Poisson). 0 = aucune donnée → baseline lissée globale.
    n_30j: int = Field(default=0, ge=0, le=1_000_000)


class AnomaliesRequest(BaseModel):
    signalements: list[SignalementItem] = Field(default_factory=list, max_length=5000)
    residences: list[ResidenceItem] = Field(default_factory=list, max_length=5000)
    zones: list[ZoneCentroide] = Field(default_factory=list, max_length=1000)
    fenetre_minutes: int = Field(default=120, ge=5, le=1440)
    seuil: int = Field(default=3, ge=1, le=1000)


class Incident(BaseModel):
    id: str
    centroid: list[float]
    radius_m: float
    n_reports: int
    n_confirmations: int
    quartier_ids: list[int] = Field(default_factory=list)
    logements_estimes: int = 0
    confidence: float = 0.0
    severity: str = "modere"
    message: str = ""
    source: str = "regles"


class AnomaliesResponse(BaseModel):
    incidents: list[Incident] = Field(default_factory=list)


# --------------------------------------------------------------------------- #
# ETR — temps de rétablissement estimé
# --------------------------------------------------------------------------- #


class CoupureEnCours(BaseModel):
    model_config = ConfigDict(extra="ignore")

    id: int | None = None
    type: str | None = None
    heure: int | None = Field(default=None, ge=0, le=23)
    jour_semaine: int | None = Field(default=None, ge=0, le=6)
    temperature: float | None = Field(default=None, ge=-50, le=70)
    heat_index: float | None = Field(default=None, ge=-50, le=80)
    charge_zone: float | None = Field(default=None, ge=0, le=1_000_000)
    confirmations: int = Field(default=0, ge=0, le=1_000_000)


class EtrRequest(BaseModel):
    coupures: list[CoupureEnCours] = Field(default_factory=list, max_length=2000)


class EtrResultat(BaseModel):
    id: int | None = None
    p10_min: float | None = None
    p50_min: float | None = None
    p90_min: float | None = None
    label: str | None = None
    available: bool = False
    model_version: str | None = None


class EtrResponse(BaseModel):
    results: list[EtrResultat] = Field(default_factory=list)


# --------------------------------------------------------------------------- #
# Insights (graphique back-office)
# --------------------------------------------------------------------------- #


class InsightsResponse(BaseModel):
    available: bool = False
    model_available: bool = False
    version: str | None = None
    trained_at: str | None = None
    partial_dependence: list[dict[str, Any]] = Field(default_factory=list)
    importances: list[dict[str, Any]] = Field(default_factory=list)
    metrics: dict[str, Any] = Field(default_factory=dict)
    evaluation: dict[str, Any] = Field(default_factory=dict)
    n_snapshots_labelises: int | None = None
