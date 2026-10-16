"""Contrats d'API du module 2 (``/coupures/*``) : schémas, 422, jeton."""

from __future__ import annotations

from app import main as main_module

CHEMINS_POST = ["/coupures/risk", "/coupures/anomalies", "/coupures/etr"]


def test_risk_rules_only(client, monkeypatch) -> None:
    # Force le mode « règles seules » (services sans artefact / repli).
    monkeypatch.setattr(main_module, "coupures_model", None)

    reponse = client.post(
        "/coupures/risk",
        json={
            "zones": [
                {
                    "quartier_id": 1,
                    "frequence": 5,
                    "en_cours": 1,
                    "chaudes": 5,
                    "confirmations": 3,
                    "niveau_chaleur": "jaune",
                    "temperature": 36,
                }
            ]
        },
    )

    assert reponse.status_code == 200
    resultat = reponse.json()["results"][0]

    assert resultat["quartier_id"] == 1
    assert resultat["score_regles"] == 71
    assert resultat["score_final"] == 71
    assert resultat["niveau_regles"] == "eleve"
    assert resultat["niveau_final"] == "eleve"
    assert resultat["source"] == "regles"
    assert resultat["p24"] is None
    assert len(resultat["facteurs"]) == 5


def test_risk_avec_modele_le_plancher_est_respecte(client) -> None:
    reponse = client.post(
        "/coupures/risk",
        json={
            "zones": [
                {
                    "quartier_id": 1,
                    "frequence": 5,
                    "en_cours": 1,
                    "chaudes": 5,
                    "confirmations": 3,
                    "niveau_chaleur": "jaune",
                    "temperature": 36,
                }
            ]
        },
    )

    resultat = reponse.json()["results"][0]

    assert resultat["score_regles"] == 71
    assert resultat["score_final"] >= resultat["score_regles"]
    assert resultat["source"] in ("modele", "regles")


def test_risk_score_final_jamais_sous_les_regles(client) -> None:
    reponse = client.post(
        "/coupures/risk",
        json={
            "zones": [
                {
                    "quartier_id": 2,
                    "frequence": 5,
                    "en_cours": 2,
                    "chaudes": 5,
                    "confirmations": 5,
                    "niveau_chaleur": "rouge",
                    "temperature": 38,
                }
            ]
        },
    )

    resultat = reponse.json()["results"][0]

    assert resultat["score_final"] >= resultat["score_regles"]


def test_risk_temperature_hors_bornes_422(client) -> None:
    reponse = client.post("/coupures/risk", json={"zones": [{"temperature": 999}]})

    assert reponse.status_code == 422


def test_anomalies_detecte_un_incident(client) -> None:
    reponse = client.post(
        "/coupures/anomalies",
        json={
            "signalements": [
                {"id": 1, "lat": 36.800, "lng": 10.180, "quartier_id": 1, "confirmations": 0, "statut": "en_cours"},
                {"id": 2, "lat": 36.801, "lng": 10.181, "quartier_id": 1, "confirmations": 1, "statut": "en_cours"},
                {"id": 3, "lat": 36.802, "lng": 10.182, "quartier_id": 1, "confirmations": 2, "statut": "en_cours"},
            ],
            "zones": [{"quartier_id": 1, "nom": "Centre", "lat": 36.800, "lng": 10.180}],
        },
    )

    assert reponse.status_code == 200
    incidents = reponse.json()["incidents"]

    assert len(incidents) == 1
    assert incidents[0]["n_reports"] == 3
    assert incidents[0]["n_confirmations"] == 3
    assert "incident majeur non déclaré" in incidents[0]["message"]


def test_anomalies_ignore_les_resolues(client) -> None:
    reponse = client.post(
        "/coupures/anomalies",
        json={
            "signalements": [
                {"id": 1, "lat": 36.80, "lng": 10.18, "quartier_id": 1, "statut": "resolue"},
                {"id": 2, "lat": 36.80, "lng": 10.18, "quartier_id": 1, "statut": "resolue"},
                {"id": 3, "lat": 36.80, "lng": 10.18, "quartier_id": 1, "statut": "resolue"},
            ],
            "zones": [{"quartier_id": 1, "lat": 36.80, "lng": 10.18}],
        },
    )

    assert reponse.json()["incidents"] == []


def test_etr_contrat(client) -> None:
    reponse = client.post("/coupures/etr", json={"coupures": [{"id": 7, "type": "panne"}]})

    assert reponse.status_code == 200
    resultat = reponse.json()["results"][0]

    assert resultat["id"] == 7
    assert "available" in resultat


def test_insights_contrat(client) -> None:
    reponse = client.get("/coupures/insights")

    assert reponse.status_code == 200
    assert "available" in reponse.json()


def test_endpoints_coupures_rejettent_sans_jeton(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-partage")

    for chemin in CHEMINS_POST:
        assert client.post(chemin, json={}).status_code == 401

    assert client.get("/coupures/insights").status_code == 401

    autorise = client.post(
        "/coupures/risk", json={}, headers={"X-AI-TOKEN": "secret-partage"}
    )
    assert autorise.status_code == 200
