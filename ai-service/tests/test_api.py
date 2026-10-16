"""Tests du contrat d'API (``app.main``) : /analyze, /message, /health, token."""

from __future__ import annotations

import pytest

from app import main as main_module

ORDRE = {"normal": 0, "jaune": 1, "orange": 2, "rouge": 3}


def test_health_ouvert(client) -> None:
    reponse = client.get("/health")

    assert reponse.status_code == 200
    assert reponse.json()["status"] == "ok"


def test_analyze_minimal(client) -> None:
    reponse = client.post("/analyze", json={"temperature": 40.0, "humidite": 60})

    assert reponse.status_code == 200
    corps = reponse.json()

    for cle in (
        "niveau_final", "niveau_modele", "niveau_regles", "desaccord",
        "probabilites", "confiance", "facteurs", "raison", "source",
    ):
        assert cle in corps

    assert corps["niveau_final"] in ORDRE
    assert ORDRE[corps["niveau_final"]] >= ORDRE[corps["niveau_regles"]]


def test_analyze_respecte_le_plancher_des_regles(client) -> None:
    # 42 °C sec → règles = rouge ; le final ne peut pas être inférieur.
    corps = client.post("/analyze", json={"temperature": 42.0}).json()

    assert corps["niveau_regles"] == "rouge"
    assert corps["niveau_final"] == "rouge"


def test_analyze_avec_message(client) -> None:
    corps = client.post(
        "/analyze",
        json={"temperature": 41.0, "avec_message": True, "profils": ["personne_agee"]},
    ).json()

    assert corps["niveau_final"] in ("orange", "rouge")
    assert isinstance(corps["message"], str)
    assert corps["message"].strip() != ""
    assert "0800 06 66 66" in corps["message"]


def test_analyze_temperature_hors_bornes_422(client) -> None:
    assert client.post("/analyze", json={"temperature": 100.0}).status_code == 422


def test_analyze_humidite_hors_bornes_422(client) -> None:
    assert client.post("/analyze", json={"temperature": 40.0, "humidite": 150}).status_code == 422


def test_analyze_trop_de_previsions_422(client) -> None:
    previsions = [{"heure": "12:00", "temperature": 30.0}] * 73
    reponse = client.post("/analyze", json={"temperature": 40.0, "previsions": previsions})

    assert reponse.status_code == 422


def test_analyze_historique_hors_bornes_422(client) -> None:
    reponse = client.post("/analyze", json={"temperature": 40.0, "historique": {"jours_surchauffe": 99}})

    assert reponse.status_code == 422


def test_analyze_batch(client) -> None:
    reponse = client.post(
        "/analyze/batch",
        json={"items": [{"temperature": 36.0}, {"temperature": 43.0}]},
    )

    assert reponse.status_code == 200
    resultats = reponse.json()["results"]

    assert len(resultats) == 2
    assert ORDRE[resultats[1]["niveau_final"]] >= ORDRE[resultats[0]["niveau_final"]]


def test_message_endpoint(client) -> None:
    reponse = client.post(
        "/message",
        json={"niveau": "orange", "profils": ["enfant"], "lieu_type": "domicile", "seed": "a1"},
    )

    assert reponse.status_code == 200
    message = reponse.json()["message"]

    assert message.strip() != ""
    assert "0800 06 66 66" in message


def test_model_info(client) -> None:
    reponse = client.get("/model-info")

    assert reponse.status_code == 200
    corps = reponse.json()

    assert corps["model_available"] is True
    assert "rouge" in corps["classes"]
    assert len(corps["features"]) == 11


def test_token_requis_quand_configure(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-partage")

    assert client.post("/analyze", json={"temperature": 40.0}).status_code == 401

    mauvais = client.post(
        "/analyze", json={"temperature": 40.0}, headers={"X-AI-TOKEN": "mauvais"}
    )
    assert mauvais.status_code == 401

    bon = client.post(
        "/analyze", json={"temperature": 40.0}, headers={"X-AI-TOKEN": "secret-partage"}
    )
    assert bon.status_code == 200


def test_health_reste_ouvert_meme_avec_token(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-partage")

    assert client.get("/health").status_code == 200


@pytest.mark.parametrize("chemin", ["/analyze", "/analyze/batch", "/message", "/model-info", "/reload"])
def test_endpoints_proteges_rejettent_sans_jeton(client, monkeypatch, chemin) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-partage")

    reponse = client.post(chemin, json={}) if chemin != "/model-info" else client.get(chemin)

    assert reponse.status_code == 401
