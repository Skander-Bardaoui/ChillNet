"""Schémas pydantic du module 4 (conseils / risque équipements).

Toutes les bornes sont strictes : une valeur hors plage déclenche une 422.
Les champs inconnus sont ignorés (``extra="ignore"``) pour rester tolérant.
"""

from __future__ import annotations

from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class EquipementContexte(BaseModel):
    """Un équipement sensible déclaré par le foyer."""

    model_config = ConfigDict(extra="ignore")

    type:           str             = Field(default="autre")
    criticite:      str             = Field(default="normale")  # normale/elevee/vitale
    medical:        bool            = False
    contact_urgence: str | None     = None


class FoyerContexte(BaseModel):
    """Contexte complet d'un foyer pour le score de risque équipements.

    Laravel sérialise les équipements déclarés + la météo du lieu principal
    + les coupures actives dans la zone interne.
    """

    model_config = ConfigDict(extra="ignore")

    foyer_id:     int | None = None

    # Équipements — Laravel peut envoyer une liste OU des compteurs agrégés.
    equipements:  list[EquipementContexte] = Field(default_factory=list, max_length=50)

    # Météo du lieu principal
    temperature:  float | None = Field(default=None, ge=-10, le=60)
    ressentie:    float | None = Field(default=None, ge=-20, le=70)
    humidite:     int   | None = Field(default=None, ge=0,   le=100)
    heat_index:   float | None = Field(default=None, ge=-20, le=70)
    canicule_niveau: int       = Field(default=0,    ge=0,   le=3)
    jours_chauds_consecutifs: int = Field(default=0, ge=0,   le=90)
    mois:         int   | None = Field(default=None, ge=1,   le=12)
    heure:        int   | None = Field(default=None, ge=0,   le=23)

    # Coupures zone interne
    coupures_en_cours: int     = Field(default=0, ge=0, le=100)
    coupures_7j:       int     = Field(default=0, ge=0, le=1000)
    duree_moy_coupures_h: float = Field(default=0.0, ge=0, le=72)


class RiskRequest(BaseModel):
    """Batch de foyers → scores de risque."""
    foyers: list[FoyerContexte] = Field(default_factory=list, max_length=200)


class FoyerResultat(BaseModel):
    """Résultat de l'analyse de risque pour un foyer."""
    foyer_id:       int | None  = None
    niveau_final:   str                     # faible/modere/eleve/critique
    niveau_modele:  str | None  = None
    niveau_regles:  str
    desaccord:      bool        = False
    confiance:      float       = 0.0
    probabilites:   dict[str, float] = Field(default_factory=dict)
    facteurs:       list[str]   = Field(default_factory=list)
    alerte_vitale:  bool        = False     # TOUJOURS vrai si nb_vitaux > 0
    raison:         str         = ""
    source:         str         = "regles"
    model_version:  str | None  = None


class RiskResponse(BaseModel):
    results: list[FoyerResultat] = Field(default_factory=list)


class InsightsResponse(BaseModel):
    """Métriques et importances pour le back-office."""
    available:       bool = False
    model_available: bool = False
    version:         str | None = None
    trained_at:      str | None = None
    importances:     list[dict[str, Any]] = Field(default_factory=list)
    metrics:         dict[str, Any]       = Field(default_factory=dict)
    classes:         list[str]            = Field(default_factory=list)
    n_samples:       int | None = None
