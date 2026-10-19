"""Simulateur points de fraîcheur + avis → données d'entraînement du module 3.

Graine FIXE (reproductible). Produit des données JOURNALIÈRES pour un réseau de
points de fraîcheur (parcs, salles climatisées, fontaines) répartis dans les
gouvernorats tunisiens :

  - MÉTÉO RÉELLE : températures maximales et pluie journalières de chaque
    gouvernorat (export Open-Meteo, ``data/meteo_gouvernorats.csv`` produit par
    ``scripts/import_meteo.py``) ; à défaut, météo synthétique de Tunis ;
  - fréquentation qui croît avec la chaleur (plus forte pour les salles
    climatisées et les fontaines), d'où une affluence ressentie ;
  - DÉGRADATIONS persistantes (quelques jours) : saleté (favorisée par la
    foule), panne de climatisation / eau coupée (favorisée par la chaleur),
    équipements cassés (favorisés par l'âge et la mauvaise qualité) ;
  - AVIS des visiteurs : note 1–5, sentiment du commentaire, affluence ressentie
    et thème de la plainte quand l'avis est négatif.

Chaque ligne = (point, jour) : features du point et de ses avis À LA FIN du
jour (mêmes définitions que ``app/fraicheur/features.py`` et que Laravel) +
label ``label_14j`` = au moins 2 avis négatifs dans les 14 jours SUIVANTS.

Sortie : ``data/fraicheur_history.csv`` (colonne ``source`` = ``sim`` ; les
lignes réelles seront ajoutées par Laravel via ``ai:export-fraicheur`` dans un
fichier distinct et sur-pondérées à l'entraînement).
"""

from __future__ import annotations

import math
import sys
from pathlib import Path

import numpy as np
import pandas as pd

RACINE = Path(__file__).resolve().parents[1]
if str(RACINE) not in sys.path:
    sys.path.insert(0, str(RACINE))

from app.config import settings  # noqa: E402
from app.fraicheur.features import (  # noqa: E402
    CAP_JOURS_NEGATIF,
    NOTE_A_PRIORI,
    POIDS_A_PRIORI,
    TYPE_CODES,
    VECTOR_ORDER,
    canicule_niveau_depuis_temp,
)

GRAINE = 20261019
# Période simulée : celle de la météo réelle (fixée dans main()), sinon 2 ans.
N_JOURS = 365 * 2
DEBUT = pd.Timestamp("2024-01-01")
N_POINTS = 120
METEO_CSV = settings.fraicheur_history_csv.parent / "meteo_gouvernorats.csv"
# Le réseau ChillNet est centré sur le Grand Tunis ; le reste des points est
# réparti dans les autres gouvernorats (climats contrastés : Bizerte ↔ Tozeur).
GRAND_TUNIS = ("Tunis", "Ariana", "Ben Arous", "Manouba")
PART_GRAND_TUNIS = 0.4

FENETRE = 30            # fenêtre « récente » des avis (jours)
FENETRE_PRECEDENTE = 90  # fenêtre de comparaison (J-120, J-30]
HORIZON = 14            # horizon de la cible (jours)
SEUIL_NEGATIFS = 2      # au moins 2 avis négatifs → point « dégradé »
# Une ligne tous les 3 jours par point : les jours consécutifs d'un même point
# sont quasi identiques (forte autocorrélation) ; l'échantillonnage limite la
# redondance sans perdre la dynamique des dégradations (~12 jours).
PAS_ECHANTILLON = 3

TAUX_AVIS = 0.022       # part des visiteurs qui laissent un avis
THEMES = ("proprete", "chaleur", "equipements")

# Impact d'une dégradation active sur la note moyenne attendue d'un avis.
IMPACT_NOTE = {"proprete": 1.5, "chaleur": 1.7, "equipements": 1.2}


def _meteo_reelle() -> tuple[pd.Timestamp, dict[str, dict[str, np.ndarray]]] | None:
    """Météo réelle par gouvernorat : ``{ville: {"temp": tmax, "pluie": mm}}``.

    Ne garde que les dates communes à tous les gouvernorats (séries complètes).
    """

    if not METEO_CSV.exists():
        return None

    meteo = pd.read_csv(METEO_CSV, parse_dates=["date"])
    dates = sorted(set.intersection(*(set(g["date"]) for _, g in meteo.groupby("ville"))))
    if len(dates) < 120:
        return None

    meteo = meteo[meteo["date"].isin(dates)].sort_values(["ville", "date"])
    series = {
        ville: {
            "temp": g["temperature_max"].to_numpy(dtype=float),
            "pluie": g["precipitation"].fillna(0.0).to_numpy(dtype=float),
        }
        for ville, g in meteo.groupby("ville")
    }

    return pd.Timestamp(dates[0]), series


def _temperatures(rng: np.random.Generator) -> np.ndarray:
    """REPLI sans météo réelle : températures maximales synthétiques de Tunis
    (saison + bruit + vagues de chaleur)."""

    jours = np.arange(N_JOURS)
    jour_annee = jours % 365

    temp = 25.0 + 9.0 * np.sin(2 * np.pi * (jour_annee - 105) / 365.0)
    temp += rng.normal(0.0, 2.0, N_JOURS)

    # 3 à 5 vagues de chaleur par été (forme triangulaire, +4 à +8 °C).
    for annee in range(N_JOURS // 365):
        for _ in range(int(rng.integers(3, 6))):
            debut = annee * 365 + int(rng.integers(160, 245))
            duree = int(rng.integers(3, 9))
            amplitude = float(rng.uniform(4.0, 8.0))
            for k in range(duree):
                if debut + k < N_JOURS:
                    forme = 1.0 - abs((k - (duree - 1) / 2) / max(1.0, duree / 2))
                    temp[debut + k] += amplitude * (0.5 + 0.5 * forme)

    return np.round(temp, 1)


def _points(rng: np.random.Generator, villes: list[str]) -> list[dict]:
    """Réseau de points aux caractéristiques réalistes par type et par ville."""

    grand_tunis = [v for v in villes if v in GRAND_TUNIS]
    autres = [v for v in villes if v not in GRAND_TUNIS] or villes
    points: list[dict] = []

    for point_id in range(1, N_POINTS + 1):
        ville = str(rng.choice(grand_tunis if grand_tunis and rng.random() < PART_GRAND_TUNIS else autres))
        type_point = str(rng.choice(["parc", "salle_climatisee", "fontaine"], p=[0.45, 0.30, 0.25]))

        if type_point == "parc":
            capacite = int(rng.integers(60, 400))
            climatise = False
            ombrage = bool(rng.random() < 0.85)
            eau = bool(rng.random() < 0.5)
            ouvert_24h = bool(rng.random() < 0.3)
            heures = 24 if ouvert_24h else int(rng.integers(11, 17))
        elif type_point == "salle_climatisee":
            capacite = int(rng.integers(15, 150))
            climatise = bool(rng.random() < 0.95)
            ombrage = bool(rng.random() < 0.2)
            eau = bool(rng.random() < 0.7)
            heures = int(rng.integers(8, 13))
        else:
            capacite = 0  # une fontaine n'a pas de capacité d'accueil
            climatise = False
            ombrage = bool(rng.random() < 0.5)
            eau = True
            ouvert_24h = bool(rng.random() < 0.7)
            heures = 24 if ouvert_24h else int(rng.integers(12, 17))

        points.append(
            {
                "point_id": point_id,
                "ville": ville,
                "type": type_point,
                "capacite": capacite,
                "climatise": climatise,
                "ombrage": ombrage,
                "eau_potable": eau,
                "accessible_pmr": bool(rng.random() < 0.6),
                "heures_ouverture_jour": heures,
                "a_photo": bool(rng.random() < 0.6),
                # Qualité intrinsèque (entretien, confort) et popularité.
                "qualite": float(rng.beta(5, 2)),
                "popularite": float(rng.lognormal(math.log(14.0), 0.5)),
            }
        )

        # La moitié existe dès le départ (depuis 0 à 4 ans, avec l'historique
        # d'avis correspondant) ; les autres ouvrent pendant la simulation. Les
        # âges et volumes d'avis restent ainsi comparables d'une année à l'autre.
        point = points[-1]
        if rng.random() < 0.5:
            point["debut"] = 0
            point["age_initial"] = int(rng.integers(0, 1460))
        else:
            point["debut"] = int(rng.integers(0, max(1, N_JOURS - 200)))
            point["age_initial"] = 0

        moyenne_historique = min(5.0, max(1.0, 1.4 + 3.4 * point["qualite"] - 0.25))
        point["n_initial"] = int(rng.poisson(point["age_initial"] * point["popularite"] * 0.8 * TAUX_AVIS))
        point["somme_notes_initiale"] = point["n_initial"] * moyenne_historique

    return points


def _simuler_point(point: dict, meteo: dict[str, np.ndarray], rng: np.random.Generator) -> pd.DataFrame:
    temp = meteo["temp"]
    pluie = meteo["pluie"]
    debut = point["debut"]
    capacite_effective = point["capacite"] if point["capacite"] > 0 else 40
    concentration = math.sqrt(12.0 / max(6.0, float(point["heures_ouverture_jour"])))

    # Agrégats journaliers des avis.
    n = np.zeros(N_JOURS)
    somme_notes = np.zeros(N_JOURS)
    negatifs = np.zeros(N_JOURS)
    somme_sentiment = np.zeros(N_JOURS)
    affluence_renseignee = np.zeros(N_JOURS)
    affluence_forte = np.zeros(N_JOURS)
    plaintes = {theme: np.zeros(N_JOURS) for theme in THEMES}

    qualite = point["qualite"]
    chocs: dict[str, int] = {}

    for jour in range(debut, N_JOURS):
        chaleur = max(0.0, float(temp[jour]) - 30.0) / 10.0
        age = (point["age_initial"] + jour - debut) / 365.0

        # --- Fréquentation : la chaleur pousse vers les points de fraîcheur ---
        facteur = 0.5 + 0.5 * min(1.5, max(0.0, (float(temp[jour]) - 18.0) / 14.0))
        if point["type"] == "salle_climatisee":
            facteur *= 1.0 + 0.8 * chaleur
        elif point["type"] == "fontaine":
            facteur *= 1.0 + 0.6 * chaleur
        else:
            facteur *= (1.0 + 0.3 * chaleur) if point["ombrage"] else (1.0 - 0.2 * chaleur)
        # Un jour de pluie vide les espaces extérieurs (parcs, fontaines).
        if pluie[jour] >= 1.0 and point["type"] != "salle_climatisee":
            facteur *= 0.6

        visiteurs = int(rng.poisson(point["popularite"] * facteur))
        foule = visiteurs / capacite_effective * concentration
        p_forte = 1.0 / (1.0 + math.exp(-5.0 * (foule - 0.8)))

        # --- Qualité : marche aléatoire rappelée vers la qualité intrinsèque ---
        qualite = min(0.98, max(0.05, qualite + 0.03 * (point["qualite"] - qualite) + float(rng.normal(0.0, 0.01))))

        # --- Dégradations persistantes ---
        p_choc = {
            "proprete": 0.003 + 0.015 * min(foule, 2.0) + 0.006 * (1.0 - qualite),
            "equipements": 0.002 + 0.004 * min(age, 3.0) + 0.006 * (1.0 - qualite),
        }
        if point["type"] == "salle_climatisee":
            p_choc["chaleur"] = 0.002 + 0.03 * chaleur * (1.0 if point["climatise"] else 0.5)
        elif point["type"] == "fontaine":
            p_choc["chaleur"] = 0.003 + 0.02 * chaleur
        else:
            p_choc["chaleur"] = (0.001 + 0.006 * chaleur) if point["ombrage"] else (0.002 + 0.025 * chaleur)

        for theme, probabilite in p_choc.items():
            if theme not in chocs and rng.random() < probabilite:
                chocs[theme] = max(3, int(rng.lognormal(2.5, 0.5)))  # médiane ~12 jours

        # --- Avis du jour ---
        for _ in range(int(rng.poisson(visiteurs * TAUX_AVIS))):
            moyenne = 1.4 + 3.4 * qualite
            for theme in chocs:
                moyenne -= IMPACT_NOTE[theme]

            forte = bool(rng.random() < p_forte)
            if forte:
                moyenne -= 0.9
            if not point["climatise"] and not point["ombrage"]:
                moyenne -= 0.8 * chaleur

            note = int(min(5, max(1, round(float(rng.normal(moyenne, 0.75))))))

            if rng.random() < 0.7:
                affluence_renseignee[jour] += 1
                affluence_forte[jour] += int(forte)

            sentiment = 0.6 * (note - 3) / 2.0 + float(rng.normal(0.0, 0.12)) - (0.15 if chocs else 0.0)
            sentiment = max(-1.0, min(1.0, sentiment))

            n[jour] += 1
            somme_notes[jour] += note
            somme_sentiment[jour] += sentiment

            if note <= 2 or sentiment < -0.15:
                negatifs[jour] += 1
                # Le commentaire évoque le problème en cours (ou, rarement, un autre).
                if chocs and rng.random() < 0.75:
                    plaintes[str(rng.choice(sorted(chocs)))][jour] += 1
                elif rng.random() < 0.08:
                    plaintes[str(rng.choice(THEMES))][jour] += 1

        for theme in list(chocs):
            chocs[theme] -= 1
            if chocs[theme] <= 0:
                del chocs[theme]

    return _lignes(point, temp, n, somme_notes, negatifs, somme_sentiment,
                   affluence_renseignee, affluence_forte, plaintes)


def _lignes(
    point: dict,
    temp: np.ndarray,
    n: np.ndarray,
    somme_notes: np.ndarray,
    negatifs: np.ndarray,
    somme_sentiment: np.ndarray,
    affluence_renseignee: np.ndarray,
    affluence_forte: np.ndarray,
    plaintes: dict[str, np.ndarray],
) -> pd.DataFrame:
    """Features à la fin de chaque jour + label prospectif (14 j)."""

    def cumul(serie: np.ndarray) -> np.ndarray:
        return np.concatenate([[0.0], np.cumsum(serie)])

    C = {
        "n": cumul(n),
        "notes": cumul(somme_notes),
        "neg": cumul(negatifs),
        "sent": cumul(somme_sentiment),
        "aff_r": cumul(affluence_renseignee),
        "aff_f": cumul(affluence_forte),
        **{f"pl_{t}": cumul(plaintes[t]) for t in THEMES},
    }

    def somme(cle: str, de: int, a: int) -> float:
        """Somme sur les jours [de, a] inclus (bornés à l'horizon simulé)."""

        de = max(0, de)
        if a < de:
            return 0.0
        return float(C[cle][a + 1] - C[cle][de])

    debut = point["debut"]
    dernier_negatif = -10_000
    lignes: list[dict] = []

    for jour in range(debut, N_JOURS - HORIZON):
        if negatifs[jour] > 0:
            dernier_negatif = jour

        if (jour - debut) % PAS_ECHANTILLON != 0:
            continue

        n_total = point["n_initial"] + somme("n", 0, jour)
        n_30 = somme("n", jour - FENETRE + 1, jour)
        n_prec = somme("n", jour - FENETRE - FENETRE_PRECEDENTE + 1, jour - FENETRE)

        note_moyenne = (point["somme_notes_initiale"] + somme("notes", 0, jour) + POIDS_A_PRIORI * NOTE_A_PRIORI) / (n_total + POIDS_A_PRIORI)
        moyenne_30 = somme("notes", jour - FENETRE + 1, jour) / n_30 if n_30 > 0 else None
        moyenne_prec = (
            somme("notes", jour - FENETRE - FENETRE_PRECEDENTE + 1, jour - FENETRE) / n_prec
            if n_prec > 0 else None
        )
        evolution = (
            moyenne_30 - moyenne_prec
            if moyenne_30 is not None and moyenne_prec is not None and n_30 >= 2 and n_prec >= 2
            else 0.0
        )

        aff_r = somme("aff_r", jour - FENETRE + 1, jour)
        jours_negatif = min(CAP_JOURS_NEGATIF, float(jour - dernier_negatif)) if dernier_negatif >= 0 else CAP_JOURS_NEGATIF

        futur_negatifs = somme("neg", jour + 1, jour + HORIZON)

        lignes.append(
            {
                "point_id": point["point_id"],
                "ville": point["ville"],
                "date": (DEBUT + pd.Timedelta(days=jour)).date().isoformat(),
                "type_code": TYPE_CODES[point["type"]],
                "capacite": point["capacite"],
                "climatise": int(point["climatise"]),
                "ombrage": int(point["ombrage"]),
                "eau_potable": int(point["eau_potable"]),
                "accessible_pmr": int(point["accessible_pmr"]),
                "heures_ouverture_jour": point["heures_ouverture_jour"],
                "anciennete_jours": point["age_initial"] + jour - debut,
                "a_photo": int(point["a_photo"]),
                "note_moyenne": round(note_moyenne, 3),
                "nb_avis_30j": int(n_30),
                "note_moyenne_30j": round(moyenne_30 if moyenne_30 is not None else note_moyenne, 3),
                "evolution_note_30j": round(evolution, 3),
                "part_negatifs_30j": round(somme("neg", jour - FENETRE + 1, jour) / n_30, 4) if n_30 > 0 else 0.0,
                "jours_depuis_dernier_negatif": jours_negatif,
                "part_affluence_forte_30j": round(somme("aff_f", jour - FENETRE + 1, jour) / aff_r, 4) if aff_r > 0 else 0.0,
                "sentiment_moyen_30j": round(somme("sent", jour - FENETRE + 1, jour) / n_30, 4) if n_30 > 0 else 0.0,
                "plaintes_proprete_30j": int(somme("pl_proprete", jour - FENETRE + 1, jour)),
                "plaintes_chaleur_30j": int(somme("pl_chaleur", jour - FENETRE + 1, jour)),
                "plaintes_equipements_30j": int(somme("pl_equipements", jour - FENETRE + 1, jour)),
                "temperature": float(temp[jour]),
                "canicule_niveau": canicule_niveau_depuis_temp(float(temp[jour])),
                "mois": int((DEBUT + pd.Timedelta(days=jour)).month),
                "label_14j": int(futur_negatifs >= SEUIL_NEGATIFS),
                "source": "sim",
            }
        )

    return pd.DataFrame(lignes)


def main() -> int:
    global N_JOURS, DEBUT

    rng = np.random.default_rng(GRAINE)

    reelle = _meteo_reelle()
    if reelle is not None:
        DEBUT, meteo = reelle
        N_JOURS = len(next(iter(meteo.values()))["temp"])
        origine = f"météo réelle Open-Meteo ({len(meteo)} gouvernorats)"
    else:
        meteo = {"Tunis": {"temp": _temperatures(rng), "pluie": np.zeros(N_JOURS)}}
        origine = f"météo SYNTHÉTIQUE ({METEO_CSV.name} absent : lancez scripts/import_meteo.py)"

    points = _points(rng, sorted(meteo))

    df = pd.concat([_simuler_point(point, meteo[point["ville"]], rng) for point in points], ignore_index=True)
    df = df.sort_values(["date", "point_id"]).reset_index(drop=True)

    colonnes = ["point_id", "ville", "date", *VECTOR_ORDER, "label_14j", "source"]
    df = df[colonnes]

    chemin = settings.fraicheur_history_csv
    chemin.parent.mkdir(parents=True, exist_ok=True)
    df.to_csv(chemin, index=False)

    print(f"fraicheur_history.csv : {len(df)} lignes ({df['point_id'].nunique()} points, "
          f"{df['ville'].nunique()} villes, du {df['date'].min()} au {df['date'].max()}) -> {chemin}")
    print(f"Source météo : {origine}")
    print(f"Taux label_14j = {df['label_14j'].mean():.3f}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
