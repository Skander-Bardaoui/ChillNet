"""Tests du feature engineering (``app.features``)."""

from __future__ import annotations

from pytest import approx

from app.features import FEATURE_ORDER, build_features, heat_index_c, vector


def test_heat_index_sous_26_7_renvoie_la_temperature_seche() -> None:
    assert heat_index_c(20.0, 80.0) == 20.0


def test_heat_index_manquant_si_une_entree_absente() -> None:
    assert heat_index_c(None, 60.0) is None
    assert heat_index_c(35.0, None) is None


def test_heat_index_aggrave_le_ressenti_par_humidite() -> None:
    assert heat_index_c(35.0, 80.0) > 35.0


def test_build_features_ordre_et_valeurs_de_base() -> None:
    features = build_features({"temperature": 40.0, "humidite": 50, "seuil_temperature": 35.0})

    assert list(features.keys()) == FEATURE_ORDER
    assert features["temp"] == 40.0
    assert features["temp_minus_seuil"] == approx(5.0)


def test_build_features_valeurs_par_defaut_sans_contexte() -> None:
    features = build_features({})

    assert features["temp"] == 25.0
    assert features["humidite"] == 50.0
    assert features["jours_chauds_consecutifs"] == 0
    assert features["nuit_tropicale"] == 0


def test_build_features_nuit_tropicale_et_heure_pic() -> None:
    previsions = [
        {"heure": "13:00", "temperature": 34.0},
        {"heure": "15:00", "temperature": 39.0},
        {"heure": "03:00", "temperature": 26.0},
    ]
    features = build_features(
        {"temperature": 34.0, "seuil_temperature": 35.0, "previsions": previsions}
    )

    assert features["max_24h"] == 39.0
    assert features["max_48h"] == 39.0
    assert features["heure_pic"] == 15
    # min des prévisions = 26 > 25 → nuit tropicale.
    assert features["nuit_tropicale"] == 1


def test_nuit_tropicale_absente_quand_il_fait_frais_la_nuit() -> None:
    previsions = [{"heure": "03:00", "temperature": 18.0}, {"heure": "15:00", "temperature": 36.0}]
    features = build_features({"temperature": 36.0, "previsions": previsions})

    assert features["nuit_tropicale"] == 0
    assert features["max_24h"] == 36.0


def test_vector_suit_feature_order() -> None:
    features = build_features({"temperature": 40.0, "humidite": 50})
    ordonne = vector(features)

    assert len(ordonne) == len(FEATURE_ORDER)
    assert ordonne[0] == features["temp"]
