"""Schémas pydantic (contrat d'API) du module 3 (santé des points de fraîcheur).

Bornes strictes : toute valeur hors plage déclenche une réponse 422. Les champs
inconnus sont ignorés (``extra="ignore"``) pour rester tolérant aux évolutions
du contexte envoyé par Laravel.
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class PointContexte(BaseModel):
    """Contexte BRUT d'un point de fraîcheur et de ses avis (voir ``features.py``)."""

    model_config = ConfigDict(extra="ignore")

    point_id: int | None = None
    nom: str | None = Field(default=None, max_length=200)

    # --- Le point ---
    type: str | None = Field(default=None, max_length=30)
    type_code: int | None = Field(default=None, ge=0, le=2)
    capacite: int | None = Field(default=None, ge=0, le=100_000)
    climatise: bool = False
    ombrage: bool = False
    eau_potable: bool = False
    accessible_pmr: bool = False
    heures_ouverture_jour: float | None = Field(default=None, ge=0, le=24)
    anciennete_jours: int | None = Field(default=None, ge=0, le=36_500)
    a_photo: bool = False

    # --- Ses avis ---
    note_moyenne: float | None = Field(default=None, ge=1, le=5)
    nb_avis_30j: int = Field(default=0, ge=0, le=1_000_000)
    note_moyenne_30j: float | None = Field(default=None, ge=1, le=5)
    evolution_note_30j: float = Field(default=0.0, ge=-4, le=4)
    part_negatifs_30j: float = Field(default=0.0, ge=0, le=1)
    jours_depuis_dernier_negatif: float | None = Field(default=None, ge=0, le=36_500)
    part_affluence_forte_30j: float = Field(default=0.0, ge=0, le=1)
    sentiment_moyen_30j: float = Field(default=0.0, ge=-1, le=1)
    plaintes_proprete_30j: int = Field(default=0, ge=0, le=100_000)
    plaintes_chaleur_30j: int = Field(default=0, ge=0, le=100_000)
    plaintes_equipements_30j: int = Field(default=0, ge=0, le=100_000)

    # --- Le contexte ---
    temperature: float | None = Field(default=None, ge=-50, le=70)
    canicule_niveau: int | None = Field(default=None, ge=0, le=3)
    mois: int | None = Field(default=None, ge=1, le=12)


class SanteRequest(BaseModel):
    points: list[PointContexte] = Field(default_factory=list, max_length=1000)


class PointSanteResultat(BaseModel):
    point_id: int | None = None
    p14: float | None = None
    score_modele: int | None = None
    score_regles: int
    score_final: int
    niveau_modele: str | None = None
    niveau_regles: str
    niveau_final: str
    desaccord: bool = False
    facteurs: list[str] = Field(default_factory=list)
    actions: list[str] = Field(default_factory=list)
    raison: str = ""
    source: str = "regles"
    model_version: str | None = None


class SanteResponse(BaseModel):
    results: list[PointSanteResultat] = Field(default_factory=list)


class FraicheurInsightsResponse(BaseModel):
    available: bool = False
    model_available: bool = False
    version: str | None = None
    trained_at: str | None = None
    importances: list[dict[str, Any]] = Field(default_factory=list)
    partial_dependence: list[dict[str, Any]] = Field(default_factory=list)
    metrics: dict[str, Any] = Field(default_factory=dict)
    comparaison_regles: dict[str, Any] = Field(default_factory=dict)
    evaluation: dict[str, Any] = Field(default_factory=dict)
    n_samples: int | None = None
    n_reel: int | None = None
