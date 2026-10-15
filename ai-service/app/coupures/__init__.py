"""IA du Module 2 (coupures) — moteur dédié, isolé du module canicule.

Ce paquet `app/coupures/` regroupe TOUTE l'intelligence du module coupures :
règles déterministes (plancher de sécurité), modèle de risque entraîné,
détecteur d'anomalies spatiales et estimateur de temps de rétablissement.

Chaque modèle a son PROPRE dossier d'artefacts (`artifacts/coupures/`) pour
rester distinguable du modèle canicule (`artifacts/`).
"""

from __future__ import annotations

__all__ = ["rules", "schemas"]
