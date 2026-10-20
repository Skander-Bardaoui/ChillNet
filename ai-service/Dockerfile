# Étape 1 : dépendances. En CI, .wheels est rempli depuis le cache pip chaud
# (voir Jenkinsfile) : aucun téléchargement. Hors CI, le dossier est vide et
# pip retombe sur l'index PyPI.
FROM python:3.11-slim AS deps

WORKDIR /deps

COPY requirements.txt ./
COPY .wheels/ ./.wheels/

# PIP_OFFLINE=1 (CI) interdit tout accès à PyPI : installation garantie depuis
# le miroir de wheels. Vide hors CI : pip retombe sur l'index.
ARG PIP_OFFLINE=
RUN pip install --no-cache-dir --retries 10 --timeout 120 \
        --find-links=./.wheels ${PIP_OFFLINE:+--no-index} \
        --prefix=/install -r requirements.txt

FROM python:3.11-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

COPY --from=deps /install /usr/local

# Copie sélective : `.wheels/` (miroir injecté par la CI) ne doit pas entrer
# dans l'image finale.
COPY app ./app
COPY artifacts ./artifacts
COPY config ./config
COPY data ./data

# Les artefacts (model.joblib, metadata.json) et le CSV d'historique sont
# embarqués. En production, montez un volume sur /app/artifacts pour persister
# les ré-entraînements.
EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD ["python", "-c", "import urllib.request,sys; sys.exit(0 if urllib.request.urlopen('http://127.0.0.1:8001/health').status==200 else 1)"]

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8001"]
