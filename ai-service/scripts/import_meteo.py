#!/usr/bin/env python
"""Importe l'export Open-Meteo (.xlsx) des 24 gouvernorats → ``data/meteo_gouvernorats.csv``.

Source : export « Historical Weather » d'Open-Meteo (open-meteo.com), données
JOURNALIÈRES réelles par gouvernorat (``location_id`` 0..23) :
température max / min / moyenne à 2 m, cumul de précipitations, vent max.

Le classeur contient deux blocs dans la même feuille :
  1. les lieux     : location_id, latitude, longitude, elevation, … ;
  2. les mesures   : location_id, time (date Excel), temperature_2m_max, …

Lecture SANS dépendance externe (un .xlsx est une archive zip de XML) : pas
besoin d'openpyxl. Le CSV produit est la météo réelle utilisée par le
simulateur du module 3 (``scripts/simulate_fraicheur.py``).

Usage : ``python scripts/import_meteo.py chemin/vers/open-meteo-….xlsx``
"""

from __future__ import annotations

import csv
import re
import sys
import xml.etree.ElementTree as ET
import zipfile
from datetime import date, timedelta
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
if str(RACINE) not in sys.path:
    sys.path.insert(0, str(RACINE))

from app.config import DATA_DIR  # noqa: E402

SORTIE = DATA_DIR / "meteo_gouvernorats.csv"

# Correspondance location_id → gouvernorat (table fournie avec l'export).
VILLES: dict[int, str] = {
    0: "Ariana", 1: "Béja", 2: "Ben Arous", 3: "Bizerte", 4: "Gabès", 5: "Gafsa",
    6: "Jendouba", 7: "Kairouan", 8: "Kasserine", 9: "Kébili", 10: "Le Kef", 11: "Mahdia",
    12: "Manouba", 13: "Médenine", 14: "Monastir", 15: "Nabeul", 16: "Sfax", 17: "Sidi Bouzid",
    18: "Siliana", 19: "Sousse", 20: "Tataouine", 21: "Tozeur", 22: "Tunis", 23: "Zaghouan",
}

COLONNES = [
    "location_id", "ville", "latitude", "longitude", "elevation", "date",
    "temperature_max", "temperature_min", "temperature_moyenne", "precipitation", "vent_max",
]

_NS = "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}"
_EPOQUE_EXCEL = date(1899, 12, 30)


def _index_colonne(reference: str | None, position: int) -> int:
    if not reference:
        return position
    lettres = re.match(r"[A-Z]+", reference)
    if lettres is None:
        return position
    n = 0
    for lettre in lettres.group(0):
        n = n * 26 + (ord(lettre) - 64)
    return n - 1


def lire_lignes(chemin: Path) -> list[list[str]]:
    """Toutes les lignes (textes) de la première feuille du classeur."""

    with zipfile.ZipFile(chemin) as archive:
        partagees: list[str] = []
        if "xl/sharedStrings.xml" in archive.namelist():
            for si in ET.fromstring(archive.read("xl/sharedStrings.xml")).findall(f"{_NS}si"):
                partagees.append("".join(t.text or "" for t in si.iter(f"{_NS}t")))

        feuilles = sorted(
            (n for n in archive.namelist() if re.fullmatch(r"xl/worksheets/sheet\d+\.xml", n)),
            key=lambda n: int(re.search(r"\d+", n.rsplit("/", 1)[1]).group(0)),
        )
        if not feuilles:
            raise SystemExit(f"Aucune feuille dans {chemin}")

        lignes: list[list[str]] = []
        for row in ET.fromstring(archive.read(feuilles[0])).iter(f"{_NS}row"):
            valeurs: dict[int, str] = {}
            for position, cellule in enumerate(row.findall(f"{_NS}c")):
                v = cellule.find(f"{_NS}v")
                texte = v.text if v is not None and v.text is not None else ""
                if cellule.get("t") == "s" and texte:
                    texte = partagees[int(texte)]
                elif cellule.get("t") == "inlineStr":
                    texte = "".join(t.text or "" for t in cellule.iter(f"{_NS}t"))
                valeurs[_index_colonne(cellule.get("r"), position)] = texte.strip()
            if valeurs:
                lignes.append([valeurs.get(i, "") for i in range(max(valeurs) + 1)])

        return lignes


def _date(valeur: str) -> str:
    """Date Excel (nombre de jours) ou ISO → ``AAAA-MM-JJ``."""

    try:
        return (_EPOQUE_EXCEL + timedelta(days=int(float(valeur)))).isoformat()
    except ValueError:
        return valeur[:10]


def convertir(chemin: Path) -> list[dict]:
    lignes = lire_lignes(chemin)

    lieux: dict[int, dict] = {}
    mesures: list[dict] = []
    bloc = None

    for ligne in lignes:
        if not ligne or not ligne[0]:
            continue
        if ligne[0] == "location_id":
            bloc = "mesures" if "time" in ligne else "lieux"
            continue
        if not ligne[0].isdigit():
            continue

        location = int(ligne[0])
        if bloc == "lieux":
            lieux[location] = {
                "latitude": float(ligne[1]),
                "longitude": float(ligne[2]),
                "elevation": float(ligne[3] or 0),
            }
        elif bloc == "mesures" and len(ligne) >= 7 and ligne[2] != "":
            lieu = lieux.get(location, {})
            mesures.append(
                {
                    "location_id": location,
                    "ville": VILLES.get(location, f"lieu {location}"),
                    "latitude": lieu.get("latitude"),
                    "longitude": lieu.get("longitude"),
                    "elevation": lieu.get("elevation"),
                    "date": _date(ligne[1]),
                    "temperature_max": float(ligne[2]),
                    "temperature_min": float(ligne[3]),
                    "temperature_moyenne": float(ligne[4]),
                    "precipitation": float(ligne[5] or 0),
                    "vent_max": float(ligne[6] or 0),
                }
            )

    mesures.sort(key=lambda m: (m["location_id"], m["date"]))
    return mesures


def main() -> int:
    if len(sys.argv) < 2:
        print(__doc__)
        return 1

    source = Path(sys.argv[1])
    if not source.exists():
        print(f"Fichier introuvable : {source}", file=sys.stderr)
        return 1

    mesures = convertir(source)
    if not mesures:
        print("Aucune mesure météo trouvée dans le classeur.", file=sys.stderr)
        return 1

    SORTIE.parent.mkdir(parents=True, exist_ok=True)
    with open(SORTIE, "w", encoding="utf-8", newline="") as fh:
        ecrivain = csv.DictWriter(fh, fieldnames=COLONNES)
        ecrivain.writeheader()
        ecrivain.writerows(mesures)

    villes = sorted({m["ville"] for m in mesures})
    dates = sorted({m["date"] for m in mesures})
    print(f"{len(mesures)} mesures journalières, {len(villes)} gouvernorats, du {dates[0]} au {dates[-1]} -> {SORTIE}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
