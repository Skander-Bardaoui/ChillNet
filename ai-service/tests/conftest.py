"""Configuration pytest du microservice IA.

Ajoute la racine ``ai-service/`` au ``sys.path`` (pour ``import app.*`` sans
installation) et expose une fixture ``client`` (FastAPI ``TestClient``).
"""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

RACINE = Path(__file__).resolve().parents[1]
if str(RACINE) not in sys.path:
    sys.path.insert(0, str(RACINE))


@pytest.fixture()
def client():
    from fastapi.testclient import TestClient

    from app.main import app

    return TestClient(app)
