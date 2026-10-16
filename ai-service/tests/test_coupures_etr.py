"""Tests de l'estimateur ETR (Phase D) : tri garanti p10 ≤ p50 ≤ p90."""

from __future__ import annotations

import pytest

from app.config import settings
from app.coupures import etr
from app.coupures.schemas import CoupureEnCours, EtrRequest

pytestmark = pytest.mark.skipif(
    not settings.coupures_etr_model_path.exists(),
    reason="modèle ETR non entraîné (scripts/train_coupures.py)",
)


@pytest.fixture(scope="module")
def modele() -> etr.CoupureEtrModel:
    return etr.CoupureEtrModel(settings.coupures_etr_model_path)


def _coupures_variees() -> list[CoupureEnCours]:
    resultats = []
    for type_coupure in ("panne", "surcharge", "delestage", "maintenance", None):
        for temp in (15.0, 25.0, 35.0, 42.0):
            for heure in (3, 12, 18):
                resultats.append(
                    CoupureEnCours(
                        id=len(resultats),
                        type=type_coupure,
                        heure=heure,
                        jour_semaine=len(resultats) % 7,
                        temperature=temp,
                        heat_index=temp + 2,
                        charge_zone=min(1.0, max(0.0, (temp - 30) / 10)),
                        confirmations=len(resultats) % 5,
                    )
                )
    return resultats


def test_modele_etr_disponible(modele: etr.CoupureEtrModel) -> None:
    assert modele.available is True
    assert modele.version


def test_quantiles_ordonnes(modele: etr.CoupureEtrModel) -> None:
    for coupure in _coupures_variees():
        resultat = modele.predict(coupure)

        assert resultat["available"] is True
        p10, p50, p90 = resultat["p10_min"], resultat["p50_min"], resultat["p90_min"]

        assert p10 <= p50 <= p90, f"quantiles désordonnés : {p10} {p50} {p90}"
        assert p10 > 0


def test_libelle_humain_fr(modele: etr.CoupureEtrModel) -> None:
    resultat = modele.predict(CoupureEnCours(id=1, type="panne", temperature=30.0))

    assert resultat["label"].startswith("retour estimé")
    assert " h" in resultat["label"]


def test_libelle_borne() -> None:
    assert etr.libelle(30.0, 45.0) == "retour estimé autour de 1 h"
    assert etr.libelle(60.0, 180.0) == "retour estimé entre 1 h et 3 h"


def test_estimate_etr_sans_modele() -> None:
    resultats = etr.estimate_etr(EtrRequest(coupures=[CoupureEnCours(id=9, type="panne")]), None)

    assert resultats[0]["available"] is False
    assert resultats[0]["id"] == 9


def test_estimate_etr_avec_modele(modele: etr.CoupureEtrModel) -> None:
    requete = EtrRequest(coupures=_coupures_variees()[:6])
    resultats = etr.estimate_etr(requete, modele)

    assert len(resultats) == 6
    for resultat in resultats:
        assert resultat["p10_min"] <= resultat["p50_min"] <= resultat["p90_min"]
