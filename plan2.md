J'ai tout ce qu'il me faut. Je produis maintenant les 5 artefacts.                                              
                                                          
  ---                                                                                                             
  Artefact 1 — Chain of Thought (CoT)                                                                             
                                                                                                                  
  Hypothèse de départ : "la rotation n'est pas implémentée" — à vérifier.                                         
                                                                                                                  
  Étape 1 — Vérification de la rotation                                                                           
  RefreshTokenGrant::respondToAccessTokenRequest() ligne 76-78 : $this->revokeRefreshTokens = true par défaut dans
   AbstractGrant. Conclusion : la rotation est déjà fonctionnelle ; l'ancien token est révoqué à chaque usage. Ce 
  n'est pas un bug d'implémentation, c'est un bug de configuration (TTL hardcodé) et un bug de sécurité
  silencieux.                                                                                                     
                  
  Étape 2 — Bug silencieux dans isRefreshTokenRevoked                                                             
  Ligne 47-53 de RefreshTokenRepository.php : si le token n'est pas en DB → retourne false (= non révoqué). Si on
  ajoute un jour un job de cleanup qui purge les tokens revoked=true, un token recyclé (replay après purge) serait
   traité comme valide. La sécurité correcte est : not-found = révoqué. Ce fix est inclus dans SEC001.
                                                                                                                  
  Étape 3 — TTL configurable                                                                                      
  OAuthServer.php ligne 54-61 : new DateInterval('P30D') et new DateInterval('PT1H') codés en dur. Assumption :
  DateInterval accepte la notation ISO 8601 (PT1H, P30D). getenv() retourne false si absent → ?: 'P30D' est safe. 
  Pas besoin d'une classe Config dédiée.
                                                                                                                  
  Étape 4 — Rate limiting
  CI4 Throttler utilise CacheInterface. Cache handler actuel : file. En test, si on écrit dans le cache, les
  compteurs persistent entre tests et peuvent bloquer des requêtes — effet de bord. Solution : override $handler =
   'dummy' dans Config/Boot/testing.php. Le DummyHandler ne persiste rien → check() retourne toujours true.
                                                                                                                  
  Étape 5 — Clé de throttle                                                                                       
  Throttler par IP seule est contournable via rotation de proxy. Throttler par client_id seul est contournable si
  on contrôle plusieurs IPs. Meilleure approche : clé composite IP + client_id (ou IP seul par défaut si pas de   
  client_id dans la requête). Récupérer client_id du corps form-encoded dans le filter.
                                                                                                                  
  Étape 6 — Tests 
  Feature tests existants testent le happy path. Besoin d'ajouter :
  - SEC001 : test que l'ancien refresh token est rejeté après rotation                                            
  - SEC002 : test que le 6e appel en 1 minute retourne 429 (avec un vrai cache non-dummy)                         
                                                                                                                  
  ---                                                                                                             
  Artefact 2 — Tree of Thoughts (ToT)
                                                                                                                  
  SEC001 — 3 approches TTL + rotation
                                                                                                                  
  ┌───────────────┬────────────────────────────────────────┬──────────────────────────┬───────────────────────┐   
  │               │   Approche A : getenv() direct dans    │      Approche B :        │     Approche C :      │   
  │               │              OAuthServer               │   Config/OAuth.php CI4   │ Par-client TTL en DB  │   
  ├───────────────┼────────────────────────────────────────┼──────────────────────────┼───────────────────────┤
  │ Avantages     │ Minimal, déjà le pattern du projet     │ Idiomatic CI4,           │ Flexible par client   │
  │               │ (OAUTH_ENCRYPTION_KEY)                 │ DI-friendly              │                       │
  ├───────────────┼────────────────────────────────────────┼──────────────────────────┼───────────────────────┤   
  │ Inconvénients │ Validation manuelle du format ISO 8601 │ Nouveau fichier,         │ Schéma DB, surcoût    │
  │               │                                        │ indirection              │                       │   
  ├───────────────┼────────────────────────────────────────┼──────────────────────────┼───────────────────────┤
  │ Risque        │ DateInterval throw si format invalide  │ Aucun                    │ Complexité YAGNI      │   
  └───────────────┴────────────────────────────────────────┴──────────────────────────┴───────────────────────┘   
   
  Choix : Approche A. Cohérent avec OAUTH_ENCRYPTION_KEY existant. Validation via try/catch DateInterval dans     
  OAuthServer.    
                                                                                                                  
  ---             
  SEC002 — 3 approches rate limiting
                                                                                                                  
  ┌───────────────┬─────────────────────────────────────┬──────────────────────────────┬──────────────────────┐
  │               │ Approche A : Filter CI4 + Throttler │  Approche B : Logique dans   │    Approche C :      │   
  │               │                natif                │        AuthController        │  Middleware PSR-15   │
  ├───────────────┼─────────────────────────────────────┼──────────────────────────────┼──────────────────────┤   
  │ Avantages     │ Zero dépendance, CI4 idiomatique,   │ Moins de fichiers            │ Portable             │
  │               │ séparation des concerns             │                              │                      │
  ├───────────────┼─────────────────────────────────────┼──────────────────────────────┼──────────────────────┤   
  │ Inconvénients │ Nouveau fichier Filter              │ Mélange concern,             │ Couche PSR dans CI4  │
  │               │                                     │ non-réutilisable             │ = overhead           │   
  ├───────────────┼─────────────────────────────────────┼──────────────────────────────┼──────────────────────┤
  │ Risque        │ Cache file peu scalable (mais       │ Duplication si revoke aussi  │ N/A                  │   
  │               │ suffisant single-server)            │                              │                      │   
  └───────────────┴─────────────────────────────────────┴──────────────────────────────┴──────────────────────┘
                                                                                                                  
  Choix : Approche A. Filters sont précisément le mécanisme CI4 pour les cross-cutting concerns.                  
   
  ---                                                                                                             
  Artefact 3 — Chain of Decisions (CoD)
                                                                                                                  
  - D1 : Ne pas créer Config/OAuth.php → le projet utilise getenv() directement (pattern OAUTH_ENCRYPTION_KEY) ;
  une Config class serait une abstraction non-demandée (YAGNI).                                                   
  - D2 : isRefreshTokenRevoked : not-found → true → traiter un token absent de la DB comme révoqué est
  défensivement correct ; un token valide est toujours en DB après persistNewRefreshToken.                        
  - D3 : Clé throttle = sha1(IP + client_id) → contourne les proxies IP-seul, contourne les attaques multi-IP
  client_id-seul. Fallback sur IP seul si client_id absent.                                                       
  - D4 : Override cache en dummy pour les tests → dans Config/Boot/testing.php, pas dans le filter lui-même, pour
  ne pas polluer la logique métier avec du code de test.                                                          
  - D5 : Pas de lockout, uniquement reject 429 → un lockout persistant en DB est complexe et hors scope ; le token
   bucket du Throttler CI4 est suffisant.                                                                         
  - D6 : Limites configurables via env (OAUTH_RATE_LIMIT_CAPACITY, OAUTH_RATE_LIMIT_SECONDS) → évite de redéployer
   pour changer les seuils.                                                                                       
  - D7 : Appliquer le filter via Routes.php (inline) → plus lisible que $filters dans Filters.php pour des routes
  ciblées.                                                                                                        
  - D8 : Pas de migration DB pour SEC002 → le Throttler CI4 utilise le cache, pas la DB.
                                                                                                                  
  ---             
  Artefact 4 — YAGNI Filter                                                                                       
                                                                                                                  
  ┌───────────────────────────────────────────────┬───────────────────────────────────────────────────────────┐
  │                Élément tentant                │         Raison de ne pas l'implémenter maintenant         │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤
  │ Config/OAuth.php avec validation complète     │ getenv() suffit, pas de multi-tenancy                     │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤
  │ TTL par client en DB                          │ Aucun use case multi-client différencié prévu             │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤   
  │ Lockout persistant (DB blacklist IP)          │ Token bucket suffit pour brute-force ; lockout = support  │   
  │                                               │ nightmare                                                 │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤
  │ Retry-After header dans le 429                │ RFC 6585 nice-to-have, pas un bloquant sécurité           │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤
  │ Monitoring/alerting des rate limit hits       │ Infrastructure concern, hors scope feature                │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤
  │ Redis pour le cache Throttler                 │ File handler suffit en single-server ; Redis = nouvelle   │   
  │                                               │ dépendance                                                │
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤   
  │ setRevokeRefreshTokens(false) toggle en       │ La rotation est la bonne pratique, ne pas la rendre       │
  │ config                                        │ optionnel                                                 │   
  ├───────────────────────────────────────────────┼───────────────────────────────────────────────────────────┤   
  │ Refresh token family tracking (détection de   │ SEC003+ ; ajoute un champ DB et logique complexe          │
  │ vol)                                          │                                                           │   
  └───────────────────────────────────────────────┴───────────────────────────────────────────────────────────┘
                                                                                                                  
  ---             
  Artefact 5 — Pattern Mapping
                              
  ┌─────────────────────────┬─────────────────────────────────┬───────────────────────────────────────────────┐
  │   Problème identifié    │       Pattern recommandé        │                   Skill CI4                   │   
  ├─────────────────────────┼─────────────────────────────────┼───────────────────────────────────────────────┤
  │ TTL hardcodés dans      │ Environment-driven config       │ OAuthServer.php modifié                       │   
  │ OAuthServer             │ (getenv + fallback)             │                                               │
  ├─────────────────────────┼─────────────────────────────────┼───────────────────────────────────────────────┤   
  │ not-found = not-revoked │ Fail-closed (defensively deny   │ RefreshTokenRepository::isRefreshTokenRevoked │   
  │  (sécurité)             │ unknown)                        │                                               │   
  ├─────────────────────────┼─────────────────────────────────┼───────────────────────────────────────────────┤   
  │ Brute-force sur         │ Token Bucket Rate Limiting      │ CI4 Throttler via Services::throttler()       │
  │ /auth/token             │                                 │                                               │   
  ├─────────────────────────┼─────────────────────────────────┼───────────────────────────────────────────────┤
  │ Filter cross-cutting    │ Before-Filter CI4               │ App\Filters\OAuthRateLimitFilter              │   
  │ concern                 │                                 │                                               │   
  ├─────────────────────────┼─────────────────────────────────┼───────────────────────────────────────────────┤
  │ Tests polluant le cache │ Test Isolation via Null Object  │ DummyHandler en CI_ENVIRONMENT=testing        │   
  ├─────────────────────────┼─────────────────────────────────┼───────────────────────────────────────────────┤   
  │ Clé throttle            │ Composite Key Hashing           │ sha1($ip . $clientId)                         │
  │ contournable            │                                 │                                               │   
  └─────────────────────────┴─────────────────────────────────┴───────────────────────────────────────────────┘
                                                                                                                  
  ---             
  Plan d'implémentation — commits atomiques ordonnés par dépendance
                                                                                                                  
  SEC001-a  fix(oauth): make access and refresh token TTLs configurable via env
            └── Fichier : app/Libraries/OAuthServer.php                                                           
            └── Vars : OAUTH_ACCESS_TOKEN_TTL (défaut PT1H), OAUTH_REFRESH_TOKEN_TTL (défaut P30D)                
            └── Validation : try/catch DateInterval constructor, fallback sur défaut + log warning                
                                                                                                                  
  SEC001-b  fix(oauth): treat missing refresh token as revoked (fail-closed)                                      
            └── Fichier : app/OAuth2/Repositories/RefreshTokenRepository.php                                      
            └── Changement : ligne 48, `if (!$token) return true;`                                                
                                                                                                                  
  SEC001-c  test(oauth): add rotation test - used refresh token must be rejected                                  
            └── Fichier : backend/tests/Feature/AuthenticationTest.php                                            
            └── Nouveau test : login → refresh → réutiliser l'ancien RT → expect 400                              
                                                                                                                  
  SEC002-a  feat(security): add OAuthRateLimitFilter using CI4 native Throttler
            └── Nouveau fichier : app/Filters/OAuthRateLimitFilter.php                                            
            └── Clé : sha1(IP + client_id), capacity/seconds depuis env                                           
            └── Retourne 429 avec JSON error si throttled                                                         
                                                                                                                  
  SEC002-b  feat(security): register OAuthRateLimitFilter alias and apply to OAuth routes                         
            └── Fichier : app/Config/Filters.php → alias 'oauth_rate_limit'
            └── Fichier : app/Config/Routes.php → filter sur auth/token + auth/revoke                             
                                                                                                                  
  SEC002-c  fix(test): use dummy cache handler in testing env to disable throttler                                
            └── Fichier : app/Config/Boot/testing.php                                                             
            └── `\Config\Cache::$handler = 'dummy'` (override class property)                                     
                                                                                                                  
  SEC002-d  test(security): add rate limit Feature test (needs real cache, tagged @group rate)                    
            └── Fichier : backend/tests/Feature/RateLimitTest.php                                                 
            └── 5 appels valides → 6e → assertStatus 429                                                          
            └── Annoté @group rate pour exclusion du suite normale (ne tourne pas en CI standard)                 
                                                                                                                  
  Ordre de dépendance :                                                                                           
  SEC001-a → SEC001-b → SEC001-c                                                                                  
  SEC002-a → SEC002-b → SEC002-c → SEC002-d
  (les deux branches sont indépendantes)                                                                          
                                        
  Variables d'env à documenter (OAuthSetupCommand + README) :                                                     
  OAUTH_ACCESS_TOKEN_TTL=PT1H        # ISO 8601 duration                                                          
  OAUTH_REFRESH_TOKEN_TTL=P30D       # ISO 8601 duration                                                          
  OAUTH_RATE_LIMIT_CAPACITY=10       # max requests in window                                                     
  OAUTH_RATE_LIMIT_SECONDS=60        # window size in seconds
                                                                                                                  
  ---             
  ⏳ Attente de validation avant toute implémentation.   