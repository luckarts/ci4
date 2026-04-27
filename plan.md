1. Chain of Thought (CoT)                                                                                       
                                                                                                                  
  Hypothèse 1 — tests/unit vs tests/Unit                                                                          
  Le job unit dans test.yml appelle tests/unit (minuscule). Ce répertoire existe mais est vide de tests réels. Les
   4 vrais tests unitaires sont dans tests/Unit/ (majuscule). phpunit.xml.dist définit déjà la suite unit qui     
  couvre les deux chemins. Correction : passer à --testsuite unit.                                                
                                                                                                                  
  Hypothèse 2 — le job database est vide, pas cassé                                                               
  tests/database/ et tests/session/ existent sur le disque mais ne contiennent que ExampleDatabaseTest.php /
  ExampleSessionTest.php (scaffolding CodeIgniter). Le job tourne et passe — mais ne teste rien. Ce n'est pas un  
  crash, c'est une fausse confiance. Suppression propre.
                                                                                                                  
  Hypothèse 3 — Feature ≠ PHPUnit + DB                                                                            
  tests/Feature/ApiTestCase.php fait des appels curl vers http://localhost:8080. Ce ne sont pas des tests
  DatabaseTestTrait — c'est un vrai niveau E2E : serveur PHP vivant requis. Nécessite php spark serve en tâche de 
  fond avant PHPUnit.
                                                                                                                  
  Hypothèse 4 — RSA keys absentes en CI                                                                           
  writable/oauth_keys/private.key et public.key existent localement mais ne sont pas dans git (le dossier
  writable/ n'est pas tracké). La commande php spark oauth:setup génère les clés RSA + seed le client OAuth2 —    
  elle est idempotente. C'est l'étape manquante critique du job feature.
                                                                                                                  
  Hypothèse 5 — port PostgreSQL
  .env.test et phpunit.xml.dist utilisent le port 5433. Matching exact dans le service GitHub Actions via ports: 
  ["5433:5432"] — aucune variable d'env à surcharger.                                                             
   
  Hypothèse 6 — cascade inter-workflows                                                                           
  workflow_run est fragile (ne marche pas sur les forks, délai de 5-10 min). La cascade unit → integration → 
  feature dans le même workflow via needs: est suffisante et native. quality.yml reste indépendant (lint est      
  orthogonal aux tests).
                                                                                                                  
  ---             
  2. Tree of Thoughts (ToT)
                                                                                                                  
  Approche A — Expand test.yml (tous les niveaux dans un fichier)
                                                                                                                  
  Ajouter les jobs integration et feature directement dans test.yml avec needs: en chaîne.                        
                                                                                                                  
  Avantages : cascade garantie nativement, un seul fichier à maintenir, pas de nouveau concept.                   
  Inconvénients : test.yml devient plus long (~150 lignes).
  Risque : si les feature tests sont lents, tous les PRs attendent. Acceptable à ce stade.                        
                                                                                                                  
  Approche B — feature.yml séparé + workflow_run                                                                  
                                                                                                                  
  test.yml reste pour unit, nouveau feature.yml déclenché par succès de "Tests" via workflow_run.                 
                  
  Avantages : séparation des concerns, triggers différenciables.                                                  
  Inconvénients : workflow_run ne fonctionne pas sur les PRs depuis des forks, délai variable, difficile à
  debugger, complexité artificielle.                                                                              
  Risque : fausse confiance (le workflow feature ne tourne pas si le PR vient d'un fork).
                                                                                                                  
  Approche C — Reusable workflows + ci.yml orchestrateur                                                          
                                                                                                                  
  Convertir quality.yml et test.yml en workflow_call, créer ci.yml qui les orchestre.                             
                  
  Avantages : flexibilité maximale, réutilisabilité.                                                              
  Inconvénients : refactoring lourd des workflows existants qui fonctionnent, surarchitecture pour 3 jobs.
  Risque : régression sur les workflows déjà opérationnels.                                                       
                                                                                                                  
  Choix : Approche A. Minimum de changements, cascade native, pas de nouveaux concepts. Les features tests lents  
  sont un problème futur (YAGNI).                                                                                 
                                                                                                                  
  ---             
  3. Chain of Decisions (CoD)
                             
  - D1: --testsuite unit plutôt que chemin direct → phpunit.xml.dist couvre déjà les deux variantes de casse ;
  évite la fragilité sur les paths                                                                                
  - D2: Supprimer le job database plutôt que le corriger → Il ne teste rien de réel, le garder crée une fausse
  confiance en CI                                                                                                 
  - D3: ports: ["5433:5432"] sur le service postgres → Correspond exactement à .env.test, aucune variable d'env à
  surcharger, zéro diff dans phpunit.xml.dist                                                                     
  - D4: php spark migrate avant PHPUnit dans chaque job DB → Les tests DatabaseTestTrait peuvent migrer eux-mêmes,
   mais une migration explicite est plus lisible et fail-fast                                                     
  - D5: php spark oauth:setup avant le job feature → Génère les clés RSA + seed le client OAuth2, command
  idempotente, critique pour AuthenticationTest.php                                                               
  - D6: php spark serve & + poll curl health check plutôt que sleep 5 → sleep est fragile (trop court ou trop
  long), un poll until curl -sf http://localhost:8080/health boucle proprement                                    
  - D7: Pinner les SHAs dans quality.yml avec les mêmes valeurs que test.yml → Cohérence, réduction du risque
  supply chain, cohérence d'audit                                                                                 
                  
  ---                                                                                                             
  4. YAGNI Filter 
                 
  ┌──────────────────────────────────────┬────────────────────────────────────────────────────────────────────┐
  │              Tentation               │               Raison de ne pas l'ajouter maintenant                │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤
  │ Matrice PHP 8.1/8.2/8.3              │ Le projet cible 8.3, diversification = coût sans bénéfice actuel   │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤
  │ Code coverage + rapport Clover       │ Nécessite Xdebug, alourdit les jobs, aucun consumer du rapport     │   
  │                                      │ n'est configuré                                                    │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤   
  │ workflow_run orchestrateur           │ needs: dans le même workflow suffit, workflow_run est fragile sur  │   
  │ cross-workflow                       │ les forks                                                          │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤
  │ Artefacts PHPUnit (testdox HTML)     │ phpunit.xml.dist le configure pour les runs locaux, inutile en CI  │   
  │                                      │ sans storage                                                       │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤
  │ ci.yml orchestrateur reusable        │ Sur-architecture pour 4 jobs, refactoring de l'existant sans gain  │   
  │                                      │ mesurable                                                          │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤
  │ Cache vendor/ dans les jobs DB       │ Les jobs DB sont rares et lents à cause de postgres, pas du        │   
  │                                      │ composer install                                                   │   
  ├──────────────────────────────────────┼────────────────────────────────────────────────────────────────────┤
  │ Separate feature.yml file            │ Un seul fichier test.yml avec needs: suffit, séparation =          │   
  │                                      │ complexité gratuite                                                │   
  └──────────────────────────────────────┴────────────────────────────────────────────────────────────────────┘
                                                                                                                  
  ---             
  5. Pattern Mapping
                                                                                                                  
  ┌───────────────────────────────────────┬──────────────────────────────┬────────────────────────────────────┐
  │          Problème identifié           │      Pattern recommandé      │               Notes                │   
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤
  │ Actions non pinnées dans quality.yml  │ Dependency pinning (supply   │ Reprendre les mêmes SHAs que       │
  │                                       │ chain)                       │ test.yml                           │
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤   
  │ Job database avec 0 vrai test         │ Dead code elimination        │ Supprimer, pas refactorer          │
  │                                       │ (Karpathy)                   │                                    │   
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤
  │ PostgreSQL en CI                      │ GitHub Actions service       │ postgres:16 + options:             │   
  │                                       │ containers                   │ --health-cmd pg_isready            │   
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤
  │ Feature tests besoin d'un serveur     │ Background process +         │ spark serve & + until curl -sf     │   
  │ live                                  │ readiness poll               │                                    │   
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤
  │ Clés RSA absentes en CI               │ Idempotent setup command     │ php spark oauth:setup avant        │   
  │                                       │                              │ PHPUnit                            │   
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤
  │ Cascade lint→unit→integration→feature │ Job dependency graph dans un │ needs: natif GitHub Actions        │   
  │                                       │  workflow                    │                                    │
  ├───────────────────────────────────────┼──────────────────────────────┼────────────────────────────────────┤   
  │ Suites déjà définies dans             │ Configuration reuse          │ --testsuite <name> au lieu des     │   
  │ phpunit.xml.dist                      │                              │ paths                              │
  └───────────────────────────────────────┴──────────────────────────────┴────────────────────────────────────┘   
                  
  ---
  Plan d'implémentation — 4 commits atomiques
                                                                                                                  
  Commit 1 — ci: pin action SHAs in quality.yml
                                                                                                                  
  Fichier : .github/workflows/quality.yml                                                                         
  
  Remplacer les 3 refs non pinnées par les mêmes SHAs que test.yml :                                              
  - actions/checkout@v4 → actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5
  - shivammathur/setup-php@v2 → shivammathur/setup-php@44454db4f0199b8b9685a5d763dc37cbf79108e1                   
  - actions/cache@v4 (×2) → actions/cache@0057852bfaa89a56745cba8c7296529d2fc39830             
                                                                                                                  
  Ajouter permissions: {} au niveau workflow et permissions: contents: read sur le job.                           
                                                                                                                  
  Aucune dépendance. Peut merger seul.                                                                            
                                                                                                                  
  ---                                                                                                             
  Commit 2 — ci: repair unit job and remove obsolete database job
                                                                                                                  
  Fichier : .github/workflows/test.yml
                                                                                                                  
  Job unit : tests/unit → --testsuite unit (couvre tests/unit + tests/Unit via phpunit.xml.dist)                  
  
  Job database : supprimé entièrement (seuls ExampleDatabaseTest.php et ExampleSessionTest.php tournent —         
  placeholder vide).
                                                                                                                  
  Job manual (workflow_dispatch) :                                                                                
  - Options : [unit, database] → [unit, integration, feature]
  - Case statement : remplacer database) phpunit tests/database tests/session par integration) phpunit --testsuite
   integration et feature) phpunit --testsuite feature                                                            
                                                                                                                  
  Dépend de rien. Peut merger seul. CI repasse au vert (unit seul).
                                                                                                                  
  ---             
  Commit 3 — ci: add integration job with PostgreSQL service                                                      
                  
  Fichier : .github/workflows/test.yml

  Ajouter le job integration après unit :                                                                         
  
  integration:                                                                                                    
    name: Integration Tests (PostgreSQL)
    runs-on: ubuntu-latest
    needs: unit
    permissions:
      contents: read                                                                                              
    if: github.event_name == 'pull_request'
    defaults:                                                                                                     
      run:        
        working-directory: backend

    services:                                                                                                     
      postgres:
        image: postgres:16                                                                                        
        env:      
          POSTGRES_DB: ci4_oauth_test
          POSTGRES_USER: ci4
          POSTGRES_PASSWORD: password
        ports:
          - 5433:5432
        options: >-
          --health-cmd pg_isready                                                                                 
          --health-interval 10s
          --health-timeout 5s                                                                                     
          --health-retries 5

    steps:
      - uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5  # v4
                                                                                                                  
      - uses: shivammathur/setup-php@44454db4f0199b8b9685a5d763dc37cbf79108e1  # v2                               
        with:                                                                                                     
          php-version: '8.3'                                                                                      
          coverage: none
          extensions: pdo_pgsql
                                                                                                                  
      - name: Cache Composer
        uses: actions/cache@0057852bfaa89a56745cba8c7296529d2fc39830  # v4                                        
        with:     
          path: ~/.composer/cache
          key: composer-${{ hashFiles('backend/composer.lock') }}                                                 
          restore-keys: composer-
                                                                                                                  
      - run: composer install --no-interaction --prefer-dist --optimize-autoloader                                
  
      - name: Run migrations                                                                                      
        env:      
          CI_ENVIRONMENT: testing
        run: php spark migrate --all                                                                              
  
      - name: Run integration tests                                                                               
        run: php vendor/bin/phpunit --testsuite integration --no-coverage
                                                                                                                  
  Dépend du commit 2 (job unit doit exister pour le needs:).                                                      
                                                                                                                  
  ---                                                                                                             
  Commit 4 — ci: add feature job with live server and OAuth2 setup
                                                                                                                  
  Fichier : .github/workflows/test.yml
                                                                                                                  
  Ajouter le job feature après integration :

  feature:
    name: Feature Tests (E2E — live server)
    runs-on: ubuntu-latest                                                                                        
    needs: integration
    permissions:                                                                                                  
      contents: read
    if: github.event_name == 'pull_request'
    defaults:
      run:
        working-directory: backend                                                                                
  
    services:                                                                                                     
      postgres:   
        image: postgres:16
        env:
          POSTGRES_DB: ci4_oauth_test
          POSTGRES_USER: ci4
          POSTGRES_PASSWORD: password
        ports:
          - 5433:5432                                                                                             
        options: >-
          --health-cmd pg_isready                                                                                 
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5  # v4
                                                                                                                  
      - uses: shivammathur/setup-php@44454db4f0199b8b9685a5d763dc37cbf79108e1  # v2                               
        with:                                                                                                     
          php-version: '8.3'                                                                                      
          coverage: none
          extensions: pdo_pgsql, curl

      - name: Cache Composer
        uses: actions/cache@0057852bfaa89a56745cba8c7296529d2fc39830  # v4
        with:                                                                                                     
          path: ~/.composer/cache
          key: composer-${{ hashFiles('backend/composer.lock') }}                                                 
          restore-keys: composer-

      - run: composer install --no-interaction --prefer-dist --optimize-autoloader                                
  
      - name: Configure environment                                                                               
        run: cp .env.test .env

      - name: Run migrations
        run: php spark migrate --all
                                                                                                                  
      - name: Setup OAuth2 (RSA keys + client seed)
        run: php spark oauth:setup                                                                                
                                                                                                                  
      - name: Start PHP server
        run: php spark serve --host 0.0.0.0 --port 8080 &                                                         
                                                                                                                  
      - name: Wait for server ready
        run: |                                                                                                    
          until curl -sf http://localhost:8080 > /dev/null 2>&1; do
            sleep 1
          done

      - name: Run feature tests
        run: php vendor/bin/phpunit --testsuite feature --no-coverage
                                                                                                                  
  Dépend du commit 3 (job integration doit exister pour le needs:).
                                                                                                                  
  ---             
  Résultat final — état des workflows après les 4 commits
                                                                                                                  
  quality.yml     lint (PHPStan)           push + PR   [indépendant, SHAs pinnées]
  test.yml        unit                     PR          [SQLite, --testsuite unit]                                 
                    └─ integration         PR          [PostgreSQL 5433, needs: unit]                             
                         └─ feature        PR          [PostgreSQL + spark serve, needs: integration]             
  audit.yml       CVE audit                weekly      [inchangé]                                                 
  deploy.yml      deploy                   manual      [inchangé]                                                 
                                                                                                                  
  ---                                                                                                             
  Attente de validation avant toute implémentation.