"""Contrats d'API du module 3 (``/fraicheur/*``) : schémas, 422, jeton, plancher."""

from __future__ import annotations

from app import main as main_module

POINT_DEGRADE = {
    "point_id": 7,
    "nom": "Parc du Belvédère",
    "type": "parc",
    "capacite": 40,
    "ombrage": True,
    "nb_avis_30j": 8,
    "note_moyenne": 3.1,
    "note_moyenne_30j": 2.0,
    "evolution_note_30j": -1.25,
    "part_negatifs_30j": 0.625,
    "jours_depuis_dernier_negatif": 1,
    "part_affluence_forte_30j": 0.6,
    "sentiment_moyen_30j": -0.4,
    "plaintes_proprete_30j": 2,
    "temperature": 39,
    "canicule_niveau": 2,
    "mois": 7,
}


def test_sante_regles_seules(client, monkeypatch) -> None:
    # Force le mode « règles seules » (artefact absent / repli).
    monkeypatch.setattr(main_module, "fraicheur_model", None)

    reponse = client.post("/fraicheur/sante", json={"points": [POINT_DEGRADE]})

    assert reponse.status_code == 200
    resultat = reponse.json()["results"][0]

    # note 35 + négatifs 16 + tendance 15 + plaintes 10 + pression 10 = 86.
    assert resultat["point_id"] == 7
    assert resultat["score_regles"] == 86
    assert resultat["score_final"] == 86
    assert resultat["niveau_final"] == "critique"
    assert resultat["source"] == "regles"
    assert resultat["p14"] is None
    assert "Programmer un nettoyage du site" in resultat["actions"]
    assert resultat["facteurs"][0] == "Note moyenne sur 30 j : 2,0/5"


def test_sante_point_sans_avis(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module, "fraicheur_model", None)

    reponse = client.post("/fraicheur/sante", json={"points": [{"point_id": 1, "type": "fontaine"}]})

    resultat = reponse.json()["results"][0]
    assert resultat["score_final"] == 0
    assert resultat["niveau_final"] == "bon"
    assert resultat["actions"] == []
    assert resultat["raison"] == "0 avis sur 30 j · note récente — · 0 % négatifs"


def test_sante_avec_modele_le_plancher_est_respecte(client) -> None:
    reponse = client.post("/fraicheur/sante", json={"points": [POINT_DEGRADE, {"point_id": 2}]})

    assert reponse.status_code == 200
    for resultat in reponse.json()["results"]:
        assert resultat["score_final"] >= resultat["score_regles"]
        assert resultat["source"] in ("modele", "regles")
        assert resultat["niveau_final"] in ("bon", "a_surveiller", "degrade", "critique")


def test_sante_valeur_hors_bornes_422(client) -> None:
    assert client.post("/fraicheur/sante", json={"points": [{"part_negatifs_30j": 1.5}]}).status_code == 422
    assert client.post("/fraicheur/sante", json={"points": [{"note_moyenne_30j": 0}]}).status_code == 422
    assert client.post("/fraicheur/sante", json={"points": [{"canicule_niveau": 4}]}).status_code == 422


def test_sante_champs_inconnus_ignores(client) -> None:
    reponse = client.post("/fraicheur/sante", json={"points": [{"point_id": 3, "champ_futur": "x"}]})

    assert reponse.status_code == 200


def test_insights_contrat(client) -> None:
    reponse = client.get("/fraicheur/insights")

    assert reponse.status_code == 200
    assert "available" in reponse.json()


def test_endpoints_fraicheur_rejettent_sans_jeton(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-partage")

    assert client.post("/fraicheur/sante", json={}).status_code == 401
    assert client.get("/fraicheur/insights").status_code == 401
    assert client.post("/retrain-fraicheur").status_code == 401

    autorise = client.post("/fraicheur/sante", json={}, headers={"X-AI-TOKEN": "secret-partage"})
    assert autorise.status_code == 200
