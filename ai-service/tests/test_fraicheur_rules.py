"""Parité stricte des règles Python ↔ PHP (module 3 — santé des points de fraîcheur).

La fixture ``tests/fixtures/fraicheur_rules_cases.json`` (racine du dépôt) est la
source de vérité unique : le test PHP (tests/Unit/FraicheurRulesParityTest.php)
la lit et le présent test la lit aussi. Elle fige aussi l'ordre des colonnes
d'entraînement (``_colonnes``), partagé avec l'export Laravel.
"""

from __future__ import annotations

import json
from pathlib import Path

import pytest

from app.fraicheur import rules
from app.fraicheur.features import VECTOR_ORDER


def _trouver_fixture() -> Path | None:
    ici = Path(__file__).resolve()

    candidats = [
        ici.parents[2] / "tests" / "fixtures" / "fraicheur_rules_cases.json",  # racine du dépôt
        ici.parents[1] / "tests" / "fixtures" / "fraicheur_rules_cases.json",
    ]

    for candidat in candidats:
        if candidat.exists():
            return candidat

    return None


FIXTURE = _trouver_fixture()

if FIXTURE is None:
    pytest.skip(
        "Fixture partagée tests/fixtures/fraicheur_rules_cases.json introuvable "
        "(lancer pytest depuis le dépôt ChillNet).",
        allow_module_level=True,
    )


def _donnees() -> dict:
    return json.loads(FIXTURE.read_text(encoding="utf-8"))


def _cas() -> list[dict]:
    return _donnees()["cases"]


def test_fixture_non_vide() -> None:
    assert len(_cas()) >= 15


def test_parite_regles_fraicheur() -> None:
    for cas in _cas():
        obtenu = rules.score_regles(cas)

        assert obtenu["score"] == cas["expected_score"], f"score : {cas}"
        assert obtenu["niveau_regles"] == cas["expected_niveau"], f"niveau : {cas}"
        assert obtenu["facteurs"] == cas["expected_facteurs"], f"facteurs : {cas}"
        assert obtenu["actions"] == cas["expected_actions"], f"actions : {cas}"
        assert obtenu["raison"] == cas["expected_raison"], f"raison : {cas}"


def test_fixture_couvre_les_quatre_niveaux() -> None:
    assert {cas["expected_niveau"] for cas in _cas()} == set(rules.NIVEAUX)


def test_colonnes_partagees_avec_laravel() -> None:
    """L'ordre des features est figé : l'export Laravel le relit dans la fixture."""

    assert _donnees()["_colonnes"] == VECTOR_ORDER


def test_mapping_probabilite_vers_score() -> None:
    assert rules.score_depuis_probabilite(0.0) == 0
    assert rules.score_depuis_probabilite(0.5) == 50
    assert rules.score_depuis_probabilite(0.755) == 76
    assert rules.score_depuis_probabilite(1.0) == 100
    assert rules.score_depuis_probabilite(-0.2) == 0
    assert rules.score_depuis_probabilite(1.5) == 100


def test_paliers_de_sante() -> None:
    assert rules.niveau_sante(0) == "bon"
    assert rules.niveau_sante(24) == "bon"
    assert rules.niveau_sante(25) == "a_surveiller"
    assert rules.niveau_sante(50) == "degrade"
    assert rules.niveau_sante(75) == "critique"


def test_un_seul_avis_negatif_ne_compte_pas_comme_part() -> None:
    """Un seul avis : la note compte, mais la PART de négatifs (bruit) non."""

    resultat = rules.score_regles({"nb_avis_30j": 1, "note_moyenne_30j": 1.0, "part_negatifs_30j": 1.0})

    assert resultat["composantes"]["negatifs"] == 0
    assert resultat["composantes"]["note"] == 35


def test_surveillance_si_le_score_final_est_eleve_sans_cause_identifiee() -> None:
    assert rules.actions({}, 10) == []
    assert rules.actions({}, 60) == ["Surveiller les prochains avis"]
