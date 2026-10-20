// Pipeline CI ChillNet — build, tests, analyse SonarQube, build/push des images.
// Le déploiement vit dans un job séparé (`Jenkinsfile.cd` -> job `chillnet-cd`) que
// cette CI déclenche en fin de build réussi, sur `main` uniquement.
pipeline {
  agent any

  options {
    timestamps()
    disableConcurrentBuilds()
    buildDiscarder(logRotator(numToKeepStr: '10'))
    skipDefaultCheckout(true)
  }

  triggers {
    // Le job ne suit que la branche main (Branch Specifier `*/main` dans sa
    // configuration SCM). GitHub ne sait pas filtrer les webhooks par branche :
    // chaque push est relayé, mais githubPush() ne fait qu'un poll SCM du job ;
    // si main n'a pas bougé, aucun build n'est planifié. Concrètement la CI
    // part au merge d'une PR dans main, pas sur les pushes de branches.
    githubPush()
    // Filet de sécurité si le webhook n'est pas joignable : même filtre (*/main),
    // polling toutes les 5 min.
    pollSCM('H/5 * * * *')
  }

  environment {
    REGISTRY    = '192.168.56.12:5000'
    APP_IMAGE   = "${REGISTRY}/chillnet-app"
    AI_IMAGE    = "${REGISTRY}/chillnet-ai-service"
    XDEBUG_MODE = 'coverage'
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
        // Tag d'image = SHA court du commit : immuable et traçable, partagé avec
        // le job CD (les deux jobs ont des numéros de build indépendants).
        script {
          env.IMAGE_TAG = sh(script: 'git rev-parse --short HEAD', returnStdout: true).trim()
          // Le checkout Jenkins est en HEAD détaché : `rev-parse --abbrev-ref HEAD` renvoie
          // « HEAD » et GIT_BRANCH est souvent absent. On déduit donc la branche en comparant
          // le commit courant à la tête de origin/main (fetchee par le checkout).
          def head = sh(script: 'git rev-parse HEAD', returnStdout: true).trim()
          def mainHead = sh(script: 'git rev-parse origin/main 2>/dev/null || true', returnStdout: true).trim()
          env.CI_BRANCH = (mainHead != '' && head == mainHead) ? 'main' : 'autre'
          echo "HEAD ${head} / origin/main ${mainHead} → branche ${env.CI_BRANCH}"
        }
      }
    }

    stage('Install') {
      steps {
        sh 'composer install --no-interaction --prefer-dist --no-progress'
        sh 'npm ci'
        // Les layouts utilisent @vite : le manifest doit exister AVANT les tests.
        sh 'npm run build'
        // Le checkout CI n'a pas de .env (git-ignoré). Sans lui, phpdotenv émet un
        // warning « file_get_contents(.env) » et les tests échouent (APP_KEY et
        // JWT_SECRET manquants -> MissingAppKeyException / SecretMissingException).
        sh '''
          set -eu
          [ -f .env ] || cp .env.example .env
          grep -q '^APP_KEY=base64:' .env || sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
          grep -q '^JWT_SECRET=.' .env || sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$(openssl rand -hex 32)|" .env
        '''
      }
    }

    stage('Lint (Pint)') {
      steps {
        // Le dépôt n'est pas encore 100 % Pint-clean : rapport informatif, non bloquant.
        catchError(buildResult: 'SUCCESS', stageResult: 'UNSTABLE') {
          sh 'vendor/bin/pint --test'
        }
      }
    }

    stage('Test (PHP)') {
      steps {
        sh 'php artisan test --coverage-clover build/coverage/clover.xml --log-junit build/coverage/junit.xml'
      }
    }

    stage('Test (Python)') {
      steps {
        // Même base que l'image de production : le Python 3.10 de l'hôte ne peut
        // pas installer scikit-learn 1.8, version qui charge les .joblib du dépôt.
        // On monte tout le dépôt : les tests de parité lisent tests/fixtures à
        // la racine (parents[2] du fichier de test).
        sh '''
          set -eu
          docker run --rm \
            -u "$(id -u):$(id -g)" \
            -e HOME=/tmp \
            -v "$WORKSPACE:/repo" -w /repo/ai-service \
            python:3.11-slim \
            sh -c '
              set -eu
              rm -rf .venv
              python -m venv .venv
              . .venv/bin/activate
              pip install -q --upgrade pip
              pip install -q -r requirements.txt pytest-cov
              pytest --junitxml=build/pytest.xml --cov=app --cov-report=xml:coverage.xml
            '
        '''
      }
    }

    stage('SonarQube (Laravel)') {
      steps {
        withSonarQubeEnv('sonarqube') {
          // -u Jenkins : le scanner tourne en tant qu'utilisateur Jenkins, mais les dossiers
          // de travail de l'image (/opt/sonar-scanner/.sonar et /tmp/.scannerwork) appartiennent
          // à scanner-cli -> AccessDeniedException. SONAR_USER_HOME déplace le cache dans /tmp
          // et sonar.working.directory place le dossier de travail dans le workspace monté
          // (appartenant à Jenkins, et où waitForQualityGate lit report-task.txt).
          sh '''
            docker run --rm \
              -u "$(id -u):$(id -g)" \
              -e SONAR_HOST_URL -e SONAR_TOKEN="$SONAR_AUTH_TOKEN" \
              -e SONAR_USER_HOME=/tmp/.sonar \
              -v "$WORKSPACE:/usr/src" -w /usr/src \
              sonarsource/sonar-scanner-cli \
              -Dsonar.working.directory=/usr/src/.scannerwork
          '''
        }
      }
    }

    stage('SonarQube (ai-service)') {
      steps {
        withSonarQubeEnv('sonarqube') {
          sh '''
            docker run --rm \
              -u "$(id -u):$(id -g)" \
              -e SONAR_HOST_URL -e SONAR_TOKEN="$SONAR_AUTH_TOKEN" \
              -e SONAR_USER_HOME=/tmp/.sonar \
              -v "$WORKSPACE/ai-service:/usr/src" -w /usr/src \
              sonarsource/sonar-scanner-cli \
              -Dsonar.working.directory=/usr/src/.scannerwork
          '''
          // waitForQualityGate ne lit qu'un seul report-task.txt (celui de
          // Laravel) : le gate Python est donc contrôlé ici via l'API, et son
          // rapport retiré du workspace pour lever l'ambiguïté.
          sh '''
            curl -s -u "$SONAR_AUTH_TOKEN:" \
              "$SONAR_HOST_URL/api/issues/search?componentKeys=chillnet-ai-service&resolved=false&inNewCodePeriod=true&ps=100" \
              -o /tmp/sonar-issues-ai.json || true
            php -r '
              $d = json_decode(@file_get_contents("/tmp/sonar-issues-ai.json"), true);
              printf("SONAR AI NEW ISSUES %d\\n", $d["total"] ?? 0);
              foreach ($d["issues"] ?? [] as $i) {
                  printf("SONAR AI %s %s %s %s:%s %s\\n",
                      $i["severity"], $i["type"], $i["rule"], $i["component"], $i["line"] ?? 0, $i["message"]);
              }
            ' || true
            rm -rf ai-service/.scannerwork
            php -r '
              $url = getenv("SONAR_HOST_URL") . "/api/qualitygates/project_status?projectKey=chillnet-ai-service";
              $ctx = stream_context_create(["http" => ["header" => "Authorization: Basic " . base64_encode(getenv("SONAR_AUTH_TOKEN") . ":"), "timeout" => 20]]);
              $s = "NONE";
              for ($i = 0; $i < 24; $i++) {
                  $g = json_decode(@file_get_contents($url, false, $ctx), true);
                  $s = $g["projectStatus"]["status"] ?? "NONE";
                  if ($s !== "NONE") { break; }
                  sleep(5);
              }
              printf("SONAR AI GATE %s\\n", $s);
              foreach ($g["projectStatus"]["conditions"] ?? [] as $c) {
                  printf("SONAR AI COND %s %s = %s (seuil %s)\\n",
                      $c["status"], $c["metricKey"], $c["actualValue"] ?? "?", $c["errorThreshold"] ?? "-");
              }
              exit($s === "OK" ? 0 : 1);
            '
          '''
        }
      }
    }

    stage('Quality Gate') {
      steps {
        timeout(time: 5, unit: 'MINUTES') {
          waitForQualityGate abortPipeline: true
        }
      }
      post {
        // Diagnostic NON bloquant : une fois l'analyse terminée, imprime les
        // anomalies du « nouveau code » du projet Laravel pour que le log du
        // build dise précisément quoi corriger sans ouvrir l'interface.
        always {
          withSonarQubeEnv('sonarqube') {
            sh '''
              curl -s -u "$SONAR_AUTH_TOKEN:" \
                "$SONAR_HOST_URL/api/qualitygates/project_status?projectKey=chillnet" \
                -o /tmp/sonar-gate.json || true
              curl -s -u "$SONAR_AUTH_TOKEN:" \
                "$SONAR_HOST_URL/api/issues/search?componentKeys=chillnet&resolved=false&inNewCodePeriod=true&ps=100" \
                -o /tmp/sonar-issues.json || true
              php -r '
                $g = json_decode(@file_get_contents("/tmp/sonar-gate.json"), true);
                printf("SONAR GATE %s\\n", $g["projectStatus"]["status"] ?? "INCONNU");
                foreach ($g["projectStatus"]["conditions"] ?? [] as $c) {
                    printf("SONAR COND %s %s = %s (seuil %s)\\n",
                        $c["status"], $c["metricKey"], $c["actualValue"] ?? "?", $c["errorThreshold"] ?? "-");
                }
                $d = json_decode(@file_get_contents("/tmp/sonar-issues.json"), true);
                printf("SONAR NEW ISSUES %d\\n", $d["total"] ?? 0);
                foreach ($d["issues"] ?? [] as $i) {
                    printf("SONAR %s %s %s %s:%s %s\\n",
                        $i["severity"], $i["type"], $i["rule"], $i["component"], $i["line"] ?? 0, $i["message"]);
                }
              ' || true
            '''
          }
        }
      }
    }

    stage('Build images') {
      steps {
        sh 'docker build -t "$APP_IMAGE:$IMAGE_TAG" -t "$APP_IMAGE:latest" .'
        sh 'docker build -t "$AI_IMAGE:$IMAGE_TAG" -t "$AI_IMAGE:latest" ./ai-service'
      }
    }

    stage('Push images') {
      steps {
        sh 'docker push "$APP_IMAGE:$IMAGE_TAG" && docker push "$APP_IMAGE:latest"'
        sh 'docker push "$AI_IMAGE:$IMAGE_TAG" && docker push "$AI_IMAGE:latest"'
      }
    }

    // Le déploiement est volontairement SÉPARÉ (voir « Jenkinsfile.cd » et le job
    // « chillnet-cd ») : la CI se contente de déclencher ce job en lui transmettant
    // le tag d'image, et uniquement depuis la branche `main`.
    stage('Trigger CD') {
      when { expression { env.CI_BRANCH == 'main' } }
      steps {
        build job: 'chillnet-cd', wait: false,
              parameters: [string(name: 'IMAGE_TAG', value: env.IMAGE_TAG)]
      }
    }
  }

  post {
    always {
      junit allowEmptyResults: true, testResults: 'build/**/*.xml, ai-service/build/**/*.xml'
      archiveArtifacts artifacts: 'build/coverage/clover.xml, ai-service/coverage.xml', allowEmptyArchive: true
      // Le builder est en mode « legacy » (buildx absent) : son cache de construction
      // est stocké sous forme d'images <none> (dangling). Un `docker image prune -f`
      // SANS filtre supprimerait donc tout le cache à chaque build, forçant à
      // re-télécharger Composer/npm/apt (lent et vulnérable au moindre timeout DNS).
      // On ne purge que les images de plus de 7 jours.
      sh 'docker image prune -f --filter "until=168h" >/dev/null 2>&1 || true'
    }
    success { echo 'ChillNet pipeline succeeded.' }
    failure { echo 'ChillNet pipeline failed.' }
  }
}
