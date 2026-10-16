"""Générateur de messages en français — SANS LLM ni API externe.

Assemble des blocs rédigés (niveau × profil × type de lieu × pic horaire) en
3-4 phrases, au tutoiement, sans markdown. Les variantes sont choisies de façon
DÉTERMINISTE à partir d'une graine (identifiant d'alerte + profil), pour que le
même contexte produise toujours le même texte.

Les numéros d'urgence (190 / 198 / 0800 06 66 66) sont inclus dès le niveau
orange. Au plus deux phrases spécifiques au profil, dans l'ordre de priorité :
équipement médical > personne âgée > enfant.
"""

from __future__ import annotations

import zlib
from typing import Any

NUMEROS = "190, 198 ou 0800 06 66 66"

PROFILE_PRIORITY: list[str] = ["equipement_medical", "personne_agee", "enfant"]

# Variantes par profil (2 phrases max seront retenues).
PROFILE_SENTENCES: dict[str, list[str]] = {
    "equipement_medical": [
        "Avec un équipement médical à la maison, vérifie son alimentation de secours et garde les traitements au frais.",
        "Si un appareil médical tourne chez toi, anticipe une coupure de courant et prépare une solution de secours.",
    ],
    "personne_agee": [
        "Pour une personne âgée, propose-lui de l'eau régulièrement sans attendre la soif et garde-la dans la pièce la plus fraîche.",
        "Pense à la personne âgée de ton foyer : évite les sorties entre 11h et 17h et prends de ses nouvelles souvent.",
    ],
    "enfant": [
        "Ne laisse jamais un enfant seul dans un véhicule et fais-le boire souvent.",
        "Pour un enfant en bas âge, privilégie les pièces ombragées et des bains rafraîchissants.",
    ],
}

LEVEL_INTRO: dict[str, list[str]] = {
    "normal": [
        "Aujourd'hui, la chaleur reste supportable : reste simplement attentif à la météo.",
        "Pas de vigilance particulière aujourd'hui, mais garde les bons réflexes de saison.",
    ],
    "jaune": [
        "La chaleur s'installe : bois de l'eau régulièrement et ferme volets et rideaux dès 10h.",
        "Vigilance jaune : reste au frais aux heures chaudes et surveille les personnes fragiles.",
    ],
    "orange": [
        "Vigilance orange : la chaleur devient forte, bois de l'eau souvent et évite les efforts entre 11h et 17h.",
        "Avec cette vigilance orange, garde ton logement frais et limite les sorties aux heures les plus chaudes.",
    ],
    "rouge": [
        "Vigilance rouge : la chaleur est dangereuse, reste au frais et limite tout déplacement au strict nécessaire.",
        "Situation de canicule rouge : ne sors pas aux heures chaudes et rejoins un lieu frais si besoin.",
    ],
}

LIEU_CLAUSE: dict[str, list[str]] = {
    "domicile": [
        "À la maison, crée un courant d'air la nuit et regroupe-toi dans la pièce la plus fraîche.",
        "Chez toi, ferme les volets côté soleil et aère tôt le matin.",
    ],
    "travail": [
        "Au travail, hydrate-toi régulièrement et fais des pauses dans un espace frais.",
        "Sur ton lieu de travail, limite les efforts physiques et reste proche de l'eau.",
    ],
    "autre": [
        "En déplacement, garde une bouteille d'eau et cherche l'ombre ou la climatisation.",
        "Si tu es à l'extérieur, mets-toi à l'abri et bois avant d'avoir soif.",
    ],
}

PIC_SENTENCE = "Le pic de chaleur est attendu vers {heure}h, sois particulièrement prudent à ce moment-là."
SECOURS_SENTENCE = "En cas de malaise, appelle le {numeros} ; la plateforme canicule répond aussi au 0800 06 66 66."


def _seed_int(seed: Any) -> int:
    return zlib.crc32(str(seed).encode("utf-8")) if seed is not None else 0


def _select(options: list[str], seed: int) -> str:
    return options[seed % len(options)]


def _heure_pic(contexte: dict[str, Any]) -> int | None:
    heure = contexte.get("heure_pic")
    if isinstance(heure, str):
        fragment = heure.replace("h", ":").split(":")[0].strip()
        if fragment.isdigit():
            return int(fragment) % 24
    if isinstance(heure, (int, float)):
        return int(heure) % 24
    return None


def generer_message(
    contexte: dict[str, Any],
    profils: list[str] | None = None,
    seed: Any = None,
) -> str:
    """Message français déterministe (3-4 phrases)."""

    profils = profils or []
    base_seed = _seed_int(seed)

    niveau = str(contexte.get("niveau") or "jaune").lower()
    if niveau not in LEVEL_INTRO:
        niveau = "jaune"

    intro = _select(LEVEL_INTRO[niveau], base_seed)

    retenus = [p for p in PROFILE_PRIORITY if p in profils][:2]
    choix: list[str] = [
        _select(PROFILE_SENTENCES[profil], base_seed + 7 + index * 13)
        for index, profil in enumerate(retenus)
    ]

    lieu = contexte.get("lieu_type")
    if isinstance(lieu, str) and lieu in LIEU_CLAUSE:
        choix.append(_select(LIEU_CLAUSE[lieu], base_seed + 31))

    if niveau in ("orange", "rouge"):
        heure = _heure_pic(contexte)
        if heure is not None:
            choix.append(PIC_SENTENCE.format(heure=heure))
        # Les numéros d'urgence sont ESSENTIELS : on leur réserve le dernier
        # créneau, sinon deux profils + un lieu les écarteraient du message.
        queue = [SECOURS_SENTENCE.format(numeros=NUMEROS)]
        budget = 2  # intro + secours réservés → au plus 4 phrases
    else:
        choix.append("Bois de l'eau avant d'avoir soif et prends des nouvelles de tes voisins isolés.")
        queue = []
        budget = 3

    return " ".join([intro, *choix[:budget], *queue])
