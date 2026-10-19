# ChillNet — Supervision (Prometheus + Grafana)

Stack de supervision **légère** du cluster k3s, déployée par **kustomize**
(**sans Helm ni Prometheus-Operator**). Portée : **infra & cluster** (nœud, pods,
ressources Kubernetes) — pas d'instrumentation applicative.

- Namespace : **`monitoring`**
- Manifestes : `k8s/monitoring/`
- Accès : `http://grafana.local` et `http://prometheus.local` (Ingress Traefik)

## 1. Architecture

```
                     ┌─ namespace: monitoring ───────────────────────────────────┐
  navigateur         │                                                          │
     │  Host:        │   ┌──────────────┐        ┌───────────────────────────┐   │
     │  grafana.local│   │   Grafana    │ ─────▶ │        Prometheus         │   │
     │  prometheus…  │   │  :3000       │ datasrc│  :9090  (PVC 5Gi, 15j)    │   │
     ▼               │   │  (PVC 2Gi)   │        │                           │   │
  ┌──────────┐  :80   │   └──────────────┘        └────────────┬──────────────┘   │
  │ Ingress  │────────┤                                        │ scrape 30s       │
  │ Traefik  │        │        ┌───────────────────────────────┴───────────────┐  │
  └──────────┘        │        │  kubelet :10250  /metrics  +  /metrics/cadvisor│  │
                      │        │  node-exporter   :9100   (DaemonSet hostNet)  │  │
                      │        │  kube-state-metrics :8080 (état des objets k8s)│  │
                      │        └───────────────────────────────────────────────┘  │
                      └──────────────────────────────────────────────────────────┘
```

## 2. Structure des fichiers

```
k8s/monitoring/
├── kustomization.yaml          # namespace, secretGenerator(.env), configMapGenerator(dashboard)
├── namespace.yaml
├── .env.example                # GRAFANA_ADMIN_PASSWORD=changeme (modèle versionné)
├── .env                        # (git-ignoré) vrai mot de passe admin Grafana
├── ingress.yaml                # grafana.local -> grafana:3000 ; prometheus.local -> prometheus:9090
├── prometheus/
│   ├── rbac.yaml               # ServiceAccount + ClusterRole(nodes/metrics, nodes/proxy) + binding
│   ├── configmap.yaml          # prometheus.yml (scrape configs)
│   ├── pvc.yaml                # 5Gi local-path
│   ├── service.yaml            # ClusterIP :9090
│   └── deployment.yaml         # prom/prometheus:v2.54.1, retention 15d
├── grafana/
│   ├── pvc.yaml                # 2Gi local-path
│   ├── provisioning-cm.yaml    # ConfigMap : datasource + provider dashboards
│   ├── service.yaml            # ClusterIP :3000
│   ├── deployment.yaml         # grafana/grafana:11.1.4
│   └── dashboard-node.json     # dashboard « Node & Cluster overview »
└── exporters/
    ├── node-exporter.yaml      # DaemonSet hostNetwork :9100 + Service
    └── kube-state-metrics.yaml # SA + ClusterRole + Deployment + Service :8080
```

## 3. Composants

| Composant | Version | Rôle |
|-----------|---------|------|
| `prometheus` | `prom/prometheus:v2.54.1` | collecte + stocke les métriques (scrape 30 s, rétention 15 j) |
| `grafana` | `grafana/grafana:11.1.4` | visualisation (datasource Prometheus provisionnée) |
| `node-exporter` | `prom/node-exporter:v1.8.2` | métriques OS du nœud (CPU, RAM, disque, réseau) |
| `kube-state-metrics` | `.../kube-state-metrics:v2.13.0` | état des objets k8s (Deployments, Pods, Jobs…) |

Cibles scrapées (`prometheus.yml`) : `prometheus`, `kubernetes-apiservers`,
`kubernetes-nodes` (kubelet), `kubernetes-nodes-cadvisor`, `node-exporter`,
`kube-state-metrics`, `kubernetes-pods` (opt-in via annotation
`prometheus.io/scrape: "true"`).

## 4. Déploiement

### Automatique (pipeline)

Le job **`chillnet-cd`** possède un stage **`Deploy monitoring`** qui exécute
`kubectl apply -k k8s/monitoring`, en injectant `k8s/monitoring/.env` depuis le
credential Jenkins (Secret file) **`chillnet-monitoring-env`**.

### Manuel

```bash
# Sur la VM k8s (ou partout avec le kubeconfig) :
cd /vagrant/ChillNet
cp k8s/monitoring/.env.example k8s/monitoring/.env   # si absent, puis renseigner le mot de passe
kubectl apply -k k8s/monitoring
kubectl -n monitoring get pods,svc,ingress
```

## 5. Accès aux interfaces

### A. Par nom de domaine (recommandé)

Ajouter au fichier `hosts` de l'hôte (PowerShell **admin**) :

```powershell
Add-Content C:\Windows\System32\drivers\etc\hosts "192.168.56.12`tgrafana.local"
Add-Content C:\Windows\System32\drivers\etc\hosts "192.168.56.12`tprometheus.local"
```

- **Grafana** → `http://grafana.local` — login `admin` / `GRAFANA_ADMIN_PASSWORD` (dans `k8s/monitoring/.env`).
- **Prometheus** → `http://prometheus.local` — état des cibles : `.../targets`.

### B. Par port-forward (sans toucher au `hosts`)

```bash
vagrant ssh k8s -c "sudo k3s kubectl -n monitoring port-forward --address 0.0.0.0 svc/grafana 3000:3000"
# autre terminal
vagrant ssh k8s -c "sudo k3s kubectl -n monitoring port-forward --address 0.0.0.0 svc/prometheus 9090:9090"
```

Puis `http://192.168.56.12:3000` (Grafana) et `http://192.168.56.12:9090` (Prometheus).

## 6. Vérification

```bash
# Pods tous Running/Ready ?
kubectl -n monitoring get pods

# Nombre de cibles UP (attendu : 8)
kubectl -n monitoring exec deploy/prometheus -- \
  wget -qO- 'http://127.0.0.1:9090/api/v1/targets?state=active' | grep -o '"health":"[a-z]*"' | sort | uniq -c

# Réponse de l'Ingress (depuis l'hôte) :
curl.exe -s -o NUL -w "%{http_code}" -H "Host: grafana.local" http://192.168.56.12/api/health
```

Dans **Grafana** : menu ☰ → *Dashboards* → « **ChillNet — Node & Cluster overview** ».

### Requêtes PromQL utiles (onglet *Graph* de Prometheus)

| Requête | Ce que ça montre |
|---|---|
| `up` | état des cibles (`1` = up, `0` = down) |
| `100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)` | % CPU du nœud |
| `node_memory_MemAvailable_bytes` | RAM disponible |
| `count(kube_pod_status_phase{namespace="chillnet",phase="Running"} == 1)` | pods `chillnet` Running |
| `100 - ((node_filesystem_avail_bytes{mountpoint="/"} / node_filesystem_size_bytes{mountpoint="/"}) * 100)` | % disque `/` |

## 7. Budget RAM (VM k8s = 6 Go)

| Composant | requests | limits |
|---|---|---|
| Prometheus | 256Mi | 1Gi |
| Grafana | 128Mi | 512Mi |
| node-exporter (×1) | 50Mi | 128Mi |
| kube-state-metrics | 64Mi | 256Mi |
| **Total ajouté** | **≈498Mi** | **≈1,9Gi** |

Si tension mémoire : réduire la *limit* de Prometheus (ex. 768Mi).

## 8. Dépannage

| Symptôme | Cause probable | Correction |
|---|---|---|
| Cible kubelet `403 Forbidden` | ClusterRole incomplet | ajouter `nodes/metrics` + `nodes/proxy` dans `prometheus/rbac.yaml` |
| `connection refused` sur `:10250` | certificat kubelet auto-signé | `tls_config.insecure_skip_verify: true` (déjà posé) |
| PVC `Pending` | pas de provisionneur | vérifier `local-path` (StorageClass par défaut de k3s) |
| Grafana sans dashboard | ConfigMap mal montée | le fichier → clé `dashboard-node.json`, monté dans `/var/lib/grafana/dashboards` |
| `grafana.local` non résolu | entrée `hosts` manquante | cf. §5.A |
| Login Grafana refusé | mot de passe ≠ `.env` | recréer le secret puis `kubectl -n monitoring rollout restart deploy/grafana` |
