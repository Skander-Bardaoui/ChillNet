"""Tests du module IA de classification des catégories de signalement."""

from __future__ import annotations

from app import main as main_module
from app.signalements.dataset import CATEGORIES, load_examples


def test_dataset_signalements_contient_les_categories_du_contrat() -> None:
    descriptions, categories = load_examples()

    assert len(descriptions) > 100
    assert set(categories) == set(CATEGORIES)
    assert len(descriptions) == len(categories)


def test_classification_signalement(client) -> None:
    reponse = client.post(
        "/signalements/classify",
        json={"description": "Une fuite d’eau coule sous le lavabo."},
    )

    assert reponse.status_code == 200
    corps = reponse.json()
    assert corps["categorie"] == "fuite"
    assert 0 <= corps["confiance"] <= 100
    assert corps["model_version"]
    assert set(corps["probabilites"]).issubset(set(CATEGORIES))


def test_classification_refuse_une_description_hors_limites(client) -> None:
    assert client.post("/signalements/classify", json={"description": ""}).status_code == 422
    assert client.post(
        "/signalements/classify",
        json={"description": "x" * 2001},
    ).status_code == 422


def test_classification_signale_modele_indisponible(client, monkeypatch) -> None:
    class UnavailableModel:
        available = False

    monkeypatch.setattr(main_module, "signalements_model", UnavailableModel())
    reponse = client.post(
        "/signalements/classify",
        json={"description": "Une fuite dans le couloir."},
    )

    assert reponse.status_code == 503


def test_endpoint_classification_exige_le_jeton(client, monkeypatch) -> None:
    monkeypatch.setattr(main_module.settings, "token", "secret-partage")

    assert client.post(
        "/signalements/classify",
        json={"description": "Une fuite."},
    ).status_code == 401


def test_model_info_signalements(client) -> None:
    reponse = client.get("/signalements/model-info")

    assert reponse.status_code == 200
    assert reponse.json()["available"] is True
    assert reponse.json()["classes"]
