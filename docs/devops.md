# ChillNet — chaîne DevOps (Jenkins · SonarQube · Kubernetes)

## 1. Vue d'ensemble

```
                       git push (branche main)
   développeur ─────────────────────────────┐
                                            ▼
                                   ┌─────────────────┐
                                   │ GitHub  (main)  │
                                   └────────┬────────┘
                                            │ webhook / poll SCM
        ┌───────────────────────────────────┼──────────────────────────────────┐
        │  CI  (job ChillNet)               ▼                                  │
        │  ┌──────────────┐   analyse   ┌───────────────┐                      │
        │  │   Jenkins    │────────────▶│   SonarQube   │   quality gate       │
        │  │ 192.168.56.10│◀────────────│ 192.168.56.11 │   + webhook          │
        │  └──────┬───────┘             └───────────────┘                      │
        │         │ docker build/push                                          │
        │         ▼                                                            │
        │  ┌────────────────────┐   pull (containerd)   ┌────────────────────┐ │
        │  │ registre d'images  │──────────────────────▶│  k3s  192.168.56.12│ │
        │  │ 192.168.56.12:5000 │                       │  ns chillnet :     │ │
        │  └────────────────────┘                       │   mysql            │ │
        │         ▲                                     │   chillnet-app x2  │ │
        │         │        CD (job chillnet-cd)         │   ai-service       │ │
        │         └───── kubectl apply -k ◀─────────────│   ingress .local   │ │
        │                                    ▲          │  ns monitoring :   │ │
        │                                    │          │   prometheus       │ │
        │  CI ──build job──▶ CD ─────────────┘          │   grafana          │ │
        └───────────────────────────────────────────────│   node-exporter    │─┘
                                                        │   kube-state-m.    │
                                                        └────────────────────┘
```

- **Jenkins** : lint, tests (PHP + Python), analyse, build/push des images ; déclenche le CD.
- **SonarQube** : qualité statique des deux projets (`chillnet`, `chillnet-ai-service`).
- **k3s** : cluster mono-nœud avec Traefik (Ingress) et local-path (volumes), plus un
  **registre Docker local** `192.168.56.12:5000` en HTTP simple.
  Deux namespaces : **`chillnet`** (application) et **`monitoring`** (Prometheus + Grafana).

> Le détail de la supervision est dans [`monitoring.md`](monitoring.md).

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
   contenant le fichier `k8s/base/.env` (voir §6), et id `chillnet-monitoring-env`
   contenant `k8s/monitoring/.env` (`GRAFANA_ADMIN_PASSWORD`). Utilisés par le job **CD**.
6. **Deux jobs** (*New Item → Pipeline* ; *Pipeline script from SCM* → Git → ce dépôt) :
   - `ChillNet` — *Script Path* `Jenkinsfile` (CI : tests, analyse, images).
   - `chillnet-cd` — *Script Path* `Jenkinsfile.cd` (CD : déploiement k3s).
   Le nom du job CD **doit** être `chillnet-cd` : la CI le référence en dur dans
   `build job: 'chillnet-cd'`. Cocher *This project is parameterized* n'est pas requis
   (le paramètre `IMAGE_TAG` est déclaré dans le `Jenkinsfile.cd` lui-même).

### Déclenchement automatique (webhook GitHub)

Le job `ChillNet` déclare désormais :

```groovy
triggers {
  githubPush()               // webhook GitHub -> build immédiat
  pollSCM('H/5 * * * *')     // repli : vérifie le dépôt toutes les 5 min
}
```

**Contrainte réseau** : Jenkins écoute sur `192.168.56.10`, une adresse **privée**.
GitHub.com **ne peut pas la joindre** : un webhook pointant directement sur
`http://192.168.56.10:8080/github-webhook/` ne sera jamais délivré. Il faut exposer
Jenkins via un relais ou un tunnel.

**Quelles branches déclenchent la CI ?**

GitHub ne permet **pas** de filtrer un webhook par branche : chaque push, sur
n'importe quelle branche, est délivré. Le filtrage se fait côté Jenkins :

- le job `ChillNet` a pour *Branch Specifier* **`*/main`** (idem pour `chillnet-cd`) ;
- `githubPush()` réagit à la livraison en lançant simplement un **poll SCM** du job ;
  si `main` n'a pas changé, aucun build n'est planifié.

Résultat : un push sur une branche de feature est ignoré ; la CI part uniquement
quand `main` avance, c'est-à-dire au **merge d'une pull request** (ou à un push
direct sur `main`). Pour interdire le push direct et n'autoriser que les merges :
GitHub → *Settings → Branches → Add branch protection rule* → branche `main` →
*Require a pull request before merging*.

> **Premier déclenchement** : les triggers sont enregistrés dans le job par le
> premier build qui exécute ce `Jenkinsfile`. Tant que le bloc `triggers` n'a
> jamais été construit sur `main` (`<triggers/>` vide dans la config du job),
> lancer **Build Now** une fois après le merge. Les merges suivants déclencheront
> la CI automatiquement.

**Option A — relais smee.io (recommandé en lab, aucun port entrant)**

Implémenté sur la VM `jenkins` : canal
`https://smee.io/Bgiiv4tBR4Sytcjd`, client `smee-client@5` installé
globalement (`/usr/bin/smee`), service systemd `smee-chillnet` qui
le maintient actif au démarrage.

1. Créer un canal sur <https://smee.io> (redirection 307 de `/new`) → URL
   `https://smee.io/<id>`.
2. Installer le client :
   ```bash
   sudo npm install -g smee-client
   ```
3. Vérifier l'URL cible : `http://localhost:8080/github-webhook/`
   (**slash final obligatoire**). Le client v5 supprime ce slash
   (`trimTrailingSlash` dans `index.js`) : Jenkins répond alors
   `302 → /github-webhook/`, et `fetch` rejoue la redirection en **GET**,
   d'où un `405`. Correctif appliqué sur la VM :
   ```bash
   sudo sed -i 's|this.#target = trimTrailingSlash(new URL(target).toString());|this.#target = new URL(target).toString();|' \
     /usr/lib/node_modules/smee-client/index.js
   ```
   Après mise à jour du paquet, **réappliquer ce correctif**.
4. Service systemd `/etc/systemd/system/smee-chillnet.service` :
   ```ini
   [Unit]
   Description=Smee.io relay -> Jenkins GitHub webhook
   After=network-online.target jenkins.service
   Wants=network-online.target

   [Service]
   Type=simple
   ExecStart=/usr/bin/smee --url https://smee.io/Bgiiv4tBR4Sytcjd --target http://localhost:8080/github-webhook/
   Restart=always
   RestartSec=5
   User=vagrant

   [Install]
   WantedBy=multi-user.target
   ```
   ```bash
   sudo systemctl daemon-reload && sudo systemctl enable --now smee-chillnet
   sudo journalctl -u smee-chillnet -f   # doit afficher « - 200 » par événement
   ```
   (`smee-client` v5 n'accepte plus `--events` : le filtrage se fait côté GitHub.)
5. GitHub → dépôt → *Settings → Webhooks → Add webhook* :
   - **Payload URL** : `https://smee.io/Bgiiv4tBR4Sytcjd`
   - **Content type** : `application/json`
   - **Which events** : *Just the push event*
   - **Active** : coché

Test manuel du relais (simule GitHub, doit répondre `200` dans le journal
du service) :
```bash
curl -X POST https://smee.io/Bgiiv4tBR4Sytcjd \
  -H 'Content-Type: application/json' -H 'X-GitHub-Event: ping' \
  -H 'X-GitHub-Delivery: test-1' \
  -d '{"zen":"x","hook_id":1,"repository":{"full_name":"a/b"}}'
```

**Option B — tunnel cloudflared**

```bash
curl -L -o /tmp/cloudflared https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64
chmod +x /tmp/cloudflared
/tmp/cloudflared tunnel --url http://localhost:8080
```

→ URL publique `https://<random>.trycloudflare.com` (change à chaque redémarrage).
Webhook GitHub : `https://<random>.trycloudflare.com/github-webhook/`
(le **slash final est obligatoire**), content type `application/json`, événement *push*.

**Option C — polling seul** : `pollSCM('H/5 * * * *')` suffit à déclencher un build
dans les 5 min suivant un push, sans aucune exposition. Si le plugin `github` n'est
pas installé, retirer `githubPush()` (sinon la syntaxe du pipeline n'est pas reconnue).

**Plugin** : `github` (ajouté à `jenkins/plugins.txt`) — l'installer puis redémarrer
Jenkins **avant** de relancer le job.

> Important : le CD n'est déclenché que depuis la branche **`main`** (stage `Trigger CD`).
> Un push sur une autre branche lance la CI (tests/Sonar) mais pas le déploiement.

## 5. Les pipelines (CI `Jenkinsfile` · CD `Jenkinsfile.cd`)

La CI et le CD sont **séparés** : la CI ne déploie jamais, le CD ne compile ni ne teste.
La CI déclenche le CD en fin de build réussi (branche `main` uniquement) via
`build job: 'chillnet-cd'`, en lui transmettant le tag d'image.

### CI (`Jenkinsfile` → job `ChillNet`)

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
| Checkout | clone (pour les manifestes `k8s/base` et `k8s/monitoring`) |
| Deploy to k3s | `kubectl apply -k k8s/base`, `set image` sur le tag reçu, Job de migration (re-taggé via `sed`), `rollout status` |
| Deploy monitoring | `kubectl apply -k k8s/monitoring` + `rollout status` Prometheus / Grafana (namespace `monitoring`) |

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

## 7. Supervision (`k8s/monitoring` — Prometheus + Grafana)

Stack légère déployée par kustomize (**sans Helm ni Operator**), dans le namespace
`monitoring`, indépendante de l'application. Détail complet : [`monitoring.md`](monitoring.md).

```
  navigateur                          ┌─ namespace: monitoring ───────────────────┐
     │  Host: grafana.local           │  Prometheus (:9090, PVC 5Gi) ─┐           │
     │         prometheus.local       │  Grafana    (:3000, PVC 2Gi) ──┼─ datasource│
     ▼                                │      ▲ scrape                 │           │
  ┌──────────┐  :80                   │      ├─ kubelet (node + cAdvisor)          │
  │ Ingress  │────────────────────────┤      ├─ node-exporter   (DaemonSet :9100)   │
  │ Traefik  │                        │      └─ kube-state-metrics    (:8080)      │
  └──────────┘                        └────────────────────────────────────────────┘
```

| Ressource | Détail |
|-----------|--------|
| `prometheus` | Deployment + Service + PVC 5Gi + RBAC kubelet (`nodes/metrics`, `nodes/proxy`) |
| `grafana` | Deployment + Service + PVC 2Gi + provisioning (datasource + dashboard) |
| `node-exporter` | DaemonSet hostNetwork `:9100` (métriques OS du nœud) |
| `kube-state-metrics` | Deployment + Service `:8080` (état des objets Kubernetes) |
| `ingress` | Traefik : `grafana.local` (`:3000`), `prometheus.local` (`:9090`) |

Accès : `http://grafana.local` (login `admin` / `GRAFANA_ADMIN_PASSWORD` de
`k8s/monitoring/.env`) et `http://prometheus.local` (`Status → Targets` — **8 cibles UP**).

## 8. Points d'attention connus

- **SonarQube RAM** : la VM a ~5 Go ; surveiller `free -m` si l'analyse ralentit.
- **`vm.max_map_count=262144`** : requis par l'Elasticsearch de SonarQube (posé par le
  provisionnement de la VM sonarqube).
- **Artefacts IA** : l'image `ai-service` embarque `model.joblib` et les CSV ; aucun
  volume n'est monté dessus (un volume vide masquerait les artefacts et forcerait le
  repli sur les règles). La persistance des ré-entraînements n'est donc pas assurée.
- **Worker de queue** : `QUEUE_CONNECTION=database` mais aucun worker n'est déployé ;
  les jobs restent en file (sans impact sur l'UI).
- **Pint** : le dépôt n'est pas encore formaté ; le stage de lint est non bloquant.
- **Supervision RAM** : la stack `monitoring` ajoute ~500 Mi de *requests* (~1,9 Gi de
  *limits*) à la VM k8s (6 Go). En cas de tension, réduire la *limit* de Prometheus.
- **Scrape kubelet `:10250`** : nécessite `insecure_skip_verify: true` (certificat auto-signé)
  et le ClusterRole `nodes/metrics` + `nodes/proxy`, sinon les cibles passent en `403`.
- **Cookies d'auth** : le lab est servi en **HTTP** (ingress Traefik « web ») ; le ConfigMap
  force `AUTH_COOKIE_SECURE=false` / `SESSION_SECURE_COOKIE=false` pour que le navigateur
  renvoie bien les cookies (sinon boucle de connexion sur `/login`). Passer à `true` derrière TLS.
