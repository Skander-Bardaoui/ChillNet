"""Parité stricte des règles PHP ↔ Python via la fixture partagée.

La fixture ``tests/fixtures/rules_cases.json`` (racine du dépôt) est la source de
vérité unique : le test PHP la lit et le présent test la lit aussi. Toute
divergence entre ``VigilanceAiService::niveauPour`` et ``rules.niveau_regles``
casse la chaîne de sécurité (le plancher côté Python et le repli côté Laravel).
"""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from app import rules

NIVEAUX = {"jaune", "orange", "rouge"}


def _trouver_fixture() -> Path | None:
    ici = Path(__file__).resolve()

    candidats = [
        ici.parents[2] / "tests" / "fixtures" / "rules_cases.json",  # racine du dépôt
        ici.parents[3] / "tests" / "fixtures" / "rules_cases.json",
        ici.parents[1] / "tests" / "fixtures" / "rules_cases.json",
    ]

    for candidat in candidats:
        if candidat.exists():
            return candidat

    return None


FIXTURE = _trouver_fixture()


def _charger() -> list[dict]:
    cas = json.loads(FIXTURE.read_text(encoding="utf-8"))
    return cas["cases"] if isinstance(cas, dict) else cas


if FIXTURE is None:
    pytest.skip(
        "Fixture partagée tests/fixtures/rules_cases.json introuvable "
        "(lancer pytest depuis le dépôt ChillNet).",
        allow_module_level=True,
    )


def test_fixture_non_vide() -> None:
    assert len(_charger()) >= 15


def test_parite_regles() -> None:
    for cas in _charger():
        obtenu = rules.niveau_regles(
            cas["temperature"],
            cas.get("humidite"),
            cas.get("jours_surchauffe", 0),
            cas.get("seuil", 35.0),
        )
        assert obtenu == cas["expected"], (
            f"Parité rompue pour {cas}: attendu {cas['expected']!r}, obtenu {obtenu!r}"
        )


def test_fixture_couvre_les_trois_niveaux() -> None:
    attendus = {cas["expected"] for cas in _charger()}
    assert attendus == NIVEAUX
