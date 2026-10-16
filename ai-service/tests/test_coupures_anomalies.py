"""Tests du détecteur d'anomalies (Phase C) : spatial DBSCAN + baseline Poisson."""

from __future__ import annotations

from app.coupures import anomalies
from app.coupures.schemas import (
    AnomaliesRequest,
    ResidenceItem,
    SignalementItem,
    ZoneCentroide,
)


def test_trois_signalements_proches_un_incident() -> None:
    requete = AnomaliesRequest(
        signalements=[
            SignalementItem(id=1, lat=36.800, lng=10.180, quartier_id=1, confirmations=0, statut="en_cours"),
            SignalementItem(id=2, lat=36.801, lng=10.181, quartier_id=1, confirmations=1, statut="en_cours"),
            SignalementItem(id=3, lat=36.802, lng=10.182, quartier_id=1, confirmations=2, statut="en_cours"),
        ],
        residences=[ResidenceItem(id=1, lat=36.801, lng=10.181, nombre_logements=120)],
        zones=[ZoneCentroide(quartier_id=1, nom="Centre", lat=36.800, lng=10.180, n_30j=30)],
    )

    incidents = anomalies.detect_anomalies(requete)

    assert len(incidents) == 1
    incident = incidents[0]
    assert incident["n_reports"] == 3
    assert incident["radius_m"] > 0
    assert incident["logements_estimes"] >= 120
    assert "incident majeur non déclaré" in incident["message"]
    assert incident["severity"] in ("modere", "eleve", "critique")


def test_trois_signalements_eloignes_aucun_incident() -> None:
    requete = AnomaliesRequest(
        signalements=[
            SignalementItem(id=1, lat=36.80, lng=10.18, quartier_id=1, statut="en_cours"),
            SignalementItem(id=2, lat=36.90, lng=10.30, quartier_id=2, statut="en_cours"),
            SignalementItem(id=3, lat=37.00, lng=10.45, quartier_id=3, statut="en_cours"),
        ],
        zones=[
            ZoneCentroide(quartier_id=1, nom="A", lat=36.80, lng=10.18, n_30j=30),
            ZoneCentroide(quartier_id=2, nom="B", lat=36.90, lng=10.30, n_30j=30),
            ZoneCentroide(quartier_id=3, nom="C", lat=37.00, lng=10.45, n_30j=30),
        ],
    )

    assert anomalies.detect_anomalies(requete) == []


def test_zone_calme_flags_zone_chargee_epargnee_par_poisson() -> None:
    """Même nombre de signalements : la zone calme est anormale, la chargée non."""

    def _points(base_lat: float, quartier: int) -> list[SignalementItem]:
        # Signalements ÉTALÉS (~700 m) : pas de cluster spatial, seul Poisson tranche.
        return [
            SignalementItem(id=quartier * 100 + i, lat=base_lat + 0.006 * i, lng=10.18,
                            quartier_id=quartier, statut="en_cours")
            for i in range(4)
        ]

    requete = AnomaliesRequest(
        signalements=_points(36.80, 10) + _points(36.95, 20),
        zones=[
            ZoneCentroide(quartier_id=10, nom="Calme", lat=36.80, lng=10.18, n_30j=3),
            ZoneCentroide(quartier_id=20, nom="Chargee", lat=36.95, lng=10.18, n_30j=300),
        ],
    )

    incidents = anomalies.detect_anomalies(requete)
    quartiers = {q for inc in incidents for q in inc["quartier_ids"]}

    assert 10 in quartiers, "la zone calme doit déclencher Poisson"
    assert 20 not in quartiers, "la zone chargée ne doit pas être flaggée"


def test_cluster_multi_quartiers_autorise() -> None:
    requete = AnomaliesRequest(
        signalements=[
            SignalementItem(id=1, lat=36.8000, lng=10.1800, quartier_id=1, statut="en_cours"),
            SignalementItem(id=2, lat=36.8005, lng=10.1805, quartier_id=1, statut="en_cours"),
            SignalementItem(id=3, lat=36.8010, lng=10.1810, quartier_id=2, statut="en_cours"),
        ],
        zones=[
            ZoneCentroide(quartier_id=1, nom="A", lat=36.80, lng=10.18, n_30j=60),
            ZoneCentroide(quartier_id=2, nom="B", lat=36.80, lng=10.18, n_30j=60),
        ],
    )

    incidents = anomalies.detect_anomalies(requete)

    assert len(incidents) == 1
    assert incidents[0]["quartier_ids"] == [1, 2]


def test_signalement_sans_coordonnees_utilise_centroide() -> None:
    """Sans coordonnées, la zone (centroïde) sert de position au signalement."""

    requete = AnomaliesRequest(
        signalements=[
            SignalementItem(id=1, quartier_id=1, statut="en_cours"),
            SignalementItem(id=2, quartier_id=1, statut="en_cours"),
            SignalementItem(id=3, quartier_id=1, statut="en_cours"),
        ],
        zones=[ZoneCentroide(quartier_id=1, nom="Centre", lat=36.80, lng=10.18, n_30j=3)],
    )

    incidents = anomalies.detect_anomalies(requete)

    assert len(incidents) == 1
    assert incidents[0]["n_reports"] == 3
    assert incidents[0]["centroid"] == [36.80, 10.18]


def test_poisson_survie_valeurs() -> None:
    assert anomalies._poisson_survie(0, 2.0) == 1.0
    assert anomalies._poisson_survie(3, 0.0) == 0.0
    # P(X >= 0) = 1 ; P(X >= 5 | 0.1) est extrêmement faible.
    assert anomalies._poisson_survie(5, 0.1) < 1e-6
    # Voisin de 1 - CDF(1) = 1 - e^-1 ≈ 0.632 pour n=2, λ=1.
    assert abs(anomalies._poisson_survie(2, 1.0) - 0.2642) < 1e-3
