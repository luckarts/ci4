# Plan — F001 : User OAuth2 + Auth (CI4 + league/oauth2-server 9.3)

> Stack : CodeIgniter 4 · PHP 8.1+ · PostgreSQL · league/oauth2-server 9.3.0
> Scope : API uniquement — inscription, authentification OAuth2 (password grant), lecture et mise à jour du profil.
> Frontend : Aucun. Endpoints JSON seulement.

---

## Analyse — CoT

### État actuel

Le projet CI4 est initialisé (composer install effectué). Dépendances présentes :
- `league/oauth2-server` 9.3.0
- `lcobucci/jwt` 5.6.0 (transitive — génération JWT RSA)
- `defuse/php-encryption` (transitive — chiffrement des refresh tokens)
- `league/uri` (transitive)

**Manquant** :
- `nyholm/psr7` — implémentation PSR-7 requise (league/oauth2-server consomme `ServerRequestInterface`/`ResponseInterface`)
- `nyholm/psr7-server` — construction du `ServerRequest` depuis les superglobales PHP (`$_SERVER`, `$_POST`)
- Clé RSA (private + public) dans `writable/oauth_keys/`
- Clé Defuse (`OAUTH_ENCRYPTION_KEY`) pour chiffrer les refresh tokens

Fichiers CI4 présents : config en classes (`app/Config/`), routing dans `app/Config/Routes.php`, DB non configurée (driver `postgre` requis).

### Adaptations CI3 → CI4 + API seule

Migration depuis le plan CI3 vers CI4 avec **API seule** (pas de Twig, pas de views) :
- CI3 MVC + routing simple → CI4 Routing en Config/Routes.php + Response JSON systématique
- CI3 `CI_Model` → CI4 `Model` avec namespaces + Repository pattern
- CI3 hooks (`pre_controller`) → CI4 Filters (middleware-like) pour JWT validation
- CI3 config fichiers PHP → CI4 classes config (`app/Config/`)
- CI3 migrations manuelles → CI4 Spark CLI migrations
- CI3 `$this->db->` → CI4 Query Builder (`$this->db->`) similaire mais PSR-4 namespaces
- Tous les controllers retournent JSON (pas de views, pas de Twig)
- Tests avec `CIUnitTestCase` (CI4) au lieu de `CI_TestCase` (CI3)

---

## Alternatives — ToT

| # | Approche | Avantages | Inconvénients | Fit |
|---|----------|-----------|---------------|-----|
| 1 | CI3 MVC classique (controller fat) | Rapide | Non testable, couplage fort | Mauvais |
| 2 | Controller → Service → Repository → Model → DB | Couches séparées, testable, idiomatique CI3 | Légèrement plus de fichiers | **Meilleur** |
| 3 | DDD/Hexagonal complet (ports & adapters) | Très propre | Over-engineered pour CI3, objectif pédagogique perdu | Pire |

**Décision** : Approche 2 — architecture en couches CI3, avec interfaces de repository pour permettre les mocks en tests unitaires.

---

## YAGNI

- Pas de refresh token endpoint (géré par league/oauth2-server, hors scope F001)
- Pas de `client_credentials` grant (seul `password` grant utilisé)
- Pas de reset password
- Pas de rate limiting OAuth2 (SEC003 — roadmap séparée)
- Pas de soft delete (USR001 — Pivot SaaS)
- Pas de pagination (pas d'endpoint collection)
- **Pas de frontend** : API seule, JSON uniquement, pas de views Blade, pas de HTML
- Pas de CSRF protection pour endpoints publics (`/register`, `/token` — stateless)

---

## Structure des fichiers (CI4)

```
app/
  Config/
    Routes.php                  (modifier — routes auth + user API)
    Database.php                (modifier — driver postgre + config)
    Autoload.php                (modifier — namespaces custom)
  Controllers/
    AuthController.php          (POST /auth/register · POST /auth/token)
    UserController.php          (GET /users/{id} · PUT /users/{id}/profile)
  Models/
    UserModel.php               (CI4 Model)
  Services/
    UserRegistrationService.php
    UpdateProfileService.php
  Repositories/
    Contracts/
      UserRepositoryContract.php (interface)
    UserRepository.php
  DTO/
    RegisterUserDTO.php
    UpdateProfileDTO.php
  OAuth2/
    Entities/
      UserEntity.php
      ClientEntity.php
      ScopeEntity.php
      AccessTokenEntity.php
      RefreshTokenEntity.php
    Repositories/
      UserOAuth2Repository.php   (getUserEntityByUserCredentials)
      ClientRepository.php
      AccessTokenRepository.php
      ScopeRepository.php
      RefreshTokenRepository.php
  Libraries/
    OAuthServer.php             (factory AuthorizationServer + ResourceServer)
  Filters/
    AuthFilter.php              (vérifie Bearer JWT via ResourceServer — protège /users/*)
  Database/
    Migrations/
      2024-04-23-000001_CreateUsersTable.php
      2024-04-23-000002_CreateOAuthTables.php

writable/
  oauth_keys/
    private.key                 (gitignore)
    public.key                  (gitignore)

tests/
  unit/
    UserRegistrationServiceTest.php
    RegisterDTOValidationTest.php
  integration/
    UserRepositoryTest.php
  Feature/ (E2E dans CI4)
    AuthenticationTest.php       (register + login)
    UserProfileTest.php          (get + update)

.env.test
docker-compose.test.yml
phpunit.xml
```

---

## Schéma BDD

```sql
-- Table users
CREATE TABLE users (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Table oauth_clients
CREATE TABLE oauth_clients (
    id              VARCHAR(80) PRIMARY KEY,
    secret          VARCHAR(255),
    name            VARCHAR(255) NOT NULL,
    redirect_uris   TEXT,
    is_confidential BOOLEAN NOT NULL DEFAULT FALSE
);

-- Table oauth_access_tokens
CREATE TABLE oauth_access_tokens (
    id          VARCHAR(255) PRIMARY KEY,
    user_id     UUID REFERENCES users(id) ON DELETE CASCADE,
    client_id   VARCHAR(80) NOT NULL REFERENCES oauth_clients(id),
    scopes      TEXT,
    revoked     BOOLEAN NOT NULL DEFAULT FALSE,
    expires_at  TIMESTAMP NOT NULL
);

-- Table oauth_refresh_tokens
CREATE TABLE oauth_refresh_tokens (
    id                VARCHAR(255) PRIMARY KEY,
    access_token_id   VARCHAR(255) NOT NULL REFERENCES oauth_access_tokens(id),
    revoked           BOOLEAN NOT NULL DEFAULT FALSE,
    expires_at        TIMESTAMP NOT NULL
);

-- Table oauth_scopes
CREATE TABLE oauth_scopes (
    id VARCHAR(80) PRIMARY KEY
);
INSERT INTO oauth_scopes (id) VALUES ('profile');
```

---

## Stratégie de tests

| Couche | Outil | Dépendances | Exemples |
|--------|-------|-------------|---------|
| Unit | PHPUnit TestCase | 0 DB, 0 CI3 bootstrap, mocks interfaces | UserRegistrationService, RegisterDTO |
| Integration | PHPUnit TestCase + PDO real | PostgreSQL réelle, CI3 QB | UserRepository::save(), existsByEmail() |
| E2E | PHPUnit + cURL helper (AbstractCiTestCase) | Serveur CI3 + DB | register 201, token 200, profile 200 |

---

## Plan d'exécution

### C1 — CI Foundation (CI4 API)

**But** : pipeline minimal qui passe en CI dès le premier commit.

1. Créer `.github/workflows/quality.yml` — job `lint` : `composer validate`, `php -l` sur tous les fichiers PHP, `phpstan` (level 5+) sur `app/`
2. Créer `.github/workflows/test.yml` — job `sanity` : `composer install --no-interaction`, `vendor/bin/phpunit --testdox --filter=SanityTest`
3. Créer `phpunit.xml` — suites `unit`, `integration`, `feature` (E2E), `all`
4. Créer `tests/SanityTest.php` — `assertTrue(true)` (confirme que PHPUnit fonctionne)
5. Lancer en local : `vendor/bin/phpunit tests/SanityTest.php` → vert

```
commit: ci: add workflow foundation — quality, sanity pipeline (CI4)
```

---

### C2 — CI Pipeline complet (CI4 API)

**But** : le job CI peut démarrer PostgreSQL, déployer l'app et lancer tous les tests.

6. Ajouter dans `test.yml` : job `integration` (service PostgreSQL 15, `make test-integration`), job `feature` (service PostgreSQL + PHP built-in server + `make test-feature`)
7. Créer `docker-compose.test.yml` — service `postgres` (PostgreSQL 15, port 5433, vars d'env)
8. Créer `Makefile` — cibles :
   ```makefile
   test-unit:       vendor/bin/phpunit --testsuite unit
   test-integration: vendor/bin/phpunit --testsuite integration
   test-feature:    vendor/bin/phpunit --testsuite feature
   test:            vendor/bin/phpunit
   serve:           php spark serve --host 0.0.0.0 --port 8080
   ```
9. `.env.test` — vars d'env pour tests (DB test, APP_ENV=test)

```
commit: ci: add E2E, integration pipeline jobs + Makefile + docker-compose.test.yml (CI4)
```

---

### C3 — DB Schema + Configuration PostgreSQL (CI4)

**But** : schéma DB créé et validé, CI4 configuré pour PostgreSQL.

10. Ajouter dans `composer.json` :
    ```json
    "nyholm/psr7": "^1.8",
    "nyholm/psr7-server": "^1.1"
    ```
    Lancer `composer update`
11. Modifier `app/Config/Database.php` — passer `driver` à `postgre`, configurer hostname/database/username/password via `.env`
12. Créer `app/Database/Migrations/2024-04-23-000001_CreateUsersTable.php` — `up()` crée table `users` (UUID PK, email UNIQUE, password, first_name, last_name, created_at, updated_at). `down()` drop table
13. Créer `app/Database/Migrations/2024-04-23-000002_CreateOAuthTables.php` — `up()` crée les 4 tables OAuth2 + seed `oauth_scopes`. `down()` drop dans l'ordre inverse
14. `.env` — vars DB : `database.default.hostname`, `database.default.database`, `database.default.username`, `database.default.password`
15. Lancer `php spark migrate` → vérifier les tables en DB
16. Vérifier : `psql -c "\dt"` → 5 tables présentes

```
commit: feat(db): add PostgreSQL config, users and OAuth2 schema migrations (CI4)
```

---

### C4 — Test Infrastructure (CI4 API)

**But** : helper Feature/E2E disponible avant le premier test.

17. Créer `tests/Feature/ApiTestCase.php` (extends `CIUnitTestCase`) :
    - `setUp()` : charge `.env.test`, démarre PHP built-in server si nécessaire
    - `apiPost(string $uri, array $data, array $headers = [])` — cURL POST JSON, retourne `[statusCode, body]`
    - `apiGet(string $uri, array $headers = [])` — cURL GET, retourne `[statusCode, body]`
    - `apiPut(string $uri, array $data, array $headers = [])` — cURL PUT JSON
    - `assertJsonResponse(int $expectedCode, int $actualCode, array $body)` — assert code + JSON valide
    - `createTestUser(array $override = [])` — appel POST /auth/register, retourne le body
    - `getAccessToken(string $email, string $password)` — appel POST /auth/token, retourne `access_token`
18. Créer `.env.test` — variables DB test : `database.default.database=ci4_oauth_test`, `APP_ENVIRONMENT=testing`, `OAUTH_CLIENT_ID`, `OAUTH_CLIENT_SECRET`

```
commit: test: add ApiTestCase HTTP helper for Feature tests (CI4)
```

---

### C5 — POST /auth/register — Inscription (CI4)

**But** : endpoint d'inscription complet avec pyramid de tests.

**Domain / Service** :

19. Créer `app/Repositories/Contracts/UserRepositoryContract.php` :
    ```php
    namespace App\Repositories\Contracts;
    interface UserRepositoryContract {
        public function findById(string $id): ?array;
        public function findByEmail(string $email): ?array;
        public function existsByEmail(string $email): bool;
        public function save(array $data): string;  // retourne id
    }
    ```
20. Créer `app/Models/UserModel.php` — CI4 Model (extends `Model`), Query Builder, méthodes déléguées
21. Créer `app/Repositories/UserRepository.php` — implémente `UserRepositoryContract`, CI4 Query Builder, `password_hash()` dans le service
22. Créer `app/DTO/RegisterUserDTO.php` — propriétés typées + `validate(): array` : email (filter_var), password (strlen ≥ 8), firstName, lastName
23. Créer `app/Services/UserRegistrationService.php` — injecte `UserRepositoryContract`. `register(RegisterUserDTO): string` — validate → throw, existsByEmail → throw UserAlreadyExistsException, password_hash(), save, retourne uuid
24. Créer `app/Exceptions/UserAlreadyExistsException.php` — exception domaine
25. Créer `app/Controllers/AuthController.php` — thin controller :
    - `register()` — lit JSON input, crée DTO, appelle service, retourne 201 JSON ou erreur (422/409)
26. Modifier `app/Config/Routes.php` : `$routes->post('auth/register', 'AuthController::register');`

**Tests Unit** :

27. Créer `tests/Unit/RegisterDTOValidationTest.php` :
    - `email_invalide_retourne_erreur()`
    - `password_trop_court_retourne_erreur()`
    - `dto_valide_retourne_zero_erreur()`
28. Créer `tests/Unit/UserRegistrationServiceTest.php` — mock `UserRepositoryContract` :
    - `inscription_reussie_appelle_save()`
    - `email_existant_lance_exception()`
29. Lancer `vendor/bin/phpunit --testsuite unit` → vert

**Tests Integration** :

30. Créer `tests/Integration/UserRepositoryTest.php` — PDO réel :
    - `save_persiste_et_existsByEmail_retourne_true()`
    - `findByEmail_retourne_null_si_inconnu()`
31. Lancer `vendor/bin/phpunit --testsuite integration` → vert

**Tests Feature (E2E)** :

32. Créer `tests/Feature/AuthenticationTest.php` (extends `ApiTestCase`) :
    - `register_happy_path_retourne_201()` 
    - `register_email_deja_existant_retourne_409()`
    - `register_email_invalide_retourne_422()`
    - `register_password_trop_court_retourne_422()`
33. Lancer `vendor/bin/phpunit --testsuite feature` → vert

```
commit: feat(auth): add POST /auth/register with unit, integration and feature tests (CI4)
```

---

### C6 — OAuth2 Infrastructure (league/oauth2-server) (CI4)

**But** : AuthorizationServer configuré, RSA keys générées, client OAuth2 seedé.

**Entities** :

34. Créer `app/OAuth2/Entities/UserEntity.php` — implements `UserEntityInterface` : `getIdentifier()` retourne l'UUID
35. Créer `app/OAuth2/Entities/ClientEntity.php` — implements `ClientEntityInterface` + use `ClientTrait`
36. Créer `app/OAuth2/Entities/ScopeEntity.php` — implements `ScopeEntityInterface` + use `ScopeTrait`
37. Créer `app/OAuth2/Entities/AccessTokenEntity.php` — implements `AccessTokenEntityInterface` + use `AccessTokenTrait`
38. Créer `app/OAuth2/Entities/RefreshTokenEntity.php` — implements `RefreshTokenEntityInterface` + use `RefreshTokenTrait`

**Repository OAuth2** :

39. Créer `app/OAuth2/Repositories/ClientRepository.php` — implémente `ClientRepositoryInterface` :
    ```php
    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface;
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool;
    ```
40. Créer `app/OAuth2/Repositories/ScopeRepository.php` — lit `oauth_scopes`, implémente `ScopeRepositoryInterface`
41. Créer `app/OAuth2/Repositories/AccessTokenRepository.php` — persiste/révoque `oauth_access_tokens`, implémente `AccessTokenRepositoryInterface`
42. Créer `app/OAuth2/Repositories/RefreshTokenRepository.php` — persiste/révoque `oauth_refresh_tokens`, implémente `RefreshTokenRepositoryInterface`
43. Créer `app/OAuth2/Repositories/UserOAuth2Repository.php` — implémente league `UserRepositoryInterface` :
    ```php
    public function getUserEntityByUserCredentials(string $username, string $password, ...): ?UserEntityInterface;
    ```

**Factory OAuthServer** :

44. Créer `app/Libraries/OAuthServer.php` — singleton factory :

    ```php
    // Constructeur AuthorizationServer (league/oauth2-server 9.3)
    // Signature : (ClientRepositoryInterface, AccessTokenRepositoryInterface,
    //              ScopeRepositoryInterface, CryptKeyInterface|string $privateKey,
    //              Key|string $encryptionKey, ?ResponseTypeInterface)
    
    $privateKey  = WRITEPATH . '../writable/oauth_keys/private.key';
    $encryptionKey = getenv('OAUTH_ENCRYPTION_KEY');
    
    $db = Database::connect();
    $authServer = new AuthorizationServer(
        new ClientRepository($db),
        new AccessTokenRepository($db),
        new ScopeRepository($db),
        $privateKey,       // chemin fichier OU contenu PEM
        $encryptionKey     // string ASCII-safe Defuse
    );
    
    $passwordGrant = new PasswordGrant(
        new UserOAuth2Repository($db),
        new RefreshTokenRepository($db)
    );
    $passwordGrant->setRefreshTokenTTL(new DateInterval('P30D'));
    $authServer->enableGrantType($passwordGrant, new DateInterval('PT1H'));
    
    $publicKey = WRITEPATH . '../writable/oauth_keys/public.key';
    $resourceServer = new ResourceServer(
        new AccessTokenRepository($db),
        $publicKey
    );
    ```
    
    - BD instanciée via `Database::connect()` (lit `.env`)
    - `getAuthorizationServer(): AuthorizationServer`
    - `getResourceServer(): ResourceServer`

**Clés RSA + Defuse** :

45. Générer les clés RSA :
    ```bash
    mkdir -p writable/oauth_keys
    openssl genrsa -out writable/oauth_keys/private.key 2048
    openssl rsa -in writable/oauth_keys/private.key -pubout -out writable/oauth_keys/public.key
    chmod 600 writable/oauth_keys/private.key
    ```
46. Générer la clé Defuse :
    ```bash
    php -r "require 'vendor/autoload.php'; echo Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString();"
    ```
    Stocker dans `.env` : `OAUTH_ENCRYPTION_KEY=<valeur>`
47. Ajouter dans `.gitignore` :
    ```
    writable/oauth_keys/
    .env
    ```

**Seed client OAuth2** :

48. Créer `app/Commands/OAuthSetupCommand.php` (extends `BaseCommand`) — commande CLI : `php spark oauth:setup` insère le client `app_client` avec secret dans `oauth_clients`

```
commit: feat(auth): add OAuth2 infrastructure — entities, repositories, OAuthServer factory, RSA keys (CI4)
```

---

### C7 — POST /auth/token — Password Grant (CI4)

**But** : endpoint token OAuth2 fonctionnel.

49. Ajouter méthode `token()` dans `app/Controllers/AuthController.php` :
    ```php
    public function token(): ResponseInterface {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory
        );
        $serverRequest = $creator->fromGlobals();
        $serverResponse = $psr17Factory->createResponse();

        try {
            $response = OAuthServer::getInstance()
                ->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            $response = $e->generateHttpResponse($serverResponse);
        } catch (\Exception $e) {
            $response = $serverResponse->withStatus(500)
                ->withBody($psr17Factory->createStream(json_encode(['error' => 'server_error'])));
        }

        return $response;  // CI4 convertit PSR-7 en native Response
    }
    ```
    Client : `POST /auth/token` avec `application/x-www-form-urlencoded`
    Réponse 200 : `{"token_type":"Bearer","expires_in":3600,"access_token":"<JWT>","refresh_token":"<opaque>"}`

50. Modifier `app/Config/Routes.php` : `$routes->post('auth/token', 'AuthController::token');`
51. Créer `tests/Feature/AuthenticationTest.php` (ou adapter) :
    - `login_happy_path_retourne_200_avec_access_token()`
    - `login_credentials_invalides_retourne_401()`
    - `login_client_invalide_retourne_401()`
    
    Helper `ApiTestCase::getAccessToken()` doit envoyer `x-www-form-urlencoded` :
    ```php
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type'    => 'password',
        'username'      => $email,
        'password'      => $password,
        'client_id'     => getenv('OAUTH_CLIENT_ID'),
        'client_secret' => getenv('OAUTH_CLIENT_SECRET'),
        'scope'         => 'profile',
    ]));
    ```

52. Lancer `vendor/bin/phpunit --testsuite feature` → vert

```
commit: feat(auth): add POST /auth/token OAuth2 password grant endpoint (CI4)
```

---

### C8 — GET /users/{id} — Profil (CI4)

**But** : endpoint profil sécurisé par JWT.

53. Créer `app/Filters/AuthFilter.php` — vérifie header `Authorization: Bearer <token>` via `ResourceServer::validateAuthenticatedRequest()`. Si invalide → JSON 401. Stocke `user_id` du token dans request attribute
54. Modifier `app/Config/Routes.php` — enregistrer `AuthFilter` sur routes `/users/*` :
    ```php
    $routes->get('users/(:segment)', 'UserController::show/$1', ['filter' => 'auth']);
    ```
55. Créer `app/Controllers/UserController.php` — méthode `show(string $id)` :
    - Récupère `user_id` depuis `AuthFilter` (attribut request)
    - Vérifie que `user_id == $id` (sinon 403)
    - Appelle `UserRepository::findById($id)`
    - Retourne 200 JSON `{id, email, firstName, lastName, createdAt, updatedAt}`
56. Créer `tests/Feature/UserProfileTest.php` (ou adapter) :
    - `get_profile_retourne_200()`
    - `get_profile_sans_token_retourne_401()`
    - `get_profile_autre_user_retourne_403()`
57. Lancer `vendor/bin/phpunit --testsuite feature` → vert

```
commit: feat(user): add GET /users/{id} profile endpoint with JWT auth filter (CI4)
```

---

### C9 — PUT /users/{id}/profile — Mise à jour profil (CI4)

**But** : endpoint update profil complet.

58. Créer `app/DTO/UpdateProfileDTO.php` — firstName, lastName, `validate()` (non vides, ≤ 100 chars)
59. Créer `app/Services/UpdateProfileService.php` — injecte `UserRepositoryContract`, `update(string $id, UpdateProfileDTO): array` — validate → update DB → retourne user mis à jour
60. Ajouter méthode `update(string $id)` dans `app/Controllers/UserController.php` — guard `user_id == $id`, dispatch DTO, retourne 200 ou 422
61. Modifier `routes.php` : `$routes->put('users/(:segment)/profile', 'UserController::update/$1', ['filter' => 'auth']);`
62. Adapter `tests/Feature/UserProfileTest.php` :
    - `update_profile_happy_path_retourne_200()`
    - `update_profile_sans_token_retourne_401()`
    - `update_profile_autre_user_retourne_403()`
    - `update_profile_champs_vides_retourne_422()`
63. Lancer `vendor/bin/phpunit --testsuite feature` → vert
64. Refactoring final : vérifier que tous les tests Feature utilisent `ApiTestCase::createTestUser()` et `getAccessToken()` — pas de code dupliqué

```
commit: feat(user): add PUT /users/{id}/profile update endpoint (CI4)
```

---

## Résumé des commits (CI4 API)

| # | Commit | Fichiers clés |
|---|--------|---------------|
| C1 | `ci: add workflow foundation — quality, sanity pipeline (CI4)` | `.github/workflows/*.yml`, `phpunit.xml`, `tests/SanityTest.php` |
| C2 | `ci: add E2E, integration pipeline jobs + Makefile + docker-compose.test.yml (CI4)` | `Makefile`, `docker-compose.test.yml` |
| C3 | `feat(db): add PostgreSQL config, users and OAuth2 schema migrations (CI4)` | `app/Config/Database.php`, `app/Database/Migrations/` |
| C4 | `test: add ApiTestCase HTTP helper for Feature tests (CI4)` | `tests/Feature/ApiTestCase.php`, `.env.test` |
| C5 | `feat(auth): add POST /auth/register with unit, integration and feature tests (CI4)` | `app/Repositories/`, `app/Services/`, `app/DTO/`, `app/Controllers/AuthController.php`, `tests/` |
| C6 | `feat(auth): add OAuth2 infrastructure — entities, repositories, OAuthServer factory, RSA keys (CI4)` | `app/OAuth2/`, `app/Libraries/OAuthServer.php`, `app/Commands/OAuthSetupCommand.php` |
| C7 | `feat(auth): add POST /auth/token OAuth2 password grant endpoint (CI4)` | `AuthController::token()`, `tests/Feature/AuthenticationTest.php` |
| C8 | `feat(user): add GET /users/{id} profile endpoint with JWT auth filter (CI4)` | `UserController::show()`, `app/Filters/AuthFilter.php`, `tests/Feature/UserProfileTest.php` |
| C9 | `feat(user): add PUT /users/{id}/profile update endpoint (CI4)` | `UpdateProfileService`, `UserController::update()`, `tests/Feature/` |

## Dépendances à ajouter

```bash
composer require nyholm/psr7 nyholm/psr7-server
```

| Package | Rôle | Pourquoi |
|---------|------|----------|
| `nyholm/psr7` | Implémentation PSR-7 (Request, Response, Stream) | `league/oauth2-server` consomme `ServerRequestInterface` / `ResponseInterface` |
| `nyholm/psr7-server` | `ServerRequestCreator::fromGlobals()` | Construit le `ServerRequest` depuis `$_SERVER`, `$_POST`, `$_COOKIE`, `$_FILES` |

`defuse/php-encryption` et `lcobucci/jwt` sont déjà en transitive — ne pas les ajouter manuellement.

---

## Notes CI4

**API seule** : Aucune vue Blade, aucun HTML, aucun rendu côté serveur. Tous les controllers retournent JSON.

**Namespaces CI4** : `App\Controllers\`, `App\Models\`, `App\Services\`, etc. — namespace `App` par défaut, peut être changé dans `app/Config/Constants.php`.

**League/oauth2-server** est framework-agnostic (PSR-7 uniquement) — fonctionne parfaitement avec CI4 via les Filters et Controllers natifs.
