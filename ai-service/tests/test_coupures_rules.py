"""Parité stricte des règles Python ↔ PHP (module 2 — coupures).

La fixture ``tests/fixtures/coupure_rules_cases.json`` (racine du dépôt) est la
source de vérité unique : le test PHP (tests/Unit/CoupureRulesParityTest.php) la
lit et le présent test la lit aussi. Toute divergence rompt la chaîne de
sécurité (plancher côté Python, repli côté Laravel).
"""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from app.coupures import rules

NIVEAUX = {"faible", "modere", "eleve", "critique"}


def _trouver_fixture() -> Path | None:
    ici = Path(__file__).resolve()

    candidats = [
        ici.parents[2] / "tests" / "fixtures" / "coupure_rules_cases.json",  # racine du dépôt
        ici.parents[3] / "tests" / "fixtures" / "coupure_rules_cases.json",
        ici.parents[1] / "tests" / "fixtures" / "coupure_rules_cases.json",
    ]

    for candidat in candidats:
        if candidat.exists():
            return candidat

    return None


FIXTURE = _trouver_fixture()


def _charger() -> list[dict]:
    data = json.loads(FIXTURE.read_text(encoding="utf-8"))

    return data["cases"] if isinstance(data, dict) else data


if FIXTURE is None:
    pytest.skip(
        "Fixture partagée tests/fixtures/coupure_rules_cases.json introuvable "
        "(lancer pytest depuis le dépôt ChillNet).",
        allow_module_level=True,
    )


def test_fixture_non_vide() -> None:
    assert len(_charger()) >= 15


def test_parite_regles_coupures() -> None:
    for cas in _charger():
        obtenu = rules.score_regles(cas)

        assert obtenu["score"] == cas["expected_score"], f"score : {cas}"
        assert obtenu["niveau_regles"] == cas["expected_niveau"], f"niveau : {cas}"
        assert obtenu["facteurs"] == cas["expected_facteurs"], f"facteurs : {cas}"
        assert obtenu["raison"] == cas["expected_raison"], f"raison : {cas}"


def test_fixture_couvre_les_quatre_niveaux() -> None:
    attendus = {cas["expected_niveau"] for cas in _charger()}

    assert attendus == NIVEAUX


def test_mapping_probabilite_vers_score() -> None:
    assert rules.score_depuis_probabilite(0.0) == 0
    assert rules.score_depuis_probabilite(0.5) == 50
    assert rules.score_depuis_probabilite(0.755) == 76
    assert rules.score_depuis_probabilite(1.0) == 100
    # Bornage hors plage.
    assert rules.score_depuis_probabilite(-0.2) == 0
    assert rules.score_depuis_probabilite(1.5) == 100
