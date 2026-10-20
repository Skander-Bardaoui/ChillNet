"""IA du Module 3 (points de fraîcheur + avis) — moteur dédié.

Ce paquet `app/fraicheur/` porte le modèle de SANTÉ PRÉDITE d'un point de
fraîcheur : à partir des caractéristiques du point (type, capacité,
équipements, horaires) et de l'historique de ses AVIS (notes, sentiment,
affluence ressentie, plaintes récurrentes), il estime la probabilité que le
point reçoive au moins deux avis négatifs dans les 14 prochains jours.

Même architecture que le module 2 (`app/coupures/`) : règles déterministes
(plancher de sécurité, parité stricte avec le PHP), classifieur entraîné et
calibré, explicabilité locale, artefacts DÉDIÉS (`artifacts/fraicheur/`).
"""

from __future__ import annotations

__all__ = ["rules", "schemas"]
