"""Détecteur d'anomalies : afflux de signalements = incident majeur non déclaré.

Deux signaux complémentaires :

1. **Spatial** — ``DBSCAN`` (haversine, ``eps`` ≈ 500 m, ``min_samples`` = 3) sur
   les signalements des ``fenetre_minutes`` dernières minutes (statut ≠ résolue).
   Le poids d'un signalement vaut ``1 + 0.5·min(confirmations, 4)`` ; un
   signalement sans coordonnées est replacé au centroïde de sa zone (poids 0.5).
   Un cluster est retenu dès qu'il contient au moins ``seuil`` signalements.
   Les clusters multi-quartiers sont autorisés.
2. **Temporel** — baseline **Poisson** par zone sur 30 j (même fenêtre) : on
   calcule ``p = P(X ≥ n)`` (implémenté en ``math``/``lgamma``, sans SciPy) et on
   lève un signal si ``p < 0.01`` même sans cluster (afflux étalé).

Repli : si le clustering est impossible (aucune coordonnée exploitable ou
``scikit-learn`` absent), on retombe sur la règle fixe (≥ ``seuil``/zone).
"""

from __future__ import annotations

import math
from typing import Any

from .schemas import AnomaliesRequest

EPS_METRES = 500.0
MIN_SAMPLES = 3
RAYON_MARGE_M = 150.0
P_VALUE = 0.01
POISSON_FENETRE_JOURS = 30
LAMBDA_LISSAGE = 0.1
RAYON_TERRE_M = 6_371_000.0


def _haversine_m(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlambda = math.radians(lng2 - lng1)

    a = math.sin(dphi / 2) ** 2 + math.cos(phi1) * math.cos(phi2) * math.sin(dlambda / 2) ** 2

    return 2 * RAYON_TERRE_M * math.asin(min(1.0, math.sqrt(a)))


def _poisson_survie(n: int, lam: float) -> float:
    """``P(X ≥ n)`` pour ``X ~ Poisson(lam)`` — pur ``math`` + ``lgamma`` (D4)."""

    if n <= 0:
        return 1.0
    if lam <= 0.0:
        return 0.0

    log_lam = math.log(lam)
    cdf = 0.0
    for k in range(n):
        cdf += math.exp(-lam + k * log_lam - math.lgamma(k + 1))

    return max(0.0, min(1.0, 1.0 - cdf))


def _dans_fenetre(signalement: Any, fenetre: int) -> bool:
    if signalement.statut == "resolue":
        return False
    if signalement.minutes_ago is not None and signalement.minutes_ago > fenetre:
        return False
    return True


def _points(payload: AnomaliesRequest) -> tuple[list[tuple[float, float, float, Any]], dict]:
    """Points (lat, lng, poids, signalement) + index des zones par quartier."""

    zones = {zone.quartier_id: zone for zone in payload.zones}
    points: list[tuple[float, float, float, Any]] = []

    for signalement in payload.signalements:
        if not _dans_fenetre(signalement, payload.fenetre_minutes):
            continue

        if signalement.lat is not None and signalement.lng is not None:
            poids = 1.0 + 0.5 * min(signalement.confirmations, 4)
            points.append((signalement.lat, signalement.lng, poids, signalement))
        elif signalement.quartier_id is not None and signalement.quartier_id in zones:
            zone = zones[signalement.quartier_id]
            points.append((zone.lat, zone.lng, 0.5, signalement))

    return points, zones


def _clusters(points: list[tuple[float, float, float, Any]]) -> dict[int, list] | None:
    """Groupes DBSCAN, ou ``None`` si le clustering est impossible."""

    if len(points) < MIN_SAMPLES:
        return {}

    try:
        import numpy as np
        from sklearn.cluster import DBSCAN
    except Exception:  # noqa: BLE001 — repli règle fixe
        return None

    X = np.asarray([[math.radians(p[0]), math.radians(p[1])] for p in points])
    poids = np.asarray([p[2] for p in points])

    db = DBSCAN(
        eps=EPS_METRES / RAYON_TERRE_M,
        min_samples=MIN_SAMPLES,
        metric="haversine",
        algorithm="ball_tree",
    )
    labels = db.fit_predict(X, sample_weight=poids)

    groupes: dict[int, list] = {}
    for point, label in zip(points, labels):
        if label < 0:
            continue
        groupes.setdefault(int(label), []).append(point)

    return groupes


def _logements(residences: list[Any], lat: float, lng: float, rayon_m: float) -> int:
    total = 0
    for residence in residences:
        if residence.lat is None or residence.lng is None:
            continue
        if _haversine_m(lat, lng, residence.lat, residence.lng) <= rayon_m:
            total += residence.nombre_logements
    return total


def _severite(n_reports: int, seuil: int, logements: int) -> str:
    if n_reports >= seuil + 2 or logements >= 500:
        return "critique"
    if n_reports >= seuil + 1 or logements >= 200:
        return "eleve"
    return "modere"


def _incident_depuis_cluster(
    index: int, membres: list, payload: AnomaliesRequest, nom_zone: dict
) -> dict[str, Any]:
    n_reports = len(membres)
    lat = sum(m[0] for m in membres) / n_reports
    lng = sum(m[1] for m in membres) / n_reports

    radius = max(
        (_haversine_m(lat, lng, m[0], m[1]) for m in membres),
        default=0.0,
    ) + RAYON_MARGE_M

    confirmations = sum(m[3].confirmations for m in membres)
    quartier_ids = sorted({m[3].quartier_id for m in membres if m[3].quartier_id is not None})
    logements = _logements(payload.residences, lat, lng, radius)

    densite = min(1.0, n_reports / (payload.seuil * 2.0))
    conf_ratio = min(1.0, confirmations / max(1.0, n_reports * 3.0))
    confidence = round(min(1.0, 0.5 * densite + 0.5 * conf_ratio), 3)

    nom = next((nom_zone[q] for q in quartier_ids if q in nom_zone), None) or (
        f"zones {', '.join(map(str, quartier_ids))}" if quartier_ids else "la zone"
    )

    return {
        "id": f"cluster-{index}",
        "centroid": [round(lat, 6), round(lng, 6)],
        "radius_m": round(radius, 1),
        "n_reports": n_reports,
        "n_confirmations": confirmations,
        "quartier_ids": quartier_ids,
        "logements_estimes": logements,
        "confidence": confidence,
        "severity": _severite(n_reports, payload.seuil, logements),
        "message": (
            f"{n_reports} signalements en {payload.fenetre_minutes} min près de {nom} : "
            f"possible incident majeur non déclaré (~{logements} logements concernés)"
        ),
        "source": "modele",
    }


def _incidents_poisson(
    payload: AnomaliesRequest, deja_couverts: set[int]
) -> list[dict[str, Any]]:
    """Signaux temporels : zone dont l'afflux dépasse nettement sa baseline."""

    if not payload.zones:
        return []

    lambdas = [z.n_30j / POISSON_FENETRE_JOURS for z in payload.zones if z.n_30j > 0]
    global_lambda = (sum(lambdas) / len(lambdas)) if lambdas else LAMBDA_LISSAGE

    observees: dict[int, int] = {}
    for signalement in payload.signalements:
        if not _dans_fenetre(signalement, payload.fenetre_minutes):
            continue
        if signalement.quartier_id is not None:
            observees[signalement.quartier_id] = observees.get(signalement.quartier_id, 0) + 1

    incidents: list[dict[str, Any]] = []

    for zone in payload.zones:
        if zone.quartier_id in deja_couverts:
            continue

        n = observees.get(zone.quartier_id, 0)
        if n < payload.seuil:
            continue

        lam = zone.n_30j / POISSON_FENETRE_JOURS if zone.n_30j > 0 else global_lambda
        p = _poisson_survie(n, lam)

        if p >= P_VALUE:
            continue

        nom = zone.nom or f"zone {zone.quartier_id}"
        logements = _logements(payload.residences, zone.lat, zone.lng, 300.0)

        incidents.append(
            {
                "id": f"poisson-{zone.quartier_id}",
                "centroid": [round(zone.lat, 6), round(zone.lng, 6)],
                "radius_m": 300.0,
                "n_reports": n,
                "n_confirmations": sum(
                    s.confirmations
                    for s in payload.signalements
                    if s.quartier_id == zone.quartier_id and _dans_fenetre(s, payload.fenetre_minutes)
                ),
                "quartier_ids": [zone.quartier_id],
                "logements_estimes": logements,
                "confidence": round(min(1.0, 0.5 + (1.0 - p)), 3),
                "severity": _severite(n, payload.seuil, logements),
                "message": (
                    f"Afflux inhabituel de {n} signalements en {payload.fenetre_minutes} min "
                    f"près de {nom} (baseline {lam:.2f}) : possible incident majeur non déclaré "
                    f"(~{logements} logements concernés)"
                ),
                "source": "poisson",
            }
        )

    return incidents


def _fallback_regles(payload: AnomaliesRequest) -> list[dict[str, Any]]:
    """Règle fixe (Phase A) : ≥ ``seuil`` signalements par quartier."""

    zones = {zone.quartier_id: zone for zone in payload.zones}
    par_zone: dict[int, list[Any]] = {}

    for signalement in payload.signalements:
        if not _dans_fenetre(signalement, payload.fenetre_minutes):
            continue
        if signalement.quartier_id is None:
            continue
        par_zone.setdefault(signalement.quartier_id, []).append(signalement)

    incidents: list[dict[str, Any]] = []

    for quartier_id, items in par_zone.items():
        if len(items) < payload.seuil:
            continue

        zone = zones.get(quartier_id)
        if zone is None:
            continue

        nom = zone.nom or f"zone {quartier_id}"
        incidents.append(
            {
                "id": f"zone-{quartier_id}",
                "centroid": [round(zone.lat, 6), round(zone.lng, 6)],
                "radius_m": 300.0,
                "n_reports": len(items),
                "n_confirmations": sum(s.confirmations for s in items),
                "quartier_ids": [quartier_id],
                "logements_estimes": _logements(payload.residences, zone.lat, zone.lng, 300.0),
                "confidence": round(min(1.0, len(items) / (payload.seuil * 2.0)), 3),
                "severity": _severite(len(items), payload.seuil, 0),
                "message": (
                    f"{len(items)} signalements en {payload.fenetre_minutes} min près de {nom} : "
                    f"possible incident majeur non déclaré "
                    f"(~{_logements(payload.residences, zone.lat, zone.lng, 300.0)} logements concernés)"
                ),
                "source": "regles",
            }
        )

    return incidents


def detect_anomalies(payload: AnomaliesRequest) -> list[dict[str, Any]]:
    """Liste des incidents suspects (contrat commun à toutes les phases)."""

    points, zones = _points(payload)
    nom_zone = {q: (z.nom or f"zone {q}") for q, z in zones.items()}

    groupes = _clusters(points)

    if groupes is None:
        return _fallback_regles(payload)

    incidents: list[dict[str, Any]] = []
    couverts: set[int] = set()
    index = 0

    for membres in groupes.values():
        if len(membres) < payload.seuil:
            continue
        incident = _incident_depuis_cluster(index, membres, payload, nom_zone)
        incidents.append(incident)
        couverts.update(incident["quartier_ids"])
        index += 1

    incidents.extend(_incidents_poisson(payload, couverts))

    return incidents
