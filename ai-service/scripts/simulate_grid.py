"""Simulateur de réseau électrique → données d'entraînement du module 2.

Graine FIXE (reproductible). Produit 2 ans de données HORAIRES sur plusieurs
zones (quartiers), avec une charge croissante en température : au-dessus d'un
seuil de capacité, la probabilité de coupure (surcharge / délestage) monte, et
les durées suivent des lois lognormales par type.

Sorties (dans ``data/``) :
  - ``coupures_history.csv``      : table (zone, heure) de features + labels
                                    ``label_24h`` / ``label_48h`` (risque).
  - ``coupures_etr_history.csv``  : coupures RÉSOLUES + durée (ETR).

Colonne ``source`` = ``sim`` (les lignes réelles seront ajoutées par Laravel via
``ai:export-coupures`` et pondérées lors de l'entraînement).
"""

from __future__ import annotations

import sys
from pathlib import Path

import numpy as np
import pandas as pd

RACINE = Path(__file__).resolve().parents[1]
if str(RACINE) not in sys.path:
    sys.path.insert(0, str(RACINE))

from app.config import DATA_DIR  # noqa: E402
from app.features import heat_index_c  # noqa: E402

GRAINE = 20260101
N_JOURS = 365 * 2
N_HEURES = N_JOURS * 24

ZONES: list[dict] = [
    {"zone_id": 1, "nom": "Centre-Ville", "capacite_temp": 34.5, "base_temp": 0.0, "nb_logements": 3200, "nb_residences": 42},
    {"zone_id": 2, "nom": "Les Berges du Lac", "capacite_temp": 36.0, "base_temp": 0.5, "nb_logements": 2100, "nb_residences": 28},
    {"zone_id": 3, "nom": "La Marsa", "capacite_temp": 35.5, "base_temp": -0.5, "nb_logements": 1800, "nb_residences": 22},
    {"zone_id": 4, "nom": "Bab Souika", "capacite_temp": 33.8, "base_temp": 0.8, "nb_logements": 2600, "nb_residences": 35},
    {"zone_id": 5, "nom": "El Menzah", "capacite_temp": 35.2, "base_temp": 0.2, "nb_logements": 2000, "nb_residences": 26},
    {"zone_id": 6, "nom": "Ariana", "capacite_temp": 36.5, "base_temp": -0.3, "nb_logements": 1500, "nb_residences": 18},
]

# Durées (minutes) lognormales par type : (mu, sigma) de ln(durée).
DUREES = {
    "surcharge": (3.6, 0.5),
    "delestage": (3.9, 0.5),
    "panne": (4.8, 0.6),
    "maintenance": (5.2, 0.4),
}

SEUIL_JOUR_CHAUD = 35.0


def _temperature(zone: dict, rng: np.random.Generator) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    t_idx = np.arange(N_HEURES)
    hour_of_day = t_idx % 24
    day_of_year = (t_idx // 24) % 365

    seasonal = 12.0 * np.sin(2 * np.pi * (day_of_year - 100) / 365.0)
    daily = 6.0 * np.sin(2 * np.pi * (hour_of_day - 9) / 24.0)

    temp = 18.0 + seasonal + daily + zone["base_temp"] + rng.normal(0, 1.5, N_HEURES)
    humidity = np.clip(75 - 0.8 * (temp - 25) + rng.normal(0, 5, N_HEURES), 20, 95)

    heat_index = np.array(
        [heat_index_c(float(temp[i]), float(humidity[i])) or float(temp[i]) for i in range(N_HEURES)]
    )

    return temp, humidity, heat_index


def _jours_chauds_consecutifs(temp: np.ndarray, day_index: np.ndarray) -> np.ndarray:
    n_jours = int(day_index[-1]) + 1
    day_max = np.zeros(n_jours)

    for jour in range(n_jours):
        day_max[jour] = temp[day_index == jour].max()

    streak = np.zeros(n_jours)
    run = 0
    for jour in range(n_jours):
        streak[jour] = run
        run = run + 1 if day_max[jour] >= SEUIL_JOUR_CHAUD else 0

    return streak[day_index]


def _simuler_zone(zone: dict, base_time: pd.Timestamp, rng: np.random.Generator) -> tuple[pd.DataFrame, pd.DataFrame]:
    temp, humidity, heat_index = _temperature(zone, rng)
    t_idx = np.arange(N_HEURES)
    day_index = t_idx // 24

    over = np.maximum(0.0, temp - zone["capacite_temp"])

    # Propension d'événement : socle infrastructurel (toutes saisons) + part
    # CHALEUR qui croît fortement au-dessus de la capacité thermique de la zone.
    # En pleine vague de chaleur la probabilité horaire atteint ~40 %, ce qui rend
    # le risque à 24 h élevé (réel : les canicules saturent le réseau).
    prop_base = 0.0015
    prop_chaleur = np.where(over > 0.0, 0.15 * np.power(over / 3.0, 1.5), 0.0)
    prop_chaleur = np.minimum(0.45, prop_chaleur)
    p_start = np.minimum(0.5, prop_base + prop_chaleur)
    demarre = rng.random(N_HEURES) < p_start

    start_hours = np.nonzero(demarre)[0]

    types: list[str] = []
    heures_fin: list[int] = []
    durees_min: list[float] = []
    confirmations: list[int] = []

    for heure in start_hours:
        # Le TYPE est tiré au prorata de la propension chaleur vs socle : un
        # événement en pleine vague de chaleur est majoritairement surcharge/délestage.
        part_chaleur = prop_chaleur[heure] / (prop_base + prop_chaleur[heure])
        if rng.random() < part_chaleur:
            type_coupure = str(rng.choice(["surcharge", "delestage"]))
        else:
            type_coupure = str(rng.choice(["panne", "maintenance"], p=[0.7, 0.3]))
        mu, sigma = DUREES[type_coupure]
        duree_min = float(np.exp(rng.normal(mu, sigma)))
        types.append(type_coupure)
        durees_min.append(duree_min)
        heures_fin.append(int(heure + max(1, round(duree_min / 60))))
        confirmations.append(int(rng.integers(0, 6)))

    # --- Activité « en cours » (regarde vers le PASSÉ au moment t) ---
    diff_actif = np.zeros(N_HEURES + 1)
    for heure, fin in zip(start_hours, heures_fin):
        diff_actif[heure] += 1
        diff_actif[min(N_HEURES, fin)] -= 1
    en_cours = np.cumsum(diff_actif[:N_HEURES]).astype(int)

    # --- Labels FORWARD : coupure attendue dans (t, t+24h] / (t, t+48h] ---
    starts = np.zeros(N_HEURES + 1)
    if start_hours.size:
        starts[start_hours] = 1.0
    cum_starts = np.cumsum(starts)

    def _futur(horizon: int) -> np.ndarray:
        lo = np.arange(N_HEURES) + 1
        hi = np.minimum(np.arange(N_HEURES) + horizon, N_HEURES)
        return ((cum_starts[hi] - cum_starts[lo - 1]) > 0).astype(int)

    label_24h = _futur(24)
    label_48h = _futur(48)

    # --- Comptages glissants ---
    indicateur = np.zeros(N_HEURES + 1)
    indicateur[start_hours + 1] = 1
    cumul = np.cumsum(indicateur)

    def _fenetre(heures: int) -> np.ndarray:
        resultat = np.zeros(N_HEURES)
        for t in range(N_HEURES):
            debut = max(0, t - heures + 1)
            resultat[t] = cumul[t + 1] - cumul[debut]
        return resultat

    coupures_7j = _fenetre(168)
    coupures_14j = _fenetre(336)

    chaudes = np.array([1 if tp in ("surcharge", "delestage") else 0 for tp in types], dtype=float)
    ind_chaudes = np.zeros(N_HEURES + 1)
    if start_hours.size:
        ind_chaudes[start_hours + 1] = chaudes
    cumul_chaudes = np.cumsum(ind_chaudes)
    chaudes_14j = np.array(
        [cumul_chaudes[t + 1] - cumul_chaudes[max(0, t - 336 + 1)] for t in range(N_HEURES)]
    )
    part_chaleur_14j = np.divide(chaudes_14j, coupures_14j, out=np.zeros(N_HEURES), where=coupures_14j > 0)

    ind_conf = np.zeros(N_HEURES + 1)
    if start_hours.size:
        ind_conf[start_hours + 1] = np.array(confirmations, dtype=float)
    cumul_conf = np.cumsum(ind_conf)
    confirmations_total = np.array(
        [cumul_conf[t + 1] - cumul_conf[max(0, t - 336 + 1)] for t in range(N_HEURES)]
    )

    # Heures depuis la dernière coupure (plafonnée à 14 j).
    heures_depuis = np.full(N_HEURES, 336.0)
    dernier = -10_000
    curseur = 0
    for t in range(N_HEURES):
        while curseur < start_hours.size and start_hours[curseur] <= t:
            dernier = int(start_hours[curseur])
            curseur += 1
        heures_depuis[t] = min(336.0, float(t - dernier)) if dernier >= 0 else 336.0

    serie_temp = pd.Series(temp)
    max_24h = serie_temp.rolling(24, min_periods=1).max().to_numpy()
    max_48h = serie_temp.rolling(48, min_periods=1).max().to_numpy()

    jours_chauds = _jours_chauds_consecutifs(temp, day_index)

    timestamps = base_time + pd.to_timedelta(t_idx, unit="h")

    risque = pd.DataFrame(
        {
            "zone_id": zone["zone_id"],
            "timestamp": timestamps,
            "coupures_7j": coupures_7j,
            "coupures_14j": coupures_14j,
            "part_chaleur_14j": np.round(part_chaleur_14j, 4),
            "heures_depuis_derniere": heures_depuis,
            "en_cours": en_cours,
            "confirmations_total": confirmations_total,
            "temp": np.round(temp, 2),
            "ressentie": np.round(heat_index, 2),
            "heat_index": np.round(heat_index, 2),
            "max_24h": np.round(max_24h, 2),
            "max_48h": np.round(max_48h, 2),
            "canicule_niveau": [1 if x >= 35 else 0 for x in temp],
            "jours_chauds_consecutifs": jours_chauds,
            "heure": (t_idx % 24),
            "jour_semaine": ((t_idx // 24) + 0) % 7,
            "nb_logements_zone": zone["nb_logements"],
            "nb_residences": zone["nb_residences"],
            "label_24h": label_24h,
            "label_48h": label_48h,
            "source": "sim",
        }
    )

    # Niveau de canicule 0..3 dérivé des seuils (cohérent avec features.py).
    risque["canicule_niveau"] = np.select(
        [temp >= 41.0, temp >= 38.0, temp >= 35.0], [3, 2, 1], default=0
    )

    etr_rows = []
    for heure, type_coupure, duree_min, conf in zip(start_hours, types, durees_min, confirmations):
        etr_rows.append(
            {
                "zone_id": zone["zone_id"],
                "timestamp": timestamps[heure],
                "type": type_coupure,
                "heure": int(heure % 24),
                "jour_semaine": int((heure // 24) % 7),
                "temp": round(float(temp[heure]), 2),
                "heat_index": round(float(heat_index[heure]), 2),
                "charge_zone": round(float(min(1.0, over[heure] / 6.0)), 3),
                "confirmations": conf,
                "duration_min": round(float(duree_min), 1),
            }
        )

    etr = pd.DataFrame(etr_rows)

    # On retire les 48 dernières heures : leur label forward regarde AU-DELÀ de
    # l'horizon simulé (censuré) → labels non fiables.
    risque = risque.iloc[: N_HEURES - 48].reset_index(drop=True)

    return risque, etr


def main() -> int:
    rng = np.random.default_rng(GRAINE)
    base_time = pd.Timestamp("2024-01-01 00:00:00")

    risques: list[pd.DataFrame] = []
    etrs: list[pd.DataFrame] = []

    for zone in ZONES:
        risque, etr = _simuler_zone(zone, base_time, rng)
        risques.append(risque)
        etrs.append(etr)

    df_risque = pd.concat(risques, ignore_index=True)
    df_etr = pd.concat(etrs, ignore_index=True)

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    chemin_risque = DATA_DIR / "coupures_history.csv"
    chemin_etr = DATA_DIR / "coupures_etr_history.csv"

    df_risque.to_csv(chemin_risque, index=False)
    df_etr.to_csv(chemin_etr, index=False)

    print(f"coupures_history.csv      : {len(df_risque)} lignes -> {chemin_risque}")
    print(f"coupures_etr_history.csv  : {len(df_etr)} lignes -> {chemin_etr}")
    print(f"Taux label_24h = {df_risque['label_24h'].mean():.3f} ; label_48h = {df_risque['label_48h'].mean():.3f}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
