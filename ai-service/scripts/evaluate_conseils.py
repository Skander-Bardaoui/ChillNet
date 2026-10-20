#!/usr/bin/env python
"""Évalue le modèle de risque équipements (Module 4) sur des foyers jamais vus.

Reproduit le découpage par foyer de ``train_conseils.py`` (même graine) et
compare le modèle à deux références : classe majoritaire et règles seules.
Mesure aussi le système réellement déployé : max(modèle, règles).

Usage : ``python scripts/evaluate_conseils.py`` (depuis ai-service/).
"""

from __future__ import annotations

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

import numpy as np                                                   # noqa: E402

from app.config import settings                                      # noqa: E402
from app.conseils.model import ConseilRiskModel                      # noqa: E402
from app.conseils.training import (                                  # noqa: E402
    CLASSES,
    baseline_majoritaire,
    charger_dataset,
    decouper,
    metriques,
    plancher,
    rapport_texte,
)

DATA_CSV = BASE_DIR / "data" / "equipements_risk_history.csv"


def _ligne(nom: str, m: dict) -> None:
    r = m["recall_par_classe"]
    print(f"  {nom:30s} {m['accuracy']:.3f}   {m['macro_f1']:.3f}   "
          f"{m['a_un_niveau_pres']:.3f}   {r['critique']:.3f}   {m['sous_estimation']:.3f}")


def _confusion(m: dict) -> None:
    entete = "vrai \\ prédit"
    print(f"  {entete:>14s}" + "".join(f"{c:>10s}" for c in CLASSES))
    for classe, ligne in zip(CLASSES, m["confusion"]):
        print(f"  {classe:>14s}" + "".join(f"{v:>10d}" for v in ligne))


def main() -> int:
    modele = ConseilRiskModel(settings.conseils_model_path, settings.conseils_metadata_path)
    if not modele.available:
        print("ERREUR : modèle non disponible. Lancez scripts/train_conseils.py", file=sys.stderr)
        return 1

    d = charger_dataset(DATA_CSV)
    tr, te = decouper(d["groupes"])
    X_test, y_test, regles = d["X"][te], d["y"][te], d["regles"][te]

    pipe = modele._pipeline
    proba = pipe.predict_proba(X_test)
    pred = pipe.predict(X_test)
    classes = [str(c) for c in pipe.classes_]

    m = metriques(y_test, pred, proba, classes)
    final = metriques(y_test, plancher(pred, regles))
    base = baseline_majoritaire(d["y"][tr], y_test)
    r = metriques(y_test, regles)

    print(f"Modèle {modele.version} ({modele.metadata().get('modele')}) — "
          f"{len(set(d['groupes'][te]))} foyers de test jamais vus, {len(y_test)} lignes\n")

    print("=" * 78)
    print(f"  {'':30s} accur.  macroF1  ±1 niv.  rec.crit  sous-est.")
    print("=" * 78)
    _ligne("Classe majoritaire", base)
    _ligne("Règles seules (rules.py)", r)
    _ligne("Modèle ML", m)
    _ligne("Système final max(ML, règles)", final)
    print()
    print(f"  Log-loss {m['log_loss']:.3f} | Brier {m['brier']:.3f} | "
          f"écart moyen {m['ecart_moyen_niveaux']:.3f} niveau")
    print()

    print("Rapport par classe (modèle ML)")
    print(rapport_texte(y_test, pred))
    print("Matrice de confusion (modèle ML)")
    _confusion(m)
    print()
    print("Matrice de confusion (système final)")
    _confusion(final)
    print()

    print("Importances par permutation (top 6)")
    for imp in modele.metadata().get("importances", [])[:6]:
        print(f"  {imp['feature']:30s} {imp['importance']:.4f}")
    print()

    # Contrôle anti-circularité : l'étiquette ne doit pas être recalculable par les règles.
    accord = float(np.mean(regles == y_test))
    print(f"Accord règles/étiquette : {accord:.1%} (≈100 % = étiquettes circulaires)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
