pipeline {
  agent any

  options {
    timestamps()
    disableConcurrentBuilds()
    buildDiscarder(logRotator(numToKeepStr: '10'))
    skipDefaultCheckout(true)
  }

  environment {
    REGISTRY    = '192.168.56.12:5000'
    IMAGE_TAG   = "${env.BUILD_NUMBER}"
    APP_IMAGE   = "${REGISTRY}/chillnet-app"
    AI_IMAGE    = "${REGISTRY}/chillnet-ai-service"
    XDEBUG_MODE = 'coverage'
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
        sh 'git rev-parse --short HEAD'
      }
    }

    stage('Install') {
      steps {
        sh 'composer install --no-interaction --prefer-dist --no-progress'
        sh 'npm ci'
        // Les layouts utilisent @vite : le manifest doit exister AVANT les tests.
        sh 'npm run build'
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
        dir('ai-service') {
          sh '''
            set -eu
            python3 -m venv .venv
            . .venv/bin/activate
            pip install -q --upgrade pip
            pip install -q -r requirements.txt pytest-cov
            pytest --junitxml=build/pytest.xml --cov=app --cov-report=xml:coverage.xml
          '''
        }
      }
    }

    stage('SonarQube (Laravel)') {
      steps {
        withSonarQubeEnv('sonarqube') {
          sh 'docker run --rm -u "$(id -u):$(id -g)" -e SONAR_HOST_URL -e SONAR_TOKEN="$SONAR_AUTH_TOKEN" -v "$WORKSPACE:/usr/src" -w /usr/src sonarsource/sonar-scanner-cli'
        }
      }
    }

    stage('SonarQube (ai-service)') {
      steps {
        withSonarQubeEnv('sonarqube') {
          sh 'docker run --rm -u "$(id -u):$(id -g)" -e SONAR_HOST_URL -e SONAR_TOKEN="$SONAR_AUTH_TOKEN" -v "$WORKSPACE/ai-service:/usr/src" -w /usr/src sonarsource/sonar-scanner-cli'
        }
      }
    }

    stage('Quality Gate') {
      steps {
        timeout(time: 5, unit: 'MINUTES') {
          waitForQualityGate abortPipeline: true
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

    stage('Deploy to k3s') {
      steps {
        // Credential « Secret file » (id: chillnet-k8s-env) = contenu de k8s/base/.env.
        withCredentials([file(credentialsId: 'chillnet-k8s-env', variable: 'K8S_ENV_FILE')]) {
          sh 'cp "$K8S_ENV_FILE" k8s/base/.env'
          sh 'kubectl apply -k k8s/base'
          sh 'kubectl -n chillnet set image deployment/chillnet-app app="$APP_IMAGE:$IMAGE_TAG"'
          sh 'kubectl -n chillnet set image deployment/ai-service ai-service="$AI_IMAGE:$IMAGE_TAG"'
          // Job immuable : recréé à chaque déploiement (image :latest poussée ci-dessus).
          sh 'kubectl -n chillnet delete job chillnet-migrate --ignore-not-found'
          sh 'kubectl -n chillnet apply -f k8s/base/laravel/laravel-migrate-job.yaml'
          sh 'kubectl -n chillnet wait --for=condition=complete job/chillnet-migrate --timeout=180s'
          sh 'kubectl -n chillnet rollout status deployment/chillnet-app --timeout=180s'
          sh 'kubectl -n chillnet rollout status deployment/ai-service --timeout=180s'
        }
      }
    }
  }

  post {
    always {
      junit allowEmptyResults: true, testResults: 'build/**/*.xml, ai-service/build/**/*.xml'
      archiveArtifacts artifacts: 'build/coverage/clover.xml, ai-service/coverage.xml', allowEmptyArchive: true
      sh 'docker image prune -f >/dev/null 2>&1 || true'
    }
    success { echo 'ChillNet pipeline succeeded.' }
    failure { echo 'ChillNet pipeline failed.' }
  }
}
