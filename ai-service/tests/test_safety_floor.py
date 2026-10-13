"""Plancher de sécurité : le niveau final n'est JAMAIS sous les règles.

Le modèle est remplacé par un bouchon déterministe pour prouver que
``analyser`` applique bien ``final = max(modèle, règles)`` quelle que soit la
prédiction du modèle.
"""

from __future__ import annotations

from app import main as main_module
from app.schemas import AnalyzeRequest

ORDRE = {"normal": 0, "jaune": 1, "orange": 2, "rouge": 3}


class _StubModele:
    available = True
    classes = ["jaune", "normal", "orange", "rouge"]
    version = "stub-0.0.0"

    def __init__(self, classe: str) -> None:
        self._classe = classe

    def predict(self, features: dict) -> tuple[str, dict, float]:
        probas = {c: 0.01 for c in self.classes}
        probas[self._classe] = 0.9

        return self._classe, probas, 0.9

    def facteurs(self, features: dict, top: int = 3) -> list:
        return []


def _analyser(monkeypatch, classe_modele: str, temperature: float):
    monkeypatch.setattr(main_module, "model", _StubModele(classe_modele))

    return main_module.analyser(AnalyzeRequest(temperature=temperature))


def test_modele_sous_les_regles_est_releve(monkeypatch) -> None:
    # Règles = rouge (42 °C), modèle = jaune → le final reste rouge.
    resultat = _analyser(monkeypatch, "jaune", 42.0)

    assert resultat.niveau_regles == "rouge"
    assert resultat.niveau_modele == "jaune"
    assert resultat.niveau_final == "rouge"
    assert resultat.desaccord is True


def test_modele_normal_ne_descend_pas_sous_jaune(monkeypatch) -> None:
    resultat = _analyser(monkeypatch, "normal", 37.0)

    assert resultat.niveau_regles == "jaune"
    assert resultat.niveau_final == "jaune"
    assert resultat.desaccord is True


def test_modele_peut_escalader(monkeypatch) -> None:
    resultat = _analyser(monkeypatch, "rouge", 37.0)

    assert resultat.niveau_regles == "jaune"
    assert resultat.niveau_final == "rouge"
    assert resultat.desaccord is True


def test_modele_aligne_pas_de_desaccord(monkeypatch) -> None:
    resultat = _analyser(monkeypatch, "rouge", 42.0)

    assert resultat.niveau_final == "rouge"
    assert resultat.desaccord is False


def test_plancher_sur_une_plage_de_temperatures(monkeypatch) -> None:
    for classe in ("normal", "jaune", "orange", "rouge"):
        for temp in (30.0, 35.0, 37.0, 38.0, 41.0, 45.0):
            resultat = _analyser(monkeypatch, classe, temp)

            assert ORDRE[resultat.niveau_final] >= ORDRE[resultat.niveau_regles], (
                f"plancher violé : modèle={classe}, temp={temp}, "
                f"règles={resultat.niveau_regles}, final={resultat.niveau_final}"
            )
