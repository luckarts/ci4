# APEX ARCHITECT — Pyramide de tests BC User

> Branche : `feature/backend/signup-response-token`
> Scope : `backend/tests/` BC User uniquement

---

```xml
<architect>

<cot>
### CoT : Analyse état actuel → cible

**1. État actuel (audit exhaustif)**

12 tests E2E pour le BC User, répartis en 4 fichiers :

| Fichier | Tests | Codes HTTP |
|---|---|---|
| RegisterTest.php | 6 | 201, 201, 422, 422, 422, 400 |
| GetProfileTest.php | 2 | 200, 401 |
| UpdateProfileTest.php | 3 | 200, 401, 422 |
| DeleteProfileTest.php | 2 | 204, 401 |

`tests/Unit/` → vide (`.gitkeep`).
`tests/Integration/` → vide (`.gitkeep`).
`phpunit.xml.dist` → suites `unit`, `integration`, `E2E`, `all` déjà déclarées, pointant vers les bons répertoires.
Makefile → `test-e2e` existe, pas de cibles séparées pour unit/integration/smoke.

**2. Architecture de validation**

`user_mapping.yaml` déclare des contraintes Symfony Validator sous `validation.write`.
`MappingConstraintLoader` (implémente `LoaderInterface`) les lit via `MappingConfigLoader` et les injecte dans le graphe Symfony Validator via DI.
→ Les validations 422 sont donc exercées par `ValidatorInterface`, testable via `KernelTestCase` **sans HTTP ni DB** (kernel boot uniquement).

`RegisterProcessor` catch `UserAlreadyExistsException` → `UnprocessableEntityHttpException` (422 duplicate email).
→ Testable en pur mock PHPUnit via `UserRegistrationServiceTest`.

Les 401 unauthenticated sont produits par le firewall Symfony (JWT) → couche HTTP → **non testables sans HTTP**.
→ Garder en E2E mais promouvoir en `#[Group('smoke')]` (pas d'écriture DB, très rapides).

**3. Contrainte DB unique email**

`User.php` : `#[ORM\UniqueConstraint(name: 'uniq_users_email')]` + `#[ORM\Column(unique: true)]`.
→ Testable via `DoctrineUserRepository` + vrai Doctrine/Postgres → Integration.

**4. Impact**

- Supprimer 7 tests E2E (422 × 4 + 401 × 3)
- Créer 4 fichiers (2 Unit validation, 1 Unit service, 1 Integration)
- Regrouper les 401 dans un fichier smoke dédié
- Ajouter cibles Makefile
</cot>

<tot>
### ToT : Alternatives pour les tests de validation (422)

| # | Approche | Pros | Cons | Fit |
|---|---|---|---|---|
| 1 | `KernelTestCase` + `ValidatorInterface` du container | Teste le vrai `MappingConstraintLoader` câblé, zéro HTTP, zéro DB, < 100ms | Boot kernel (léger overhead ~200ms) | **Best** |
| 2 | Instancier manuellement `MappingConstraintLoader` + `ValidatorBuilder` | Pur unit sans kernel | Reproduce la config DI à la main, fragile | Over-engineered |
| 3 | Rester en E2E mais ajouter `#[Group('unit-like')]` | Zero effort | Toujours Docker dépendant, masque la nature des tests | Wrong layer |

**Décision** : Approche 1.
**Rationale** : `MappingConstraintLoader` est une dépendance interne au projet. La tester via le container garantit que la config YAML → contraintes est correcte. Le boot kernel sans DB est reproductible hors Docker.

---

### ToT : Alternatives pour le test duplicate email (422 depuis `RegisterProcessor`)

| # | Approche | Pros | Cons | Fit |
|---|---|---|---|---|
| 1 | Unit test `UserRegistrationService` avec mocks | Teste la logique métier pure, 0 dépendance | Ne teste pas le catch dans `RegisterProcessor` | **Best** |
| 2 | Unit test `RegisterProcessor` avec mocks (service + token generator) | Teste le catch dans le processor | Deux mocks lourds, la logique est dans le service | Acceptable |
| 3 | Integration test avec vrai `UserRegistrationService` + vrai repo | Teste le flow complet | Besoin DB, appartient à Integration | Overkill pour ce cas |

**Décision** : Approche 1.
**Rationale** : La règle "ne pas enregistrer deux fois le même email" appartient au domaine (`UserRegistrationService`). Le `RegisterProcessor` ne fait que convertir l'exception → HTTP, ce qui est testé implicitement en E2E happy path.

---

### ToT : Alternatives pour les 401 unauthenticated

| # | Approche | Pros | Cons | Fit |
|---|---|---|---|---|
| 1 | Garder en E2E, regrouper dans `UnauthenticatedTest.php`, ajouter `#[Group('smoke')]` | Pas de changement de nature, vérifie le firewall, rapides (pas d'écriture DB) | Restent E2E (Docker) | **Best** |
| 2 | Supprimer complètement (confiance dans le framework Symfony JWT) | Moins de tests | Aucune alerte si la sécurité est mal configurée | Risqué |
| 3 | Tester le firewall via `WebTestCase` standalone | Plus rapide que l'infra Docker | Dépend de la config JWT en test, fragile | Sur-ingénierie |

**Décision** : Approche 1.
</tot>

<cod>
### CoD : 3 drafts d'organisation

**Draft 1** (Minimal — ne supprimer rien, juste ajouter) :
- Ajouter `RegisterValidationTest.php` et `UserProfileValidationTest.php` en Unit
- Ajouter `UniqueEmailTest.php` en Integration
- Laisser les 422 en E2E (duplication)

**Problème** : les tests 422 E2E restent, la pyramide n'est pas respectée, les tests sont exécutés deux fois.

**Draft 2** (Équilibré) :
- Créer les tests Unit et Integration
- Supprimer les 422 des fichiers E2E existants
- Laisser les 401 en place sans toucher

**Problème** : les 401 restent dans des fichiers thématiques (GetProfileTest, etc.) sans group `smoke`, ils ne s'exécutent pas en première vague CI.

**Draft 3** (Final — sélectionné) :
- Créer `tests/Unit/User/Validation/RegisterValidationTest.php`
- Créer `tests/Unit/User/Validation/UserProfileValidationTest.php`
- Créer `tests/Unit/User/Service/UserRegistrationServiceTest.php`
- Créer `tests/Integration/User/Repository/UniqueEmailTest.php`
- Supprimer les 422 des fichiers E2E (3 tests dans `RegisterTest`, 1 dans `UpdateProfileTest`)
- Supprimer les 401 des fichiers E2E existants, créer `tests/E2E/User/UnauthenticatedTest.php` avec `#[Group('smoke')]`
- Ajouter `#[Group('smoke')]` à `register_missing_fields` (400, pas d'écriture DB)
- Ajouter cibles Makefile : `test-smoke`, `test-unit`, `test-integration`

**Résultat net** : 12 tests E2E → 7 E2E (dont 6 smoke), +8 Unit, +1 Integration.
</cod>

<yagni>
### YAGNI : Ce qu'on ne fait PAS

- Pas de `UserVoterTest.php` → aucun `UserVoter` n'existe dans le BC User (profil = user courant seulement, pas de Voter)
- Pas de test `UserProfileTransformer` en unit → pure conversion de champs, aucune logique conditionnelle
- Pas de test `ProfileProvider` en unit → délègue à Security + Transformer, testé via E2E happy path
- Pas de test du `PasswordGrantTokenGenerator` en unit → dépend de League\OAuth2\Server, couvert en E2E smoke (register_success_token_is_usable)
- Pas de test de cascade delete DB (pas d'entité enfant liée à User dans ce BC)
- Pas de refactoring des helpers `ApiTestHelper`
- Pas de modification de `phpunit.xml.dist` (suites déjà correctement configurées)
- Pas de test pour `Role` enum → pas de logique métier
</yagni>

<patterns>
### Pattern Selection

**Pattern 1 — Unit validation** : `KernelTestCase` + `ValidatorInterface`
**Confidence** : 0.95
**Source** : `src/Shared/Infrastructure/Validation/MappingConstraintLoader.php:10` (implémente `LoaderInterface`, câblé via DI)
**Raison** : seule façon de tester la vraie config YAML → contraintes sans dupliquer le câblage DI

**Pattern 2 — Unit service** : `TestCase` + `createMock()` PHPUnit
**Confidence** : 0.99
**Source** : `src/User/Application/Service/UserRegistrationService.php:18`
**Raison** : `UserRegistrationService` ne dépend que d'interfaces (`UserRepositoryInterface`, `PasswordHasherInterface`, `EventDispatcherInterface`) → mocking trivial

**Pattern 3 — Integration repository** : `KernelTestCase` + `EntityManagerInterface` + transaction rollback
**Confidence** : 0.90
**Source** : `src/User/Infrastructure/Doctrine/DoctrineUserRepository.php` (flush automatique)
**Raison** : tester la contrainte DB unique nécessite un vrai Postgres ; le rollback garantit l'isolation entre tests

**Pattern 4 — E2E smoke 401** : `AbstractApiTestCase` + `#[Group('smoke')]`, sans écriture DB
**Confidence** : 0.95
**Source** : pattern existant dans `RegisterTest.php:15` (smoke déjà utilisé)
</patterns>

<summary>
  <task-type>feature</task-type>
  <complexity>medium</complexity>

  <files>
    <!-- Nouveaux fichiers -->
    <file path="backend/tests/Unit/User/Validation/RegisterValidationTest.php" action="create"/>
    <file path="backend/tests/Unit/User/Validation/UserProfileValidationTest.php" action="create"/>
    <file path="backend/tests/Unit/User/Service/UserRegistrationServiceTest.php" action="create"/>
    <file path="backend/tests/Integration/User/Repository/UniqueEmailTest.php" action="create"/>
    <file path="backend/tests/E2E/User/UnauthenticatedTest.php" action="create"/>
    <!-- Fichiers E2E modifiés (suppression de tests 422 et 401) -->
    <file path="backend/tests/E2E/User/RegisterTest.php" action="modify"/>
    <file path="backend/tests/E2E/User/GetProfileTest.php" action="modify"/>
    <file path="backend/tests/E2E/User/UpdateProfileTest.php" action="modify"/>
    <file path="backend/tests/E2E/User/DeleteProfileTest.php" action="modify"/>
    <!-- CI -->
    <file path="backend/Makefile" action="modify"/>
  </files>

  <execution-strategy>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- COMMIT 1 : Unit tests — validation RegisterUserRequest  -->
    <!-- ═══════════════════════════════════════════════════════ -->

    <step order="1">
      Créer `tests/Unit/User/Validation/RegisterValidationTest.php`
      extends `KernelTestCase`, groupe `unit` + `user`
      Utilise `static::getContainer()->get('validator')` (ValidatorInterface)
      Cas couverts :
        - `email_blank_fails()` → NotBlank
        - `email_invalid_format_fails()` → Email (ex: "not-an-email")
        - `password_blank_fails()` → NotBlank
        - `password_too_short_fails()` → Length(min:8) (ex: "short")
        - `firstname_blank_fails()` → NotBlank
        - `lastname_blank_fails()` → NotBlank
        - `valid_request_passes()` → 0 violations
      Chaque test : instancie `RegisterUserRequest`, peuple les champs, appelle `$validator->validate($dto)`, assert sur le count de violations
    </step>

    <step order="2">
      Créer `tests/Unit/User/Validation/UserProfileValidationTest.php`
      extends `KernelTestCase`, groupe `unit` + `user`
      Cas couverts :
        - `firstname_blank_fails()` → NotBlank
        - `lastname_blank_fails()` → NotBlank
        - `firstname_too_long_fails()` → Length(max:100) (string de 101 chars)
        - `lastname_too_long_fails()` → Length(max:100)
        - `valid_profile_passes()` → 0 violations
      Même pattern : instancie `UserProfile`, peuple, validate, assert
    </step>

    <step order="3">
      Lancer les tests Unit sans Docker pour valider :
        cd backend &amp;&amp; php bin/phpunit --testsuite unit
      Si erreur "MappingConfigLoader not found" → vérifier l'autowiring dans `src/Shared/config/services.yaml`
    </step>

    <commit message="test(user/unit): add RegisterValidationTest and UserProfileValidationTest via KernelTestCase"/>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- COMMIT 2 : Unit test — UserRegistrationService         -->
    <!-- ═══════════════════════════════════════════════════════ -->

    <step order="4">
      Créer `tests/Unit/User/Service/UserRegistrationServiceTest.php`
      extends `TestCase` (pur PHPUnit, pas de kernel)
      Injecte des mocks :
        - `UserRepositoryInterface` : mock
        - `PasswordHasherInterface` : mock, stub `hash()` → "hashed"
        - `EventDispatcherInterface` : mock
      Cas couverts :
        - `register_throws_on_duplicate_email()` :
            `$repo->existsByEmail()->willReturn(true)` → assert throws `UserAlreadyExistsException`
        - `register_saves_and_dispatches_event()` :
            `$repo->existsByEmail()->willReturn(false)` → assert `save()` appelé 1×, `dispatch()` appelé 1×
      Pas de `assertEquals` sur UUID (non déterministe), utiliser `assertInstanceOf(User::class, ...)` si besoin
    </step>

    <step order="5">
      Lancer `php bin/phpunit --testsuite unit` (hors Docker) — tous verts
    </step>

    <commit message="test(user/unit): add UserRegistrationServiceTest - mock-based duplicate email check"/>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- COMMIT 3 : E2E slim — supprimer 422 et 401 déplacés   -->
    <!-- ═══════════════════════════════════════════════════════ -->

    <step order="6">
      Modifier `tests/E2E/User/RegisterTest.php` :
        SUPPRIMER : `register_invalid_email()`, `register_short_password()`, `register_duplicate_email()`
        GARDER : `register_success()` (smoke ✓), `register_success_token_is_usable()` (smoke ✓), `register_missing_fields()`
        MODIFIER : ajouter `#[Group('smoke')]` à `register_missing_fields()` (400, pas d'écriture DB)
    </step>

    <step order="7">
      Modifier `tests/E2E/User/GetProfileTest.php` :
        SUPPRIMER : `get_profile_unauthenticated()`
        GARDER : `get_profile_authenticated()` (smoke ✓)
    </step>

    <step order="8">
      Modifier `tests/E2E/User/UpdateProfileTest.php` :
        SUPPRIMER : `update_profile_unauthenticated()`, `update_profile_blank_fields()`
        GARDER : `update_profile_success()` (smoke ✓)
    </step>

    <step order="9">
      Modifier `tests/E2E/User/DeleteProfileTest.php` :
        SUPPRIMER : `delete_profile_unauthenticated()`
        GARDER : `delete_profile_success()` (smoke ✓)
    </step>

    <step order="10">
      Créer `tests/E2E/User/UnauthenticatedTest.php`
      extends `AbstractApiTestCase`, groupe `smoke` + `e2e` + `user`
      Cas couverts (tous `#[Group('smoke')]`, pas d'écriture DB) :
        - `get_profile_without_token_returns_401()` → GET /api/user/profile sans token → 401
        - `update_profile_without_token_returns_401()` → PUT /api/user/profile sans token → 401
        - `delete_profile_without_token_returns_401()` → DELETE /api/user/profile sans token → 401
    </step>

    <step order="11">
      Lancer les tests E2E User (via Docker) : make test-e2e
      Vérifier que les smoke passent : bin/phpunit --group smoke
    </step>

    <commit message="test(user/e2e): slim E2E - move 422 to unit, group 401s as smoke in UnauthenticatedTest"/>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- COMMIT 4 : Integration — UniqueEmailTest               -->
    <!-- ═══════════════════════════════════════════════════════ -->

    <step order="12">
      Créer `tests/Integration/User/Repository/UniqueEmailTest.php`
      extends `KernelTestCase`, groupe `integration` + `user`
      Setup : `EntityManagerInterface` depuis container, `DoctrineUserRepository`
      Teardown : rollback ou truncate table `users` (utiliser transactions Doctrine ou `doctrine:schema:drop` → non, trop lourd)
      Pattern recommandé : wrapper chaque test dans `$em->beginTransaction()` + `$em->rollback()` via setUp/tearDown
      Cas couverts :
        - `exists_by_email_returns_true_when_user_registered()` :
            Crée et sauvegarde un User → `$repo->existsByEmail('test@example.com')` → assertTrue
        - `save_second_user_with_same_email_throws_db_constraint()` :
            Sauvegarde User 1 avec email X → sauvegarde User 2 avec même email X
            → assert throws `\Doctrine\DBAL\Exception\UniqueConstraintViolationException`
            (ou `\Doctrine\ORM\Exception\EntityIdentityCollisionException` selon Doctrine version)
      Dépend de la DB → nécessite `make test-up` avant exécution
    </step>

    <step order="13">
      Lancer make test-up (si pas déjà démarré)
      Puis : $(PHP_RUN) bin/phpunit --testsuite integration
      Les 2 tests doivent passer
    </step>

    <commit message="test(user/integration): add UniqueEmailTest - DB unique constraint on email"/>

    <!-- ═══════════════════════════════════════════════════════ -->
    <!-- COMMIT 5 : Makefile — cibles smoke/unit/integration    -->
    <!-- ═══════════════════════════════════════════════════════ -->

    <step order="14">
      Modifier `backend/Makefile` — ajouter les cibles suivantes après `test-e2e` :

        ## Lance les tests smoke (rapides, priorité CI)
        test-smoke:
            $(PHP_RUN) bin/phpunit --group smoke

        ## Lance les tests unitaires (sans DB)
        test-unit:
            $(PHP_RUN) bin/phpunit --testsuite unit

        ## Lance les tests d'intégration (nécessite DB)
        test-integration: test-up
            $(PHP_RUN) bin/phpunit --testsuite integration

      Modifier `.PHONY` pour inclure les nouvelles cibles.
      NE PAS modifier la cible `test` (pipeline existant conservé tel quel).
    </step>

    <commit message="ci(test): add test-smoke, test-unit, test-integration targets to Makefile"/>

  </execution-strategy>
</summary>

</architect>
```

---

## Bilan — avant / après

| Couche | Avant | Après | Delta |
|---|---|---|---|
| Unit | 0 | 8 (+`SanityTest` existant) | +8 |
| Integration | 0 | 2 | +2 |
| E2E | 12 | 7 | -5 |
| **Total** | **12** | **17** | **+5** |

## Tableau de déplacement précis

| Test supprimé de E2E | Déplacé vers | Fichier cible |
|---|---|---|
| `register_invalid_email` (422) | Unit | `RegisterValidationTest` |
| `register_short_password` (422) | Unit | `RegisterValidationTest` |
| `register_duplicate_email` (422) | Unit | `UserRegistrationServiceTest` |
| `update_profile_blank_fields` (422) | Unit | `UserProfileValidationTest` |
| `get_profile_unauthenticated` (401) | E2E smoke | `UnauthenticatedTest` |
| `update_profile_unauthenticated` (401) | E2E smoke | `UnauthenticatedTest` |
| `delete_profile_unauthenticated` (401) | E2E smoke | `UnauthenticatedTest` |

## Ordre CI (post-implémentation)

```bash
make test-smoke        # ~3 tests, ms — stop si échec
make test-unit         # ~8 tests, sans DB
make test-integration  # ~2 tests, nécessite DB up
make test-e2e          # 7 tests, Docker complet
```
