"""Tests du modèle de risque de coupure (Phase B).

Vérifie la MONOTONIE (hausser la température ne peut jamais faire BAISSER la
probabilité à 24 h), la courbe de sensibilité croissante, et le PLANCHER de
sécurité (``score_final >= score_regles``).

Ces tests nécessitent l'artefact entraîné : ils sont ignorés s'il est absent
(lancer ``scripts/simulate_grid.py`` + ``scripts/train_coupures.py``).
"""

from __future__ import annotations

import pytest

from app.config import settings
from app.coupures import risk
from app.coupures.model import CoupureRiskModel
from app.coupures.schemas import ZoneContexte

pytestmark = pytest.mark.skipif(
    not settings.coupures_model_path.exists(),
    reason="modèle coupures non entraîné (scripts/train_coupures.py)",
)


@pytest.fixture(scope="module")
def modele() -> CoupureRiskModel:
    return CoupureRiskModel(settings.coupures_model_path, settings.coupures_metadata_path)


def _contexte(temp: float, **extra) -> dict:
    base = {
        "quartier_id": 1,
        "coupures_7j": 3,
        "coupures_14j": 6,
        "part_chaleur_14j": 0.6,
        "en_cours": 1,
        "confirmations_total": 4,
        "temperature": temp,
    }
    base.update(extra)
    return base


def test_modele_disponible(modele: CoupureRiskModel) -> None:
    assert modele.available is True
    assert modele.version


def test_monotonie_temperature(modele: CoupureRiskModel) -> None:
    """temp ↑ ⇒ p24 ne baisse jamais (contrainte monotonic_cst = +1)."""

    precedent = -1.0
    for temp in (15, 20, 25, 28, 30, 32, 34, 35, 36, 37, 38, 39, 40, 42, 45):
        zone = ZoneContexte(**_contexte(float(temp)))
        p24 = float(modele.predict_zone(zone)["p24"])
        assert p24 >= precedent - 1e-9, f"p24 a baissé à {temp} °C ({p24} < {precedent})"
        precedent = p24


def test_monotonie_par_zone(modele: CoupureRiskModel) -> None:
    for quartier in (1, 4, 6):
        precedent = -1.0
        for temp in (24, 30, 34, 36, 38, 41):
            zone = ZoneContexte(**_contexte(float(temp), quartier_id=quartier))
            p24 = float(modele.predict_zone(zone)["p24"])
            assert p24 >= precedent - 1e-9
            precedent = p24


def test_courbe_sensibilite_croissante(modele: CoupureRiskModel) -> None:
    courbe = modele.metadata()["partial_dependence"]
    p24 = [point["p24"] for point in courbe]

    # Monotone à l'arrondi près (les valeurs sont stockées à 4 décimales).
    for precedent, suivant in zip(p24, p24[1:]):
        assert suivant >= precedent - 1e-3, "la sensibilité doit croître avec la température"

    assert p24[-1] > p24[0], "la probabilité doit augmenter avec la température"


def test_score_final_jamais_inferieur_aux_regles(modele: CoupureRiskModel) -> None:
    for temp in (20, 30, 35, 40):
        zone = ZoneContexte(**_contexte(float(temp)))
        resultat = risk.evaluate_zone(zone, modele)

        assert resultat["score_final"] >= resultat["score_regles"]
        assert resultat["niveau_final"] in ("faible", "modere", "eleve", "critique")
        assert resultat["source"] == "modele"


def test_plancher_quand_modele_indisponible() -> None:
    """Sans modèle, on retombe sur les règles seules (repli sûr)."""

    zone = ZoneContexte(**_contexte(40.0))
    resultat = risk.evaluate_zone(zone, None)

    assert resultat["source"] == "regles"
    assert resultat["score_final"] == resultat["score_regles"]
    assert resultat["niveau_final"] == resultat["niveau_regles"]


def test_facteurs_explicables(modele: CoupureRiskModel) -> None:
    zone = ZoneContexte(**_contexte(42.0, coupures_14j=9, part_chaleur_14j=0.9, en_cours=2))
    resultat = risk.evaluate_zone(zone, modele)

    assert isinstance(resultat["facteurs"], list)
    assert len(resultat["facteurs"]) >= 1
    assert isinstance(resultat["raison"], str) and resultat["raison"]
