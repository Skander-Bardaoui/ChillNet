"""Tests du modèle de risque équipements (Module 4 — Ghazi).

Vérifie :
  - le chargement de l'artefact et la prédiction,
  - la RÈGLE DE SÉCURITÉ ABSOLUE (équipement vital → critique + alerte_vitale),
  - le PLANCHER DE SÉCURITÉ (niveau final >= niveau règles),
  - le repli quand le modèle est absent,
  - les contrats d'API (422 sur valeurs hors bornes, jeton).

Ces tests nécessitent l'artefact entraîné : ignorés s'il est absent
(lancer ``scripts/simulate_conseils.py`` + ``scripts/train_conseils.py``).
"""

from __future__ import annotations

import pytest

from app.config import settings
from app.conseils import risk
from app.conseils.model import ConseilRiskModel
from app.conseils.rules import label_risque, niveau_plus_grave
from app.conseils.schemas import EquipementContexte, FoyerContexte
from app import main as main_module

pytestmark = pytest.mark.skipif(
    not settings.conseils_model_path.exists(),
    reason="modèle conseils non entraîné (scripts/train_conseils.py)",
)


# --------------------------------------------------------------------------- #
# Fixtures
# --------------------------------------------------------------------------- #


@pytest.fixture(scope="module")
def modele() -> ConseilRiskModel:
    return ConseilRiskModel(
        settings.conseils_model_path, settings.conseils_metadata_path
    )


def _foyer_vital(coupure: bool = True) -> FoyerContexte:
    """Foyer avec respirateur vital + éventuellement une coupure."""
    return FoyerContexte(
        foyer_id=1,
        equipements=[
            EquipementContexte(
                type="respirateur",
                criticite="vitale",
                medical=True,
                contact_urgence="Fille 0612345678",
            )
        ],
        temperature=42.0,
        humidite=70,
        canicule_niveau=3,
        coupures_en_cours=1 if coupure else 0,
    )


def _foyer_sans_equipement() -> FoyerContexte:
    return FoyerContexte(
        foyer_id=2,
        equipements=[],
        temperature=28.0,
        canicule_niveau=0,
    )


def _foyer_refrigerateur() -> FoyerContexte:
    return FoyerContexte(
        foyer_id=3,
        equipements=[
            EquipementContexte(type="refrigerateur", criticite="elevee"),
        ],
        temperature=39.0,
        canicule_niveau=2,
        coupures_en_cours=1,
    )


# --------------------------------------------------------------------------- #
# Tests du modèle
# --------------------------------------------------------------------------- #


def test_modele_disponible(modele: ConseilRiskModel) -> None:
    assert modele.available is True
    assert modele.version
    assert len(modele.classes) == 4


def test_prediction_renvoie_une_classe_valide(modele: ConseilRiskModel) -> None:
    from app.conseils.features import build_features

    features = build_features({
        "nb_equipements": 1,
        "nb_medicaux": 1,
        "nb_vitaux": 1,
        "criticite_max": 2,
        "temperature": 42.0,
        "canicule_niveau": 3,
        "coupures_en_cours": 1,
    })

    classe, probas, confiance = modele.predict(features)

    assert classe in ("faible", "modere", "eleve", "critique")
    assert isinstance(probas, dict)
    assert confiance > 0.0


def test_facteurs_explicables(modele: ConseilRiskModel) -> None:
    from app.conseils.features import build_features

    features = build_features({
        "nb_equipements": 1,
        "nb_medicaux": 1,
        "nb_vitaux": 1,
        "criticite_max": 2,
        "temperature": 42.0,
        "canicule_niveau": 3,
        "coupures_en_cours": 1,
    })

    facteurs = modele.facteurs(features)
    assert isinstance(facteurs, list)
    assert len(facteurs) >= 1


# --------------------------------------------------------------------------- #
# Tests des règles déterministes
# --------------------------------------------------------------------------- #


def test_regles_vital_avec_coupure_donne_critique() -> None:
    ctx = {"nb_vitaux": 1, "nb_medicaux": 1, "criticite_max": 2, "coupures_en_cours": 1}
    assert label_risque(ctx) == "critique"


def test_regles_vital_sans_coupure_donne_eleve() -> None:
    ctx = {"nb_vitaux": 1, "nb_medicaux": 1, "criticite_max": 2, "coupures_en_cours": 0}
    assert label_risque(ctx) == "eleve"


def test_regles_sans_equipement_donne_faible() -> None:
    ctx = {"nb_vitaux": 0, "nb_medicaux": 0, "criticite_max": 0, "coupures_en_cours": 0}
    assert label_risque(ctx) == "faible"


def test_regles_medical_avec_coupure_canicule_donne_critique() -> None:
    ctx = {"nb_vitaux": 0, "nb_medicaux": 1, "criticite_max": 1,
           "coupures_en_cours": 1, "canicule_niveau": 2}
    assert label_risque(ctx) == "critique"


def test_niveau_plus_grave() -> None:
    assert niveau_plus_grave("faible", "critique") == "critique"
    assert niveau_plus_grave("eleve", "modere") == "eleve"
    assert niveau_plus_grave("critique", "faible") == "critique"


# --------------------------------------------------------------------------- #
# Tests d'intégration (risk.py)
# --------------------------------------------------------------------------- #


def test_foyer_vital_alerte_vitale_toujours_vraie(modele: ConseilRiskModel) -> None:
    """RÈGLE DE SÉCURITÉ ABSOLUE : vital → alerte_vitale + niveau >= critique."""
    resultat = risk.evaluate_foyer(_foyer_vital(coupure=True), modele)

    assert resultat.alerte_vitale is True
    assert resultat.niveau_final == "critique"


def test_foyer_vital_sans_coupure_niveau_eleve_minimum(modele: ConseilRiskModel) -> None:
    """Même sans coupure, un équipement vital donne au moins 'eleve'."""
    resultat = risk.evaluate_foyer(_foyer_vital(coupure=False), modele)

    assert resultat.alerte_vitale is True
    assert resultat.niveau_final in ("eleve", "critique")


def test_foyer_sans_equipement_donne_faible(modele: ConseilRiskModel) -> None:
    resultat = risk.evaluate_foyer(_foyer_sans_equipement(), modele)

    assert resultat.alerte_vitale is False
    assert resultat.niveau_final == "faible"


def test_foyer_refrigerateur_niveau_non_faible(modele: ConseilRiskModel) -> None:
    """Un réfrigérateur en criticité élevée + coupure + canicule → au moins modere."""
    resultat = risk.evaluate_foyer(_foyer_refrigerateur(), modele)

    assert resultat.niveau_final in ("modere", "eleve", "critique")
    assert resultat.alerte_vitale is False


def test_plancher_niveau_final_jamais_sous_regles(modele: ConseilRiskModel) -> None:
    """PLANCHER DE SÉCURITÉ : niveau_final >= niveau_regles."""
    for foyer in [_foyer_vital(True), _foyer_sans_equipement(), _foyer_refrigerateur()]:
        resultat = risk.evaluate_foyer(foyer, modele)
        ordre_final = {"faible": 0, "modere": 1, "eleve": 2, "critique": 3}
        assert ordre_final[resultat.niveau_final] >= ordre_final[resultat.niveau_regles]


def test_repli_sans_modele() -> None:
    """Sans modèle, on retombe sur les règles seules (repli sûr)."""
    resultat = risk.evaluate_foyer(_foyer_vital(coupure=True), None)

    assert resultat.source == "regles"
    assert resultat.niveau_final == resultat.niveau_regles
    assert resultat.alerte_vitale is True


def test_equipement_medical_non_vital_declenche_alerte(modele: ConseilRiskModel) -> None:
    """Même définition que PHP : médical OU criticité vitale → alerte + critique."""
    foyer = FoyerContexte(
        foyer_id=4,
        equipements=[EquipementContexte(type="respirateur", criticite="elevee", medical=True)],
    )
    resultat = risk.evaluate_foyer(foyer, modele)

    assert resultat.alerte_vitale is True
    assert resultat.niveau_final == "critique"


# --------------------------------------------------------------------------- #
# Garde-fous anti-circularité (étiquette recalculable par les règles = ML inutile)
# --------------------------------------------------------------------------- #

_CSV = settings.conseils_model_path.parent.parent.parent / "data" / "equipements_risk_history.csv"


@pytest.mark.skipif(not _CSV.exists(), reason="dataset conseils absent")
def test_etiquette_non_deterministe_des_features() -> None:
    """Des lignes aux features IDENTIQUES doivent avoir des étiquettes différentes."""
    from collections import defaultdict

    from app.conseils.training import charger_dataset

    d = charger_dataset(_CSV)
    vus: dict[tuple, set] = defaultdict(set)
    for ligne, etiquette in zip(d["X"], d["y"]):
        vus[tuple(ligne)].add(etiquette)

    conflits = sum(1 for e in vus.values() if len(e) > 1)
    assert conflits > 0, "étiquette fonction déterministe des features : apprentissage circulaire"


@pytest.mark.skipif(not _CSV.exists(), reason="dataset conseils absent")
def test_modele_bat_les_regles_sur_foyers_inconnus(modele: ConseilRiskModel) -> None:
    """Le modèle doit surpasser les règles seules, sans atteindre une perfection suspecte."""
    from sklearn.metrics import accuracy_score

    from app.conseils.training import charger_dataset, decouper

    d = charger_dataset(_CSV)
    _, te = decouper(d["groupes"])
    X, y, regles = d["X"][te], d["y"][te], d["regles"][te]

    acc_modele = accuracy_score(y, modele._pipeline.predict(X))
    acc_regles = accuracy_score(y, regles)

    assert acc_regles < 0.9, "les règles recalculent l'étiquette : dataset circulaire"
    assert acc_modele > acc_regles + 0.05
    assert acc_modele < 0.97, "exactitude suspecte : fuite d'information probable"


# --------------------------------------------------------------------------- #
# Tests d'API (FastAPI)
# --------------------------------------------------------------------------- #


def test_api_risk_repond(client) -> None:
    reponse = client.post(
        "/conseils/risk",
        json={
            "foyers": [
                {
                    "foyer_id": 1,
                    "equipements": [
                        {"type": "respirateur", "criticite": "vitale", "medical": True}
                    ],
                    "temperature": 40.0,
                    "canicule_niveau": 3,
                    "coupures_en_cours": 1,
                }
            ]
        },
    )

    assert reponse.status_code == 200
    resultat = reponse.json()["results"][0]

    assert resultat["alerte_vitale"] is True
    assert resultat["niveau_final"] == "critique"
    assert resultat["niveau_final"] in ("faible", "modere", "eleve", "critique")


def test_api_risk_temperature_hors_bornes_422(client) -> None:
    reponse = client.post(
        "/conseils/risk",
        json={"foyers": [{"temperature": 999}]},
    )
    assert reponse.status_code == 422


def test_api_insights_repond(client) -> None:
    reponse = client.get("/conseils/insights")
    assert reponse.status_code == 200
    assert "available" in reponse.json()


def test_api_rejettent_sans_jeton(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-conseils")

    assert client.post("/conseils/risk", json={}).status_code == 401
    assert client.get("/conseils/insights").status_code == 401

    autorise = client.post(
        "/conseils/risk",
        json={},
        headers={"X-AI-TOKEN": "secret-conseils"},
    )
    assert autorise.status_code == 200
