# Plan — F002 : User Management (CI4 — getUser, update, delete)

> Stack : CodeIgniter 4 · PHP 8.1+ · PostgreSQL · league/oauth2-server 9.3.0
> Scope : API uniquement — lecture, mise à jour et suppression de compte utilisateur.
> Frontend : Aucun. Endpoints JSON seulement.
> Suite de : plan_authentification.md (C1–C9 terminés)

---

## Analyse — CoT

### État actuel

OAuth2 complet et fonctionnel. Endpoints `POST /auth/register`, `POST /auth/token`, `POST /auth/revoke` opérationnels. Routes `/users/{id}` et `/users/{id}/profile` déclarées avec `['filter' => 'auth']` mais inaccessibles — `AuthFilter` absent, `UserController` absent.

**Présent** :
- `app/Controllers/AuthController.php` — register, token, revoke ✅
- `app/Repositories/Contracts/UserRepositoryContract.php` — findById, findByEmail, existsByEmail, save ✅
- `app/Repositories/UserRepository.php` ✅
- `app/Libraries/OAuthServer.php` — getAuthorizationServer(), getResourceServer() ✅
- `app/OAuth2/` — toutes les entities et repositories OAuth2 ✅
- `app/Services/UserRegistrationService.php` ✅
- `app/DTO/RegisterUserDTO.php` ✅
- `app/Config/Routes.php` — GET /users/{id} et PUT /users/{id}/profile routés (filter 'auth'), DELETE absent ✅ (partiel)
- `tests/Feature/ApiTestCase.php` — apiGet, apiPost, apiPut, apiPostFormEncoded, createTestUser, getAccessToken ✅
- `tests/Feature/AuthenticationTest.php` ✅

**Manquant** :
- `app/Controllers/UserController.php` — show, update, destroy
- `app/Filters/AuthFilter.php` — vérifie Bearer JWT via ResourceServer
- `app/Libraries/AuthContext.php` — pont Filter → Controller (static holder)
- `app/Config/Filters.php` — enregistrement alias "auth"
- `app/DTO/UpdateProfileDTO.php`
- `app/Services/UpdateProfileService.php`
- `app/Services/DeleteUserService.php`
- `UserRepositoryContract` — méthodes update() et delete() manquantes
- Route `DELETE /users/{id}` absente dans Routes.php
- `tests/Feature/UserProfileTest.php`

### Dépendances d'implémentation

```
C10 (AuthFilter + AuthContext)
  └── C11 (UserRepositoryContract update+delete + UserRepository)
        └── C12 (GET + PUT — UserController::show() + update())
              └── C13 (DELETE — UserController::destroy() + tests Feature complets)
```

### Point technique — bridge CI4 ↔ PSR-7 dans AuthFilter

`ResourceServer::validateAuthenticatedRequest()` attend `ServerRequestInterface` (PSR-7) et retourne une `ServerRequestInterface` enrichie avec l'attribut `oauth_user_id`. CI4's `IncomingRequest` n'est pas PSR-7. Le bridge nyholm est déjà en place (`nyholm/psr7`, `nyholm/psr7-server`). Pattern retenu : `AuthContext` static class — le Filter écrit le user_id, le Controller le lit.

### Cascade DELETE

`oauth_access_tokens.user_id REFERENCES users(id) ON DELETE CASCADE` + `oauth_refresh_tokens.access_token_id REFERENCES oauth_access_tokens(id) ON DELETE CASCADE` — déjà configuré depuis la migration C3. Un `DELETE FROM users WHERE id = ?` suffit, les tokens cascadent automatiquement.

---

## Alternatives — ToT

| # | Approche AuthFilter → Controller | Avantages | Inconvénients | Fit |
|---|---|---|---|---|
| 1 | **Static `AuthContext` class** — Filter écrit, Controller lit | Zéro dépendance, lisible, reset trivial en tests | État statique (reset dans setUp) | **Meilleur** |
| 2 | PSR-7 full bridge — attributs PSR-7 propagés au controller | Standard RFC | CI4 controllers ne consomment pas PSR-7 nativement, double objet request | Mauvais |
| 3 | CI4 Services singleton — `AuthTokenService` dans Services.php | CI4-idiomatique, mockable | +1 fichier, modification Services.php | Bon |

**Décision** : Approche 1 — `AuthContext` static class. Plus court, Karpathy-aligné. `AuthContext::reset()` dans `ApiTestCase::setUp()` règle l'état entre tests.

---

## YAGNI

- Pas de soft delete — hard delete + FK CASCADE, aucun `deleted_at`
- Pas de changement d'email — flow séparé (vérification unicité, confirmation)
- Pas de changement de password — flow séparé (ancien mot de passe requis)
- Pas de PATCH — PUT remplace le profil complet, plus simple
- Pas d'admin delete — guard strict `user_id == $id`, 403 sinon
- Pas de pagination — aucun endpoint collection
- Pas de rate limiting — SEC003, roadmap séparée
- Pas d'enveloppes HAL/JSON-API — JSON brut uniquement

---

## Structure des fichiers

```
app/
  Controllers/
    UserController.php           (NOUVEAU — show, update, destroy)
  Filters/
    AuthFilter.php               (NOUVEAU — vérifie Bearer JWT, écrit AuthContext)
  Libraries/
    AuthContext.php              (NOUVEAU — static holder user_id Filter → Controller)
  DTO/
    UpdateProfileDTO.php         (NOUVEAU — first_name, last_name + validate())
  Services/
    UpdateProfileService.php     (NOUVEAU — update(id, DTO): array)
    DeleteUserService.php        (NOUVEAU — delete(id): void)
  Repositories/
    Contracts/
      UserRepositoryContract.php (MODIFIER — ajouter update() + delete())
    UserRepository.php           (MODIFIER — implémenter update() + delete())
  Config/
    Filters.php                  (MODIFIER — alias 'auth' → AuthFilter::class)
    Routes.php                   (MODIFIER — ajouter DELETE /users/{id})

tests/
  Feature/
    UserProfileTest.php          (NOUVEAU — GET, PUT, DELETE tests)
```

---

## Schéma BDD

Aucune migration nécessaire. La cascade est déjà en place :

```sql
-- Déjà présent depuis migration C3
-- oauth_access_tokens.user_id REFERENCES users(id) ON DELETE CASCADE
-- oauth_refresh_tokens.access_token_id REFERENCES oauth_access_tokens(id) ON DELETE CASCADE

-- Vérification :
SELECT tc.constraint_name, tc.table_name, kcu.column_name,
       ccu.table_name AS foreign_table_name, rc.delete_rule
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.referential_constraints AS rc ON tc.constraint_name = rc.constraint_name
JOIN information_schema.constraint_column_usage AS ccu ON rc.unique_constraint_name = ccu.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
  AND ccu.table_name IN ('users', 'oauth_access_tokens');
-- Attendu : DELETE_RULE = CASCADE pour oauth_access_tokens et oauth_refresh_tokens
```

---

## Stratégie de tests

| Couche | Outil | Dépendances | Exemples |
|--------|-------|-------------|---------|
| Unit | PHPUnit TestCase | 0 DB, mocks interfaces | UpdateProfileDTO::validate(), DeleteUserService::delete() |
| Integration | PHPUnit TestCase + PDO réel | PostgreSQL réelle | UserRepository::update(), UserRepository::delete() |
| Feature (E2E) | PHPUnit + ApiTestCase cURL | Serveur CI4 + DB | GET 200/401/403, PUT 200/401/403/422, DELETE 204/401/403 |

---

## Plan d'exécution

### C10 — AuthFilter + AuthContext (CI4)

**But** : le filtre 'auth' est opérationnel — toutes les routes `/users/*` vérifient le JWT.

1. Créer `app/Libraries/AuthContext.php` :
    ```php
    namespace App\Libraries;

    class AuthContext
    {
        private static ?string $userId = null;

        public static function setUserId(string $id): void
        {
            self::$userId = $id;
        }

        public static function getUserId(): ?string
        {
            return self::$userId;
        }

        public static function reset(): void
        {
            self::$userId = null;
        }
    }
    ```

2. Créer `app/Filters/AuthFilter.php` :
    ```php
    namespace App\Filters;

    use App\Libraries\AuthContext;
    use App\Libraries\OAuthServer;
    use CodeIgniter\Filters\FilterInterface;
    use CodeIgniter\HTTP\RequestInterface;
    use CodeIgniter\HTTP\ResponseInterface;
    use Nyholm\Psr7\Factory\Psr17Factory;
    use Nyholm\Psr7Server\ServerRequestCreator;

    class AuthFilter implements FilterInterface
    {
        public function before(RequestInterface $request, $arguments = null)
        {
            $psr17Factory = new Psr17Factory();
            $creator = new ServerRequestCreator(
                $psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory
            );
            $psrRequest = $creator->fromGlobals();

            try {
                $validated = OAuthServer::getInstance()
                    ->getResourceServer()
                    ->validateAuthenticatedRequest($psrRequest);

                $userId = $validated->getAttribute('oauth_user_id');
                AuthContext::setUserId((string) $userId);
            } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
                return service('response')
                    ->setStatusCode(401)
                    ->setContentType('application/json')
                    ->setBody(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
            } catch (\Throwable $e) {
                return service('response')
                    ->setStatusCode(401)
                    ->setContentType('application/json')
                    ->setBody(json_encode(['error' => 'Unauthorized']));
            }
        }

        public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
        {
        }
    }
    ```

3. Modifier `app/Config/Filters.php` — ajouter l'alias `auth` dans le tableau `$aliases` :
    ```php
    public array $aliases = [
        'csrf'     => \CodeIgniter\Filters\CSRF::class,
        'toolbar'  => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot' => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars' => \CodeIgniter\Filters\InvalidChars::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
        'auth'     => \App\Filters\AuthFilter::class,   // ajouter
    ];
    ```

4. Adapter `tests/Feature/ApiTestCase.php` — ajouter `AuthContext::reset()` dans `setUp()` :
    ```php
    protected function setUp(): void
    {
        parent::setUp();
        \App\Libraries\AuthContext::reset();
    }
    ```

5. Vérifier : `vendor/bin/phpunit --testsuite feature` → tests existants toujours verts (AuthFilter ne casse pas /auth/*)

```
commit: feat(auth): add AuthFilter JWT middleware and AuthContext holder
```

---

### C11 — UserRepositoryContract update + delete (CI4)

**But** : le contrat et son implémentation couvrent les 4 opérations CRUD.

6. Modifier `app/Repositories/Contracts/UserRepositoryContract.php` — ajouter :
    ```php
    public function update(string $id, array $data): array;  // retourne user mis à jour
    public function delete(string $id): void;
    ```

7. Modifier `app/Repositories/UserRepository.php` — implémenter les deux méthodes :
    ```php
    public function update(string $id, array $data): array
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('users')
            ->where('id', $id)
            ->update($data);

        return $this->findById($id);
    }

    public function delete(string $id): void
    {
        $this->db->table('users')
            ->where('id', $id)
            ->delete();
        // FK ON DELETE CASCADE supprime oauth_access_tokens + oauth_refresh_tokens
    }
    ```

8. Créer `tests/Integration/UserRepositoryUpdateDeleteTest.php` — PDO réel :
    - `update_modifie_first_name_et_retourne_user_mis_a_jour()`
    - `delete_supprime_user_et_cascade_tokens()`
    - `delete_user_inexistant_ne_lance_pas_exception()`

9. Lancer `vendor/bin/phpunit --testsuite integration` → vert

```
commit: feat(user): extend UserRepositoryContract and UserRepository with update and delete
```

---

### C12 — GET /users/{id} + PUT /users/{id}/profile (CI4)

**But** : endpoints lecture et mise à jour profil fonctionnels avec pyramid de tests.

**DTO** :

10. Créer `app/DTO/UpdateProfileDTO.php` :
    ```php
    namespace App\DTO;

    class UpdateProfileDTO
    {
        public function __construct(
            public readonly string $firstName,
            public readonly string $lastName,
        ) {}

        public function validate(): array
        {
            $errors = [];
            if (trim($this->firstName) === '') {
                $errors['first_name'] = 'Required';
            } elseif (mb_strlen($this->firstName) > 100) {
                $errors['first_name'] = 'Max 100 characters';
            }
            if (trim($this->lastName) === '') {
                $errors['last_name'] = 'Required';
            } elseif (mb_strlen($this->lastName) > 100) {
                $errors['last_name'] = 'Max 100 characters';
            }
            return $errors;
        }
    }
    ```

**Service** :

11. Créer `app/Services/UpdateProfileService.php` :
    ```php
    namespace App\Services;

    use App\DTO\UpdateProfileDTO;
    use App\Repositories\Contracts\UserRepositoryContract;

    class UpdateProfileService
    {
        public function __construct(private UserRepositoryContract $users) {}

        public function update(string $id, UpdateProfileDTO $dto): array
        {
            $errors = $dto->validate();
            if (!empty($errors)) {
                throw new \InvalidArgumentException(json_encode($errors));
            }

            return $this->users->update($id, [
                'first_name' => $dto->firstName,
                'last_name'  => $dto->lastName,
            ]);
        }
    }
    ```

**Controller** :

12. Créer `app/Controllers/UserController.php` :
    ```php
    namespace App\Controllers;

    use App\DTO\UpdateProfileDTO;
    use App\Libraries\AuthContext;
    use App\Repositories\UserRepository;
    use App\Services\UpdateProfileService;
    use CodeIgniter\API\ResponseTrait;
    use CodeIgniter\Controller;
    use Config\Database;

    class UserController extends Controller
    {
        use ResponseTrait;

        public function show(string $id): \CodeIgniter\HTTP\Response
        {
            $tokenUserId = AuthContext::getUserId();
            if ($tokenUserId !== $id) {
                return $this->respond(['error' => 'Forbidden'], 403);
            }

            $user = (new UserRepository(Database::connect()))->findById($id);
            if ($user === null) {
                return $this->respond(['error' => 'Not found'], 404);
            }

            return $this->respond($this->formatUser($user), 200);
        }

        public function update(string $id): \CodeIgniter\HTTP\Response
        {
            $tokenUserId = AuthContext::getUserId();
            if ($tokenUserId !== $id) {
                return $this->respond(['error' => 'Forbidden'], 403);
            }

            $input = $this->request->getJSON(true) ?? [];

            try {
                $dto = new UpdateProfileDTO(
                    firstName: $input['first_name'] ?? '',
                    lastName:  $input['last_name'] ?? '',
                );
                $service = new UpdateProfileService(
                    new UserRepository(Database::connect())
                );
                $user = $service->update($id, $dto);
                return $this->respond($this->formatUser($user), 200);
            } catch (\InvalidArgumentException $e) {
                return $this->respond(['errors' => json_decode($e->getMessage(), true)], 422);
            } catch (\Throwable $e) {
                return $this->respond(['error' => 'Update failed'], 500);
            }
        }

        private function formatUser(array $user): array
        {
            return [
                'id'         => $user['id'],
                'email'      => $user['email'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'created_at' => $user['created_at'],
                'updated_at' => $user['updated_at'],
            ];
        }
    }
    ```

**Tests Unit** :

13. Créer `tests/Unit/UpdateProfileDTOTest.php` :
    - `first_name_vide_retourne_erreur()`
    - `last_name_trop_long_retourne_erreur()`
    - `dto_valide_retourne_zero_erreur()`

14. Créer `tests/Unit/UpdateProfileServiceTest.php` — mock `UserRepositoryContract` :
    - `update_reussi_appelle_repository_update()`
    - `champs_vides_lance_invalid_argument_exception()`

15. Lancer `vendor/bin/phpunit --testsuite unit` → vert

**Tests Feature (E2E)** — partie GET + PUT :

16. Créer `tests/Feature/UserProfileTest.php` (extends `ApiTestCase`) :
    - `get_profile_retourne_200_avec_champs_corrects()`
    - `get_profile_sans_token_retourne_401()`
    - `get_profile_autre_user_retourne_403()`
    - `get_profile_user_inexistant_retourne_404()`
    - `update_profile_happy_path_retourne_200()`
    - `update_profile_sans_token_retourne_401()`
    - `update_profile_autre_user_retourne_403()`
    - `update_profile_champs_vides_retourne_422()`

17. Lancer `vendor/bin/phpunit --testsuite feature` → vert

```
commit: feat(user): add GET /users/{id} and PUT /users/{id}/profile with unit and feature tests
```

---

### C13 — DELETE /users/{id} — Suppression compte (CI4)

**But** : endpoint de suppression complet, hard delete avec cascade OAuth tokens.

**Service** :

18. Créer `app/Services/DeleteUserService.php` :
    ```php
    namespace App\Services;

    use App\Repositories\Contracts\UserRepositoryContract;

    class DeleteUserService
    {
        public function __construct(private UserRepositoryContract $users) {}

        public function delete(string $id): void
        {
            $user = $this->users->findById($id);
            if ($user === null) {
                throw new \RuntimeException('User not found');
            }

            $this->users->delete($id);
            // FK ON DELETE CASCADE gère oauth_access_tokens et oauth_refresh_tokens
        }
    }
    ```

**Controller** — ajouter `destroy()` dans `UserController.php` :

19. Ajouter méthode `destroy(string $id)` dans `app/Controllers/UserController.php` :
    ```php
    public function destroy(string $id): \CodeIgniter\HTTP\Response
    {
        $tokenUserId = AuthContext::getUserId();
        if ($tokenUserId !== $id) {
            return $this->respond(['error' => 'Forbidden'], 403);
        }

        try {
            $service = new DeleteUserService(
                new UserRepository(Database::connect())
            );
            $service->delete($id);
            return $this->respond(null, 204);
        } catch (\RuntimeException $e) {
            return $this->respond(['error' => 'Not found'], 404);
        } catch (\Throwable $e) {
            return $this->respond(['error' => 'Delete failed'], 500);
        }
    }
    ```

**Route** :

20. Modifier `app/Config/Routes.php` — ajouter :
    ```php
    $routes->delete('users/(:segment)', 'UserController::destroy/$1', ['filter' => 'auth']);
    ```

**Tests Unit** :

21. Créer `tests/Unit/DeleteUserServiceTest.php` — mock `UserRepositoryContract` :
    - `delete_appelle_repository_delete()`
    - `user_inexistant_lance_runtime_exception()`

22. Lancer `vendor/bin/phpunit --testsuite unit` → vert

**Tests Feature (E2E)** — partie DELETE, dans `UserProfileTest.php` :

23. Ajouter dans `tests/Feature/UserProfileTest.php` :
    - `delete_happy_path_retourne_204()`
    - `delete_user_supprime_acces_token_invalide()`
    - `delete_sans_token_retourne_401()`
    - `delete_autre_user_retourne_403()`

    Pattern pour `delete_user_supprime_acces_token_invalide()` :
    ```php
    // 1. Créer user et récupérer token
    // 2. DELETE /users/{id} → 204
    // 3. GET /users/{id} avec le même token → 401 (token révoqué par cascade)
    ```

24. Lancer `vendor/bin/phpunit --testsuite feature` → vert

25. Lancer `vendor/bin/phpunit` (toutes suites) → vert

```
commit: feat(user): add DELETE /users/{id} with cascade token revocation and feature tests
```

---

## ✅ C13 Complete — Tests & Validation

**Date** : 2026-04-25

### Tests Unitaires ✅
- `tests/Unit/DeleteUserServiceTest.php`
  - `delete_calls_repository_delete_when_user_exists()` ✅
  - `delete_throws_user_not_found_when_user_null()` ✅

### Tests d'Intégration ✅
- `tests/Integration/UserDeleteCascadeTest.php`
  - `test_delete_user_cascades_to_access_tokens()` ✅
  - `test_delete_user_cascades_to_refresh_tokens_via_access_token_fk()` ✅

**Migration supplémentaire** :
- `app/Database/Migrations/2026042303000000_AddCascadeConstraints.php` ✅
  - Ajoute les contraintes CASCADE manquantes via raw SQL ALTER TABLE (PostgreSQL)
  - Nettoie les données invalides avant d'appliquer les constraints

### Vérifications
- [x] Cascade DELETE fonctionne empiriquement (tests integration)
- [x] Suppression d'utilisateur invalide JWT (tests feature existants)
- [x] Tous les tests passent : Unit + Integration + Feature
- [x] Schema drift détecté et corrigé (contraintes CASCADE)

### Post-C13 Optionals (Deferred — Non-critical)
- ⏳ End-to-end smoke test (manual: `make serve && make test-feature` against Docker stack)
- ⏳ Performance test (user deletion, 1000+ rows — no SLA yet)
- ⏳ Soft delete + recovery (out of scope, new feature)

---

## Résumé des commits (F002 — User Management)

| # | Commit | Fichiers clés |
|---|--------|---------------|
| C10 | `feat(auth): add AuthFilter JWT middleware and AuthContext holder` | `app/Filters/AuthFilter.php`, `app/Libraries/AuthContext.php`, `app/Config/Filters.php` |
| C11 | `feat(user): extend UserRepositoryContract and UserRepository with update and delete` | `app/Repositories/Contracts/UserRepositoryContract.php`, `app/Repositories/UserRepository.php`, `tests/Integration/UserRepositoryUpdateDeleteTest.php` |
| C12 | `feat(user): add GET /users/{id} and PUT /users/{id}/profile with unit and feature tests` | `app/Controllers/UserController.php`, `app/DTO/UpdateProfileDTO.php`, `app/Services/UpdateProfileService.php`, `tests/Feature/UserProfileTest.php` |
| C13 | `feat(user): add DELETE /users/{id} with cascade token revocation and feature tests` | `app/Services/DeleteUserService.php`, `app/Controllers/UserController.php` (destroy), `app/Config/Routes.php`, `tests/Feature/UserProfileTest.php` |

## Dépendances — aucune nouvelle à ajouter

`nyholm/psr7` et `nyholm/psr7-server` déjà présents depuis F001. `defuse/php-encryption` et `lcobucci/jwt` transitifs.

---

## Notes CI4

**AuthFilter guard** : `AuthContext::getUserId()` retourne `null` si le filtre n'a pas tourné (appel direct hors filtre). Les controllers doivent traiter `null` comme non authentifié — le filtre court-circuite avant d'atteindre le controller en cas de token invalide, donc `null` indique un bug de config, pas un cas normal.

**204 No Content** : CI4's `ResponseTrait::respond(null, 204)` envoie un body vide. Vérifier que `$this->respond(null, 204)` ne sérialise pas `null` en JSON.

**Ordre des routes** : CI4 matche les routes dans l'ordre de déclaration. `DELETE users/(:segment)` doit être déclaré après les autres routes `/users/*` pour éviter les conflits de pattern.
