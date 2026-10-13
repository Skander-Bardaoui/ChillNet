"""Test d'intégration léger de la chaîne d'entraînement (données + pipeline)."""

from __future__ import annotations

from sklearn.ensemble import RandomForestClassifier
from sklearn.impute import SimpleImputer
from sklearn.pipeline import Pipeline

from app import dataset
from app.features import FEATURE_ORDER


def test_build_dataset_depuis_echantillon() -> None:
    ds = dataset.build_dataset(with_noise=False)

    assert ds["X"].shape[1] == len(FEATURE_ORDER)
    assert ds["X"].shape[0] > 100
    assert set(ds["y"]).issubset(set(dataset.CLASSES))
    assert ds["hash"] != ""


def test_pipeline_scikit_apprend_et_predit() -> None:
    ds = dataset.build_dataset(with_noise=True)
    X, y = ds["X"], ds["y"]

    pipeline = Pipeline(
        [
            ("imputer", SimpleImputer(strategy="median")),
            ("rf", RandomForestClassifier(n_estimators=10, random_state=42, class_weight="balanced")),
        ]
    )
    pipeline.fit(X, y)

    predictions = pipeline.predict(X)

    assert len(predictions) == len(y)
    assert set(predictions).issubset(set(dataset.CLASSES))


def test_corrections_template_coherent() -> None:
    X, y = dataset.load_corrections()

    assert X.shape[0] == len(y)
    assert set(y).issubset(set(dataset.CLASSES))
