"""Contrat API du classifieur de catégories de signalement."""

from __future__ import annotations

from pydantic import BaseModel, Field


class ClassificationRequest(BaseModel):
    description: str = Field(min_length=1, max_length=2000)


class ClassificationResponse(BaseModel):
    categorie: str
    confiance: int = Field(ge=0, le=100)
    probabilites: dict[str, float]
    model_version: str | None = None
