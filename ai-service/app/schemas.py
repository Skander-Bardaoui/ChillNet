"""Schémas pydantic (contrat d'API) et validation des entrées.

Les bornes sont strictes : une température hors [-10, 60] °C, une humidité hors
[0, 100] % ou plus de 72 prévisions horaires déclenchent une réponse 422.
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class Historique(BaseModel):
    jours_surchauffe: int = Field(default=0, ge=0, le=30)
    temp_max: float | None = None


class Prevision(BaseModel):
    heure: str | None = None
    temperature: float = Field(ge=-20, le=70)


class AnalyzeRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    temperature: float | None = Field(default=None, ge=-10, le=60)
    ressentie: float | None = None
    humidite: int | None = Field(default=None, ge=0, le=100)
    seuil_temperature: float = Field(default=35.0, ge=20, le=55)
    historique: Historique = Field(default_factory=Historique)
    mois: int | None = Field(default=None, ge=1, le=12)
    previsions: list[Prevision] = Field(default_factory=list, max_length=72)
    lieu_type: str | None = None
    quartier: str | None = None
    profils: list[str] = Field(default_factory=list)
    alerte_id: int | None = None
    avec_message: bool = False


class BatchRequest(BaseModel):
    items: list[AnalyzeRequest] = Field(default_factory=list, max_length=500)


class MessageRequest(BaseModel):
    model_config = ConfigDict(extra="ignore")

    niveau: str | None = None
    niveau_label: str | None = None
    quartiers: list[str] = Field(default_factory=list)
    seuil_temperature: float | None = None
    temperature_actuelle: float | None = None
    temperature_ressentie: float | None = None
    humidite: int | None = None
    heure_pic: str | int | None = None
    lieu_type: str | None = None
    raison: str | None = None
    profils: list[str] = Field(default_factory=list)
    seed: str | None = None


class Facteur(BaseModel):
    feature: str
    label: str
    valeur: float
    valeur_affichee: str
    impact: float


class AnalyzeResponse(BaseModel):
    niveau_final: str
    niveau_modele: str
    niveau_regles: str
    desaccord: bool
    probabilites: dict[str, float]
    confiance: float
    facteurs: list[Facteur]
    raison: str
    message: str | None = None
    source: str
    model_version: str | None = None
    modele_disponible: bool = True


class BatchResponse(BaseModel):
    results: list[AnalyzeResponse]


class MessageResponse(BaseModel):
    message: str


class ModelInfoResponse(BaseModel):
    version: str | None = None
    date: str | None = None
    trained_at: str | None = None
    model_available: bool = False
    classes: list[str] = Field(default_factory=list)
    features: list[str] = Field(default_factory=list)
    metrics: dict[str, Any] = Field(default_factory=dict)
    n_corrections: int | None = None
    n_samples: int | None = None
