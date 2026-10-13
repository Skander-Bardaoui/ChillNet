#!/usr/bin/env python
"""Récupère l'historique météo journalier depuis l'API Open-Meteo Archive.

Écrit ``data/weather_history.csv`` (une ligne par jour et par ville) avec les
colonnes : date, city, latitude, longitude, temp_max, temp_min, humidite,
ressentie.

Usage :
    python scripts/fetch_history.py --start 2015-01-01 --end 2024-12-31
"""

from __future__ import annotations

import argparse
import csv
import sys
import time
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

import requests  # noqa: E402

from app.config import DATA_DIR  # noqa: E402

VILLES = [
    ("Tunis", 36.8065, 10.1815),
    ("Sfax", 34.7406, 10.7603),
    ("Sousse", 35.8256, 10.6084),
    ("Nabeul", 36.4513, 10.7357),
    ("Bizerte", 37.2744, 9.8739),
    ("Gabes", 33.8815, 10.0982),
    ("Kairouan", 35.6781, 10.0963),
]

URL = "https://archive-api.open-meteo.com/v1/archive"

DAILY = ",".join(
    [
        "temperature_2m_max",
        "temperature_2m_min",
        "relative_humidity_2m_mean",
        "apparent_temperature_max",
    ]
)


def _fetch(ville: str, latitude: float, longitude: float, debut: str, fin: str) -> list[dict]:
    params = {
        "latitude": latitude,
        "longitude": longitude,
        "start_date": debut,
        "end_date": fin,
        "daily": DAILY,
        "timezone": "Africa/Tunis",
    }

    derniere_erreur: Exception | None = None
    for tentative in range(3):
        try:
            reponse = requests.get(URL, params=params, timeout=30)
            reponse.raise_for_status()
            break
        except Exception as exc:  # noqa: BLE001
            derniere_erreur = exc
            time.sleep(2 * (tentative + 1))
    else:
        raise RuntimeError(f"Open-Meteo injoignable pour {ville}: {derniere_erreur}")

    daily = reponse.json().get("daily", {})
    dates = daily.get("time", [])

    lignes: list[dict] = []
    for index, jour in enumerate(dates):
        lignes.append(
            {
                "date": jour,
                "city": ville,
                "latitude": latitude,
                "longitude": longitude,
                "temp_max": daily["temperature_2m_max"][index],
                "temp_min": daily["temperature_2m_min"][index],
                "humidite": daily["relative_humidity_2m_mean"][index],
                "ressentie": daily["apparent_temperature_max"][index],
            }
        )

    return lignes


def main() -> int:
    parser = argparse.ArgumentParser(description="Historique météo Open-Meteo Archive.")
    parser.add_argument("--start", default="2015-01-01")
    parser.add_argument("--end", default="2024-12-31")
    parser.add_argument("--out", default=str(DATA_DIR / "weather_history.csv"))
    args = parser.parse_args()

    toutes: list[dict] = []

    for ville, latitude, longitude in VILLES:
        print(f"→ {ville} ({args.start} → {args.end})")
        toutes.extend(_fetch(ville, latitude, longitude, args.start, args.end))

    chemin = Path(args.out)
    chemin.parent.mkdir(parents=True, exist_ok=True)

    with open(chemin, "w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(
            fh,
            fieldnames=["date", "city", "latitude", "longitude", "temp_max", "temp_min", "humidite", "ressentie"],
        )
        writer.writeheader()
        writer.writerows(toutes)

    print(f"{len(toutes)} lignes écrites dans {chemin}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
