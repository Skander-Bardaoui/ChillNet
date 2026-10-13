"""Règles déterministes de niveaux — PORT FIDÈLE de ``VigilanceAiService::niveauPour``.

Ce module est la référence « métier » : il sert de PLANCHER DE SÉCURITÉ (le
niveau final ne peut jamais lui être inférieur) et de REPLI quand le modèle est
indisponible. Il doit rester STRICTEMENT aligné avec l'implémentation PHP — le
test de parité ``tests/test_rules_parity.py`` (fixture partagée
``tests/fixtures/rules_cases.json``) le garantit.

Seuls jaune/orange/rouge existent ici : l'application n'expose pas de niveau
« normal » (l'absence de chaleur reste traitée comme vigilance jaune, comme
avant la migration). La classe « normal » n'existe que côté modèle entraîné.
"""

from __future__ import annotations

NIVEAUX = ("jaune", "orange", "rouge")


def niveau_regles(
    temperature: float,
    humidite: float | None = None,
    jours_surchauffe: int = 0,
    seuil: float = 35.0,
) -> str:
    """Classe déterministe (jaune/orange/rouge) d'une situation.

    Température effective = température + pénalité humidité (>= 60 %) +
    pénalité canicule installée (>= 3 jours), puis seuils à +3 °C et +6 °C, et
    escalade d'un cran si l'humidité est très élevée (>= 70 %) ou la vague de
    chaleur durable (>= 3 jours).
    """

    effective = float(temperature)

    if humidite is not None and humidite >= 60:
        effective += min(3.0, (humidite - 60) * 0.1)

    if jours_surchauffe >= 3:
        effective += 2.0

    if effective >= seuil + 6:
        niveau = "rouge"
    elif effective >= seuil + 3:
        niveau = "orange"
    else:
        niveau = "jaune"

    if (humidite is not None and humidite >= 70) or jours_surchauffe >= 3:
        niveau = {"jaune": "orange", "orange": "rouge", "rouge": "rouge"}[niveau]

    return niveau
