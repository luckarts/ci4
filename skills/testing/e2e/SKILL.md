---
name: test:e2e
description: "Tests E2E PHPUnit - Parcours utilisateur complets avec OAuth2 et API Platform"
argument-hint: [user-profile|delete|update|security]
triggers:
  - test e2e
  - test end to end
  - test bout en bout
  - tester parcours utilisateur
  - test authentifie
  - OAuth2IntegrationTestTrait
  - test securite
---

# Tests E2E (End-to-End) PHPUnit

Tests de parcours utilisateur complets : inscription → login OAuth2 → appel API authentifie → verification. Utilisent le trait `OAuth2IntegrationTestTrait` pour reutiliser le setup d'authentification.

## Conventions du projet

- Extends `WebTestCase`
- Attribut `#[Group('e2e')]` (+ `#[Group('security')]` pour les tests auth)
- Utilise `OAuth2IntegrationTestTrait` pour le setup utilisateur + token
- Tests de securite centralises dans `AuthenticationTest` (DRY)
- Tests metier dans des fichiers dedies par ressource
- Suite PHPUnit : `--testsuite E2E`

## Structure des fichiers

```
tests/
  E2E/
    Traits/
      OAuth2IntegrationTestTrait.php    # Trait reutilisable (user + token + DB)
    Security/
      AuthenticationTest.php            # Tests auth centralises (401 sans/avec token)
    User/
      GetUserProfileTest.php            # GET /api/users/me
      UpdateUserIntegrationTest.php     # PUT /api/users/me
      DeleteUserIntegrationTest.php     # DELETE /api/users/me
```

## Le Trait OAuth2IntegrationTestTrait

Fournit tout le setup reutilisable pour les tests E2E authentifies.

```php
trait OAuth2IntegrationTestTrait
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private static bool $schemaCreated = false;
    private KernelBrowser $client;

    // Donnees du user de test
    private ?string $testUserId = null;
    private string $testUserEmail;
    private string $testUserPassword = 'SecurePassword123!';
    private string $testUserUsername;
    private ?string $testUserAccessToken = null;

    // Cree un user + recupere son token
    private function createSharedTestUser(): void
    {
        $this->testUserId = $this->createTestUser(
            $this->testUserEmail,
            $this->testUserPassword,
            $this->testUserUsername
        );

        // Commit pour que OAuth2 puisse voir le user
        if ($this->connection->isTransactionActive()) {
            $this->connection->commit();
            $this->connection->beginTransaction();
        }

        $this->testUserAccessToken = $this->getAccessToken(
            $this->testUserEmail,
            $this->testUserPassword
        );
    }

    // POST /api/auth/register → retourne l'ID
    private function createTestUser(string $email, string $password, string $username): ?string { /* ... */ }

    // POST /api/auth/token → retourne l'access_token
    private function getAccessToken(string $email, string $password): ?string { /* ... */ }

    // Schema BDD + transaction + client OAuth2
    private function initializeDatabaseIfNeeded(): void { /* ... */ }
    private function initializeOAuth2TestClient(): void { /* ... */ }
}
```

## 1. Test E2E d'une ressource (GET Profile)

Pattern : setUp via trait → requete authentifiee → assertions structure + valeurs + securite.

```php
declare(strict_types=1);

namespace App\Tests\E2E\User;

use App\Tests\E2E\Traits\OAuth2IntegrationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('e2e')]
final class GetUserProfileTest extends WebTestCase
{
    use OAuth2IntegrationTestTrait;

    private const API_PROFILE_URL = '/api/users/me';
    private const CONTENT_TYPE_JSON_LD = 'application/ld+json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->initializeDatabaseIfNeeded();

        // Email/username unique par test (evite les collisions)
        $uniqueId = substr(uniqid(), -6);
        $this->testUserEmail = "profile{$uniqueId}@example.com";
        $this->testUserUsername = "profile{$uniqueId}";

        // User + token via trait
        $this->createSharedTestUser();
    }

    public function testGetCurrentUserProfile(): void
    {
        // Act : requete authentifiee
        $this->client->request('GET', self::API_PROFILE_URL, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->testUserAccessToken,
            'CONTENT_TYPE' => self::CONTENT_TYPE_JSON_LD,
            'HTTP_ACCEPT' => self::CONTENT_TYPE_JSON_LD
        ]);

        // Assert : status
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Assert : structure
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('createdAt', $response);

        // Assert : valeurs coherentes avec l'inscription
        $this->assertEquals($this->testUserEmail, $response['email']);
        $this->assertEquals($this->testUserUsername, $response['username']);

        // Assert : UUID valide
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $response['id']
        );

        // Assert : JSON-LD
        $this->assertArrayHasKey('@context', $response);
        $this->assertArrayHasKey('@id', $response);
        $this->assertArrayHasKey('@type', $response);

        // Assert : securite - le password n'est JAMAIS expose
        $this->assertArrayNotHasKey('password', $response);
        $this->assertArrayNotHasKey('passwordHash', $response);
    }

    public function testProfileDataConsistencyWithRegistration(): void
    {
        $this->client->request('GET', self::API_PROFILE_URL, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->testUserAccessToken,
            'CONTENT_TYPE' => self::CONTENT_TYPE_JSON_LD,
            'HTTP_ACCEPT' => self::CONTENT_TYPE_JSON_LD
        ]);

        $profile = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($this->testUserEmail, $profile['email']);
        $this->assertEquals($this->testUserUsername, $profile['username']);

        // Timestamp recent (moins de 5 secondes)
        $createdAt = new \DateTimeImmutable($profile['createdAt']);
        $diff = (new \DateTimeImmutable())->getTimestamp() - $createdAt->getTimestamp();
        $this->assertLessThan(5, $diff);
    }
}
```

## 2. Test E2E avec mutation (PUT Update)

Pattern : update + verification des valeurs + validation + unicite email.

```php
#[Group('e2e')]
final class UpdateUserIntegrationTest extends WebTestCase
{
    use OAuth2IntegrationTestTrait;

    // Helper : requete PUT avec Content-Type + token
    private function makePutRequest(array $data, ?string $token = null): void
    {
        $headers = [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json',
        ];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request('PUT', '/api/users/me', [], [], $headers, json_encode($data));
    }

    public function testSuccessfulUserUpdate(): void
    {
        $this->makePutRequest(
            ['email' => 'updated@example.com', 'username' => 'updated_username'],
            $this->testUserAccessToken
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('updated@example.com', $response['email']);
        $this->assertSame('updated_username', $response['username']);
    }

    // Validation domaine (email invalide → 422 avec violations)
    public function testUpdateWithInvalidData(): void
    {
        $this->makePutRequest(
            ['email' => 'invalid-email-format', 'username' => 'valid'],
            $this->testUserAccessToken
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('violations', $response);
        $this->assertSame('email', $response['violations'][0]['propertyPath']);
    }

    // Garder son propre email (excludeCurrentUser: true)
    public function testUpdateKeepingOwnEmail(): void
    {
        $this->makePutRequest(
            ['email' => $this->testUserEmail, 'username' => 'newuser'],
            $this->testUserAccessToken
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    // Email deja pris par un autre user → 422
    public function testUpdateWithEmailTakenByAnotherUser(): void
    {
        // Creer un autre user
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/ld+json'
        ], json_encode([
            'email' => 'taken@example.com',
            'username' => 'other',
            'password' => 'Password123!'
        ]));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Essayer de voler son email
        $this->makePutRequest(
            ['email' => 'taken@example.com', 'username' => $this->testUserUsername],
            $this->testUserAccessToken
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already exists', $response['violations'][0]['message']);
    }
}
```

## 3. Test E2E avec suppression (DELETE)

Pattern : suppression + verification post-suppression (401 apres delete).

```php
#[Group('e2e')]
final class DeleteUserIntegrationTest extends WebTestCase
{
    use OAuth2IntegrationTestTrait;

    public function testSuccessfulUserDelete(): void
    {
        $this->client->request('DELETE', '/api/users/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->testUserAccessToken
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Verification : le user n'existe plus → 401
        $this->client->request('GET', '/api/users/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->testUserAccessToken
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testDeleteWithoutAuthentication(): void
    {
        $this->client->request('DELETE', '/api/users/me');
        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
```

## 4. Tests de securite centralises (DRY)

Pattern : DataProvider sur tous les endpoints proteges. Un seul fichier pour tester 401 sans/avec token invalide.

```php
#[Group('e2e')]
#[Group('security')]
final class AuthenticationTest extends WebTestCase
{
    // Sans token → 401
    #[DataProvider('protectedEndpointsProvider')]
    public function testProtectedEndpointWithoutToken(string $method, string $uri): void
    {
        $client = static::createClient();
        $client->request($method, $uri, [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // Token invalide → 401
    #[DataProvider('protectedEndpointsProvider')]
    public function testProtectedEndpointWithInvalidToken(string $method, string $uri): void
    {
        $client = static::createClient();
        $client->request($method, $uri, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid_fake_token_12345',
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json'
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    // Ajouter chaque nouvel endpoint protege ici
    public static function protectedEndpointsProvider(): iterable
    {
        yield 'GET /api/users/me' => ['GET', '/api/users/me'];
        // yield 'PUT /api/users/me' => ['PUT', '/api/users/me'];
        // yield 'DELETE /api/users/me' => ['DELETE', '/api/users/me'];
    }
}
```

## Pattern setUp E2E (avec le trait)

```
setUp()
  ├── createClient()
  ├── initializeDatabaseIfNeeded()          # Schema + transaction + OAuth2 client
  ├── Generer email/username unique         # substr(uniqid(), -6)
  └── createSharedTestUser()                # Register + commit + getAccessToken
       ├── POST /api/auth/register
       ├── connection->commit() + beginTransaction()
       └── POST /api/auth/token → $testUserAccessToken

test*()
  └── Requete avec 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->testUserAccessToken

tearDown()
  └── connection->rollBack()
```

## Assertions sur les collections (API Platform JSON-LD)

API Platform enveloppe toujours les collections dans un objet JSON-LD :

```json
{
    "member": [ {...}, {...} ],
    "totalItems": 2,
    "@context": "...",
    "@id": "...",
    "@type": "hydra:Collection"
}
```

**Toujours utiliser `$data['member']`** pour les assertions de count et d'indexage :

```php
// ❌ FAUX — assertCount sur les 5 cles de l'objet JSON-LD, pas sur les items
$data = json_decode($response->getContent(), true);
$this->assertCount(3, $data);        // retourne toujours 5 (nombre de cles)
$this->assertSame('To Do', $data[0]['name']); // erreur : $data[0] n'existe pas

// ✅ CORRECT — assertCount sur $data['member']
$data = json_decode($response->getContent(), true);
$this->assertCount(3, $data['member']);
$this->assertSame('To Do', $data['member'][0]['name']);

// ✅ CORRECT — inline
$this->assertCount(1, json_decode($response->getContent(), true)['member']);
```

Les reponses **single item** (GET /{id}, POST → 201) sont des objets plats — pas de `member`.

## Pieges sur les Resource DTOs

**Champ optionnel a la creation : toujours `?int = null`, pas `int = 0`**

```php
// ❌ FAUX — 0 est traite comme valeur explicite par isset() && is_int()
public int $position = 0;

// Dans le Processor :
$position = isset($data->position) && is_int($data->position) ? $data->position : null;
// → avec position=0 (defaut), $position vaut 0, pas null → casse l'auto-calcul

// ✅ CORRECT — null indique "non fourni"
public ?int $position = null;
// → isset(null) retourne false → $position = null → auto-calcul declenche
```

## Checklist test E2E

- [ ] `use OAuth2IntegrationTestTrait` pour le setup authentifie
- [ ] `#[Group('e2e')]`
- [ ] Email/username unique par test (`uniqid()`)
- [ ] Tester le cas nominal avec token valide
- [ ] Les tests auth (401) sont dans `AuthenticationTest`, pas dupliques
- [ ] Verifier la structure JSON-LD complete
- [ ] Verifier que le password n'est jamais expose
- [ ] Pour les mutations : verifier l'etat apres (GET apres PUT, 401 apres DELETE)
- [ ] Tester la validation domaine (422 avec violations)
- [ ] Transaction rollback dans `tearDown()`
- [ ] **Collections** : utiliser `$data['member']` pour assertCount/indexage

## Commandes

```bash
# Tous les tests E2E
php bin/phpunit --testsuite E2E

# Tests securite uniquement
php bin/phpunit --group security

# Un fichier specifique
php bin/phpunit tests/E2E/User/GetUserProfileTest.php

# Tous les tests (unit + integration + E2E)
php bin/phpunit --testsuite all
```
