"""Tests du générateur de messages (``app.nlg``)."""

from __future__ import annotations

import itertools

from app.nlg import (
    LIEU_CLAUSE,
    NUMEROS,
    PROFILE_PRIORITY,
    PROFILE_SENTENCES,
    generer_message,
)

NIVEAUX = ["normal", "jaune", "orange", "rouge"]
LIEUX = [None, "domicile", "travail", "autre"]
PROFILS = [
    [],
    ["personne_agee"],
    ["enfant"],
    ["equipement_medical"],
    ["personne_agee", "enfant"],
    ["equipement_medical", "personne_agee", "enfant"],
]


def _contexte(niveau: str, lieu: str | None) -> dict:
    return {"niveau": niveau, "lieu_type": lieu, "heure_pic": "15:00"}


def test_message_non_vide_pour_toutes_les_combinaisons() -> None:
    for niveau, lieu, profils in itertools.product(NIVEAUX, LIEUX, PROFILS):
        message = generer_message(_contexte(niveau, lieu), profils, seed="test")

        assert isinstance(message, str)
        assert message.strip() != ""
        assert message.count(".") <= 4


def test_message_deterministe_pour_une_meme_graine() -> None:
    for niveau in NIVEAUX:
        a = generer_message(_contexte(niveau, "domicile"), ["personne_agee"], seed="alerte:1")
        b = generer_message(_contexte(niveau, "domicile"), ["personne_agee"], seed="alerte:1")

        assert a == b


def test_numeros_urgence_presents_des_orange() -> None:
    for niveau in ("orange", "rouge"):
        for profils in PROFILS:
            message = generer_message(_contexte(niveau, "travail"), profils, seed="x")

            assert "190" in message
            assert "0800 06 66 66" in message


def test_numeros_urgence_absents_sous_orange() -> None:
    for niveau in ("normal", "jaune"):
        message = generer_message(_contexte(niveau, "autre"), ["enfant"], seed="x")

        assert "0800" not in message


def test_message_sans_markdown() -> None:
    for niveau in NIVEAUX:
        message = generer_message(_contexte(niveau, "domicile"), ["enfant"], seed="x")

        assert "*" not in message
        assert "#" not in message
        assert "`" not in message


def test_priorite_profils_deux_maximum_equipement_avant_enfant() -> None:
    message = generer_message(
        _contexte("rouge", None),
        ["enfant", "personne_agee", "equipement_medical"],
        seed="priorite",
    )

    assert any(s in message for s in PROFILE_SENTENCES["equipement_medical"])
    # L'enfant est le profil de priorité la plus basse : il est écarté.
    assert not any(s in message for s in PROFILE_SENTENCES["enfant"])


def test_clause_lieu_presente_quand_reconnue() -> None:
    message = generer_message(_contexte("jaune", "travail"), [], seed="lieu")

    assert any(s in message for s in LIEU_CLAUSE["travail"])


def test_numeros_contient_les_trois_contacts() -> None:
    for contact in ("190", "198", "0800 06 66 66"):
        assert contact in NUMEROS


def test_priorite_declaree_ordre_attendu() -> None:
    assert PROFILE_PRIORITY == ["equipement_medical", "personne_agee", "enfant"]
