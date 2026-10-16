"""Tests de l'« expert labeler » (``app.labeler``)."""

from __future__ import annotations

from app import labeler
from app.config import settings


def _row(temp_max: float, **extra) -> dict:
    return {"temp_max": temp_max, "humidite": None, **extra}


def test_label_renvoie_une_classe_connue() -> None:
    cfg = settings.labeler
    for temp in (10.0, 25.0, 33.0, 36.0, 40.0, 45.0):
        assert labeler.label(_row(temp), cfg, with_noise=False) in labeler.ORDRE


def test_label_monotone_croissant_avec_la_temperature() -> None:
    cfg = settings.labeler
    temperatures = [10.0, 25.0, 32.0, 33.0, 34.0, 35.0, 36.0, 37.9, 38.0, 40.9, 41.0, 45.0]

    precedent = -1
    for temp in temperatures:
        classe = labeler.label(_row(temp), cfg, with_noise=False)
        rang = labeler.ORDRE[classe]
        assert rang >= precedent, f"non monotone à {temp} °C ({classe})"
        precedent = rang


def test_label_borne_normal_puis_jaune() -> None:
    cfg = settings.labeler
    seuil = float(cfg["seuil"])
    marge = float(cfg["normal_marge"])

    assert labeler.label(_row(seuil - marge - 1.0), cfg, with_noise=False) == "normal"
    assert labeler.label(_row(seuil - marge), cfg, with_noise=False) == "jaune"


def test_label_borne_orange_et_rouge() -> None:
    cfg = settings.labeler
    seuil = float(cfg["seuil"])

    assert labeler.label(_row(seuil + float(cfg["orange_offset"])), cfg, with_noise=False) == "orange"
    assert labeler.label(_row(seuil + float(cfg["rouge_offset"])), cfg, with_noise=False) == "rouge"


def test_label_nuit_tropicale_aggrave() -> None:
    cfg = settings.labeler

    # 32 °C (< seuil - marge = 33) → normal ; +1 °C de nuit tropicale → jaune.
    sans = labeler.label(_row(32.0), cfg, with_noise=False)
    avec = labeler.label(_row(32.0, nuit_tropicale=1), cfg, with_noise=False)

    assert sans == "normal"
    assert avec == "jaune"
    assert labeler.ORDRE[avec] > labeler.ORDRE[sans]


def test_label_deterministe_avec_bruit() -> None:
    cfg = settings.labeler
    a = labeler.label(_row(37.0, humidite=65), cfg, with_noise=True, seed=7)
    b = labeler.label(_row(37.0, humidite=65), cfg, with_noise=True, seed=7)

    assert a == b
