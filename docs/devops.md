# ChillNet — chaîne DevOps (Jenkins · SonarQube · Kubernetes)

## 1. Vue d'ensemble

```
   développeur
       │  git push
       ▼
┌──────────────┐   analyse   ┌───────────────┐
│   Jenkins    │────────────▶│   SonarQube   │
│ 192.168.56.10│◀────────────│ 192.168.56.11 │  (quality gate + webhook)
└──────┬───────┘             └───────────────┘
       │ docker build/push
       ▼
┌───────────────────────┐   kubectl apply    ┌──────────────────────────┐
│ registre d'images     │◀───────────────────│  k3s (k8s)               │
│ 192.168.56.12:5000    │   containerd pull  │ 192.168.56.12            │
└───────────────────────┘───────────────────▶│ ns chillnet :            │
                                             │  mysql · chillnet-app    │
                                             │  ai-service · ingress    │
                                             └──────────────────────────┘
```

- **Jenkins** : lint, tests (PHP + Python), analyse, build/push des images, déploiement.
- **SonarQube** : qualité statique des deux projets (`chillnet`, `chillnet-ai-service`).
- **k3s** : cluster mono-nœud avec Traefik (Ingress) et local-path (volumes), plus un
  **registre Docker local** `192.168.56.12:5000` en HTTP simple.

## 2. Le lab Vagrant

| VM | IP | Rôle | Ressources |
|----|----|------|-----------|
| `jenkins` | 192.168.56.10 | CI/CD : PHP 8.3 + gd, Composer, Node 20, kubectl, Docker, python3 | 3 Go / 2 vCPU |
| `sonarqube` | 192.168.56.11 | SonarQube Community (conteneur, `:9000`) | 5 Go / 2 vCPU |
| `k8s` | 192.168.56.12 | k3s + registre `:5000` | 6 Go / 4 vCPU |

### Démarrage (reproductible)

```bash
cd devops-lab

# 1) Le cluster d'abord : il publie un kubeconfig à jour dans ./k3s.yaml
vagrant up k8s

# 2) Puis le reste (lit /vagrant/k3s.yaml distribué par la VM k8s)
vagrant up jenkins sonarqube

# Après modification du Vagrantfile :
vagrant provision k8s
vagrant provision jenkins      # re-distribue le kubeconfig si besoin
vagrant provision sonarqube
```

> Le provisionnement Jenkins copie `k3s.yaml` dans `~/.kube/config` et
> `/var/lib/jenkins/.kube/config`. **C'est ce qui manquait** : sans ce fichier,
> `kubectl` échouait (`invalid character '<' looking for beginning of value`).

## 3. Registre d'images

- Conteneur `registry:2` sur la VM k8s, port `5000`.
- k3s le considère comme **HTTP simple** via `/etc/rancher/k3s/registries.yaml`
  (`mirrors → http://192.168.56.12:5000`), puis redémarrage de `k3s`.
- Jenkins peut y **pousser** grâce à `/etc/docker/daemon.json`
  (`insecure-registries: ["192.168.56.12:5000"]`).

Vérification :

```bash
vagrant ssh k8s -c "curl -s http://localhost:5000/v2/_catalog"
```

## 4. Configuration de Jenkins (une fois)

1. **Plugins** : installer ceux de `jenkins/plugins.txt`
   (`kubernetes`, `nodejs`, `configuration-as-code`, `ws-cleanup`).
2. **Serveur SonarQube** : soit via `jenkins/casc.yaml`, soit manuellement —
   *Manage Jenkins → System → SonarQube servers* : name `sonarqube`,
   URL `http://192.168.56.11:9000`.
3. **Jeton SonarQube** : dans SonarQube, *My Account → Security → Generate Token*,
   puis dans Jenkins *Manage Credentials → add → Secret text* (id `sonarqube-token`).
4. **Webhook (indispensable au quality gate)** : dans SonarQube,
   *Administration → Configuration → Webhooks* →
   `http://192.168.56.10:8080/sonarqube-webhook/`.
5. **Secrets k8s** : *Manage Credentials → add → Secret file*, id `chillnet-k8s-env`,
   contenant le fichier `k8s/base/.env` (voir §6). Utilisé par le job **CD** uniquement.
6. **Deux jobs** (*New Item → Pipeline* ; *Pipeline script from SCM* → Git → ce dépôt) :
   - `chillnet-ci` — *Script Path* `Jenkinsfile` (CI : tests, analyse, images).
   - `chillnet-cd` — *Script Path* `Jenkinsfile.cd` (CD : déploiement k3s).
   Le nom du job CD **doit** être `chillnet-cd` : la CI le référence en dur dans
   `build job: 'chillnet-cd'`. Cocher *This project is parameterized* n'est pas requis
   (le paramètre `IMAGE_TAG` est déclaré dans le `Jenkinsfile.cd` lui-même).

## 5. Les pipelines (CI `Jenkinsfile` · CD `Jenkinsfile.cd`)

La CI et le CD sont **séparés** : la CI ne déploie jamais, le CD ne compile ni ne teste.
La CI déclenche le CD en fin de build réussi (branche `main` uniquement) via
`build job: 'chillnet-cd'`, en lui transmettant le tag d'image.

### CI (`Jenkinsfile` → job `chillnet-ci`)

| Stage | Rôle |
|-------|------|
| Checkout | clone + calcul du tag image (SHA court du commit) |
| Install | `composer install`, `npm ci`, **`npm run build`** |
| Lint (Pint) | `pint --test` — **informatif** (le dépôt n'est pas encore Pint-clean) |
| Test (PHP) | `php artisan test` + clover + junit |
| Test (Python) | `pytest` + couverture dans `ai-service/` |
| SonarQube (Laravel / ai-service) | analyse via l'image `sonarsource/sonar-scanner-cli` |
| Quality Gate | `waitForQualityGate` — échec du build si le gate échoue |
| Build / Push images | app + ai-service, tags `<sha-court>` et `latest` |
| Trigger CD | `build job: 'chillnet-cd'` (paramètre `IMAGE_TAG`), si branche `main` |

### CD (`Jenkinsfile.cd` → job `chillnet-cd`)

| Stage | Rôle |
|-------|------|
| Checkout | clone (pour les manifestes `k8s/base`) |
| Deploy to k3s | `kubectl apply -k k8s/base`, `set image` sur le tag reçu, Job de migration, `rollout status` |

Le job CD est **paramétré** (`IMAGE_TAG`, défaut `latest`) : il est déclenché
automatiquement par la CI, ou lanceable à la main (*Build with Parameters*) pour
redéployer un tag précis.

> **Séparer les responsabilités** évite de redonner au déploiement les outils de build :
> le job CD n'a besoin que de `kubectl` + le credential `chillnet-k8s-env` ; la CI n'a
> pas besoin du kubeconfig.

> **Ordre important** : `npm run build` doit précéder les tests PHP. Les layouts
> utilisent `@vite` ; sans `public/build/manifest.json` **tous** les tests
> d'affichage échouent (500 « Vite manifest not found »).

## 6. Kubernetes (`k8s/base`, kustomize)

| Ressource | Détail |
|-----------|--------|
| `namespace` | `chillnet` |
| `configmap` | env Laravel non sensible (`APP_ENV`, `DB_HOST=mysql`, `AI_SERVICE_URL`…) |
| `secret` | généré par `secretGenerator` depuis `k8s/base/.env` |
| `mysql` | Deployment + Service + PVC (local-path) |
| `chillnet-app` | Deployment (2 réplicas, initContainer « wait-for-mysql », probes `/up`) + Service |
| `chillnet-migrate` | Job `php artisan migrate --force` (appliqué hors kustomize) |
| `ai-service` | Deployment + Service (probes `/health`) |
| `ingress` | Traefik, hôte `chillnet.local` |

### Secrets

```bash
cp k8s/base/.env.example k8s/base/.env
# renseigner APP_KEY (php artisan key:generate --show), JWT_SECRET, DB_PASSWORD,
# DB_ROOT_PASSWORD, AI_SERVICE_TOKEN…
```

`k8s/base/.env` est **ignoré par git** ; en CI il est injecté depuis le credential
Jenkins `chillnet-k8s-env`.

### Déploiement manuel (hors Jenkins)

```bash
kubectl apply -k k8s/base
kubectl -n chillnet delete job chillnet-migrate --ignore-not-found
kubectl -n chillnet apply -f k8s/base/laravel/laravel-migrate-job.yaml
kubectl -n chillnet get pods,svc,ingress
```

### Accès à l'application

```bash
# Depuis l'hôte, via l'Ingress Traefik (port 80 du nœud k3s) :
curl -H 'Host: chillnet.local' http://192.168.56.12/up
```

Ou ajouter `192.168.56.12 chillnet.local` au fichier `hosts` de l'hôte.

## 7. Points d'attention connus

- **SonarQube RAM** : la VM a ~5 Go ; surveiller `free -m` si l'analyse ralentit.
- **`vm.max_map_count=262144`** : requis par l'Elasticsearch de SonarQube (posé par le
  provisionnement de la VM sonarqube).
- **Artefacts IA** : l'image `ai-service` embarque `model.joblib` et les CSV ; aucun
  volume n'est monté dessus (un volume vide masquerait les artefacts et forcerait le
  repli sur les règles). La persistance des ré-entraînements n'est donc pas assurée.
- **Worker de queue** : `QUEUE_CONNECTION=database` mais aucun worker n'est déployé ;
  les jobs restent en file (sans impact sur l'UI).
- **Pint** : le dépôt n'est pas encore formaté ; le stage de lint est non bloquant.
