"""Tests du modèle de santé des points de fraîcheur (module 3).

Vérifie les MONOTONIES (plus d'avis négatifs / de plaintes ne peut jamais faire
BAISSER le risque ; une meilleure note ne peut jamais le faire MONTER), le
PLANCHER de sécurité (``score_final >= score_regles``) et l'explicabilité.

Ces tests nécessitent l'artefact entraîné : ils sont ignorés s'il est absent
(lancer ``scripts/simulate_fraicheur.py`` + ``scripts/train_fraicheur.py``).
"""

from __future__ import annotations

import pytest

from app.config import settings
from app.fraicheur import sante
from app.fraicheur.features import VECTOR_ORDER, build_point_features, vector
from app.fraicheur.model import FraicheurSanteModel
from app.fraicheur.schemas import PointContexte

pytestmark = pytest.mark.skipif(
    not settings.fraicheur_model_path.exists(),
    reason="modèle fraicheur non entraîné (scripts/train_fraicheur.py)",
)


@pytest.fixture(scope="module")
def modele() -> FraicheurSanteModel:
    return FraicheurSanteModel(settings.fraicheur_model_path, settings.fraicheur_metadata_path)


def _contexte(**extra) -> dict:
    base = {
        "point_id": 1,
        "type": "salle_climatisee",
        "capacite": 60,
        "climatise": True,
        "heures_ouverture_jour": 10,
        "anciennete_jours": 400,
        "nb_avis_30j": 8,
        "note_moyenne": 3.6,
        "note_moyenne_30j": 3.4,
        "part_negatifs_30j": 0.25,
        "jours_depuis_dernier_negatif": 6,
        "temperature": 34,
        "mois": 7,
    }
    base.update(extra)
    return base


def _p14(modele: FraicheurSanteModel, **extra) -> float:
    return float(modele.predict_point(PointContexte(**_contexte(**extra)))["p14"])


def test_modele_disponible(modele: FraicheurSanteModel) -> None:
    assert modele.available is True
    assert modele.version
    assert modele.metadata()["features"] == VECTOR_ORDER


def test_monotonie_part_negatifs(modele: FraicheurSanteModel) -> None:
    precedent = -1.0
    for part in (0.0, 0.1, 0.25, 0.4, 0.6, 0.8, 1.0):
        p = _p14(modele, part_negatifs_30j=part)
        assert p >= precedent - 1e-9, f"p14 a baissé à part={part}"
        precedent = p


def test_monotonie_plaintes(modele: FraicheurSanteModel) -> None:
    for theme in ("plaintes_proprete_30j", "plaintes_chaleur_30j", "plaintes_equipements_30j"):
        precedent = -1.0
        for n in (0, 1, 2, 4, 8):
            p = _p14(modele, **{theme: n})
            assert p >= precedent - 1e-9, f"p14 a baissé avec {theme}={n}"
            precedent = p


def test_monotonie_note(modele: FraicheurSanteModel) -> None:
    """Une meilleure note ne peut jamais AUGMENTER le risque."""

    precedent = 2.0
    for note in (1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 4.0, 4.5, 5.0):
        p = _p14(modele, note_moyenne=note, note_moyenne_30j=note)
        assert p <= precedent + 1e-9, f"p14 a augmenté à note={note}"
        precedent = p


def test_point_degrade_plus_risque_que_point_sain(modele: FraicheurSanteModel) -> None:
    sain = _p14(modele, note_moyenne=4.5, note_moyenne_30j=4.6, part_negatifs_30j=0.0,
                jours_depuis_dernier_negatif=90)
    degrade = _p14(modele, note_moyenne=2.6, note_moyenne_30j=2.0, part_negatifs_30j=0.7,
                   jours_depuis_dernier_negatif=0, plaintes_chaleur_30j=3, evolution_note_30j=-1.5)

    assert degrade > sain + 0.2


def test_score_final_jamais_inferieur_aux_regles(modele: FraicheurSanteModel) -> None:
    for extra in ({}, {"note_moyenne_30j": 1.8, "part_negatifs_30j": 0.8}, {"canicule_niveau": 3}):
        resultat = sante.evaluate_point(PointContexte(**_contexte(**extra)), modele)

        assert resultat["score_final"] >= resultat["score_regles"]
        assert resultat["source"] == "modele"
        assert 0.0 <= resultat["p14"] <= 1.0


def test_facteurs_explicables(modele: FraicheurSanteModel) -> None:
    resultat = sante.evaluate_point(
        PointContexte(**_contexte(note_moyenne=2.4, note_moyenne_30j=1.9, part_negatifs_30j=0.8,
                                  jours_depuis_dernier_negatif=0)),
        modele,
    )

    assert 1 <= len(resultat["facteurs"]) <= 3
    assert all(" : " in facteur for facteur in resultat["facteurs"])
    assert resultat["raison"].startswith("Risque d'au moins 2 avis négatifs sous 14 j")


def test_features_robustes_aux_valeurs_manquantes() -> None:
    features = build_point_features({})

    assert len(vector(features)) == len(VECTOR_ORDER)
    # Sans avis récent, la note 30 j retombe sur la note globale.
    assert build_point_features({"note_moyenne": 4.2, "nb_avis_30j": 0, "note_moyenne_30j": 1.0})["note_moyenne_30j"] == 4.2
    # La canicule se déduit de la température si elle n'est pas fournie.
    assert build_point_features({"temperature": 39.0})["canicule_niveau"] == 2
