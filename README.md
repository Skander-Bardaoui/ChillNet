<p align="center">
  <img src="docs/banner.svg" width="100%" alt="ChillNet banner" />
</p>

<p align="center">
  <img src="https://readme-typing-svg.demolab.com?font=Fira+Code&weight=600&size=22&pause=1200&color=0EA5E9&center=true&vCenter=true&width=760&lines=Citizen+heatwave+vigilance;Laravel+%2B+FastAPI+%2B+scikit-learn;Alerts%2C+refuges%2C+mutual+aid" alt="Typing tagline" />
</p>

<p align="center">
  <img src="https://img.shields.io/github/languages/top/Skander-Bardaoui/ChillNet?style=flat-square&color=0EA5E9" alt="" />
  <img src="https://img.shields.io/github/stars/Skander-Bardaoui/ChillNet?style=flat-square&color=0EA5E9" alt="" />
  <img src="https://img.shields.io/github/last-commit/Skander-Bardaoui/ChillNet?style=flat-square&color=38BDF8" alt="" />
  <img src="https://img.shields.io/badge/status-Active-0EA5E9?style=flat-square" alt="" />
</p>

## Overview

ChillNet is a citizen platform for heatwave vigilance and neighbourhood solidarity. Residents receive clear alerts, find air-conditioned refuges and fresh points nearby, and coordinate mutual aid during heat peaks. A dedicated machine-learning microservice predicts heat risk and explains its decisions to managers.

## Highlights

- Heatwave alerts with severity levels, geographic targeting and personalised messages
- Map of climate refuges, fresh points and community resources around the household
- Power-outage reporting and confirmation by residents
- Reports with PDF export and geolocation of every signalment
- Resident, manager and administrator roles with scoped dashboards
- Heat-risk prediction and explainability from a FastAPI + scikit-learn service

## Tech stack

<p align="center">
  <img src="https://skillicons.dev/icons?i=laravel,php,python,postgres,docker,kubernetes,jenkins,tailwind&theme=dark&perline=8" alt="Tech stack" />
</p>

## Screenshots

<p align="center">
  <a href="docs/screenshots/landing.webp"><img src="docs/screenshots/landing.webp" width="49%" alt="Landing page" /></a>
  <a href="docs/screenshots/login.webp"><img src="docs/screenshots/login.webp" width="49%" alt="Sign in" /></a>
  <a href="docs/screenshots/admin.webp"><img src="docs/screenshots/admin.webp" width="49%" alt="Admin dashboard" /></a>
  <a href="docs/screenshots/habitant.webp"><img src="docs/screenshots/habitant.webp" width="49%" alt="Resident space" /></a>
</p>

## Getting started

**Prerequisites:** PHP 8.2+, Composer, Node.js 20+, PostgreSQL (or SQLite for a quick local run), Python 3.11+ for the AI service.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env, then:
php artisan migrate --seed
npm run build
php artisan serve

# AI microservice
cd ai-service
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8001
```

## Project structure

```text
app/            Laravel domain (controllers, models, services, policies)
ai-service/     FastAPI + scikit-learn heat-risk service
database/       migrations and demo seeders
docs/           product and architecture notes
docker/         container definitions
jenkins/        CI/CD pipeline
k8s/            Kubernetes manifests
```

---

<p align="center">
  <img src="https://capsule-render.vercel.app/api?type=waving&color=0:0EA5E9,100:0A0A0C&height=110&section=footer" alt="footer" width="100%" />
</p>

<p align="center">
  Built by <a href="https://github.com/Skander-Bardaoui"><b>Skander Bardaoui</b></a> ·
  <a href="https://skander-bardaoui.netlify.app/">Portfolio</a> ·
  <a href="https://github.com/Skander-Bardaoui?tab=repositories">All repositories</a>
</p>
