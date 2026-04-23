---
name: test:integration
description: "Tests d'integration PHPUnit - Assemblage DDD complet avec BDD et API Platform"
argument-hint: [register|oauth2|endpoint]
triggers:
  - test integration
  - integration test
  - tester endpoint
  - tester api
  - WebTestCase
  - database test
---

# Tests d'Integration PHPUnit

Tests qui verifient l'assemblage complet des composants DDD avec l'infrastructure reelle : API Platform, Doctrine, Symfony Messenger. Utilisent une vraie BDD avec isolation par transaction.

## Conventions du projet

- Extends `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`
- Attributs : `#[Group('dev')]`, `#[Group('integration')]`, `#[Group('oauth2')]`
- Isolation BDD par transaction (beginTransaction / rollBack dans tearDown)
- Schema recree une seule fois (`static $schemaCreated`)
- Suite PHPUnit : `--testsuite integration`

## Structure des fichiers

```
tests/
  Integration/
    Auth/
      RegisterUserIntegrationTest.php    # Flow complet register
      OAuth2TokenEndToEndTest.php        # Flow OAuth2 complet avec JWT
```

## Difference avec les tests E2E

| | Integration | E2E |
|---|-----------|-----|
| **Focus** | Assemblage composants DDD | Parcours utilisateur complet |
| **OAuth2** | Teste le flow OAuth2 lui-meme | Utilise OAuth2 comme prerequis |
| **BDD** | Schema + transaction | Schema + transaction + trait partage |
| **Group** | `#[Group('dev')]` | `#[Group('e2e')]` |

## 1. Test d'integration d'un endpoint (Register)

Pattern : HTTP Request → API Platform → Processor → Command Bus → Handler → Repository → BDD.

```php
declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('dev')]
final class RegisterUserIntegrationTest extends WebTestCase
{
    private Connection $connection;
    private EntityManagerInterface $entityManager;
    private static bool $schemaCreated = false;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->initializeDatabaseIfNeeded();
    }

    protected function tearDown(): void
    {
        // Rollback pour isolation entre tests
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }
        parent::tearDown();
    }

    // Cas nominal
    public function testSuccessfulUserRegistration(): void
    {
        $requestData = [
            'email' => 'john.doe@example.com',
            'password' => 'securePassword123!',
            'username' => 'john_doe'
        ];

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_ACCEPT' => 'application/ld+json'
            ],
            json_encode($requestData)
        );

        // Status + Content-Type
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/ld+json; charset=utf-8');

        // Structure reponse
        $content = $this->client->getResponse()->getContent();
        $responseContent = json_decode($content, true);
        $this->assertArrayHasKey('id', $responseContent);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $responseContent['id']
        );

        // Metadonnees JSON-LD
        $this->assertArrayHasKey('@context', $responseContent);
        $this->assertArrayHasKey('@id', $responseContent);
        $this->assertArrayHasKey('@type', $responseContent);
    }

    // Validation : donnees invalides
    public function testRegistrationWithInvalidData(): void
    {
        // Email invalide
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json'
        ], json_encode(['email' => 'invalid-email', 'password' => 'Password123!', 'username' => 'testuser']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Mot de passe trop court
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json'
        ], json_encode(['email' => 'valid@example.com', 'password' => '123', 'username' => 'testuser']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // Champ requis manquant
    public function testRegistrationWithoutUsername(): void
    {
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/ld+json',
            'HTTP_ACCEPT' => 'application/ld+json'
        ], json_encode(['email' => 'nousername@example.com', 'password' => 'Password123!']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // --- Infrastructure helpers ---

    private function initializeDatabaseIfNeeded(): void
    {
        $container = $this->client->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get('doctrine.dbal.default_connection');

        if (!self::$schemaCreated) {
            $this->createDatabaseSchema();
            self::$schemaCreated = true;
        }

        if (!$this->connection->isTransactionActive()) {
            $this->connection->beginTransaction();
        }
    }

    private function createDatabaseSchema(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        try {
            $schemaTool->dropDatabase();
        } catch (\Exception $e) {
        }
        $schemaTool->createSchema($metadata);
    }
}
```

## 2. Test d'integration OAuth2 (Token + JWT)

Pattern : setup client OAuth2, password grant, validation JWT structure/scopes/claims.

```php
#[Group('integration')]
#[Group('oauth2')]
final class OAuth2TokenEndToEndTest extends WebTestCase
{
    // setUp : createClient + initDB + initOAuth2Client + createTestUser

    // Cas nominal : password grant avec scopes
    public function testSuccessfulPasswordGrantWithScopes(): void
    {
        $tokenData = [
            'grant_type' => 'password',
            'client_id' => 'test_client',
            'client_secret' => 'test_secret',
            'username' => $this->testUserEmail,
            'password' => $this->testUserPassword,
            'scope' => 'read write email profile'
        ];

        $response = $this->requestOAuth2Token($tokenData);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $responseData = json_decode($response->getContent(), true);

        // Structure OAuth2
        $this->assertArrayHasKey('access_token', $responseData);
        $this->assertArrayHasKey('token_type', $responseData);
        $this->assertArrayHasKey('expires_in', $responseData);
        $this->assertSame('Bearer', $responseData['token_type']);

        // Validation JWT
        $this->validateJWTStructure($responseData['access_token']);
        $this->validateJWTScopes($responseData['access_token'], ['read', 'write', 'email', 'profile']);
        $this->validateJWTUserClaims($responseData['access_token'], $this->testUserId);
    }

    // Scopes partiels
    public function testPasswordGrantWithPartialScopes(): void { /* ... */ }

    // Erreurs : credentials invalides, client invalide, scopes invalides
    public function testPasswordGrantWithInvalidCredentials(): void { /* ... */ }
    public function testPasswordGrantWithInvalidClient(): void { /* ... */ }
    public function testPasswordGrantWithInvalidScopes(): void { /* ... */ }

    // --- Helpers JWT ---

    private function validateJWTStructure(string $token): void
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT token should have 3 parts');

        $header = json_decode(base64_decode($parts[0]), true);
        $this->assertSame('JWT', $header['typ']);
        $this->assertSame('RS256', $header['alg']);

        $payload = json_decode(base64_decode($parts[1]), true);
        $this->assertArrayHasKey('aud', $payload);
        $this->assertArrayHasKey('sub', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('scopes', $payload);
    }

    private function validateJWTScopes(string $token, array $expectedScopes): void
    {
        $payload = json_decode(base64_decode(explode('.', $token)[1]), true);

        foreach ($expectedScopes as $scope) {
            $this->assertContains($scope, $payload['scopes']);
        }
        // Pas de scopes inattendus
        foreach ($payload['scopes'] as $actual) {
            $this->assertContains($actual, $expectedScopes);
        }
    }

    // --- OAuth2 Client Setup ---

    private function initializeOAuth2TestClient(): void
    {
        $clientManager = $this->client->getContainer()
            ->get('league.oauth2_server.manager.in_memory.client');

        $testClient = new OAuth2ClientEntity('test_client', 'test_secret', 'Test Client');
        $clientManager->save($testClient);
    }
}
```

## Pattern : Isolation BDD par transaction

```
setUp()
  ├── createClient()
  ├── initializeDatabaseIfNeeded()
  │   ├── [Premier test] createDatabaseSchema() → static $schemaCreated = true
  │   └── beginTransaction()
  └── [OAuth2] initializeOAuth2TestClient()

test*()
  └── Requetes HTTP sur la BDD transactionnelle

tearDown()
  └── rollBack()  ← Annule tout, BDD propre pour le test suivant
```

## Checklist test d'integration

- [ ] Extends `WebTestCase`
- [ ] `#[Group('dev')]` ou `#[Group('integration')]`
- [ ] Transaction rollback dans `tearDown()`
- [ ] Schema cree une seule fois (`static $schemaCreated`)
- [ ] Content-Type `application/ld+json` pour API Platform
- [ ] Tester le cas nominal + les cas d'erreur (validation 422, 401, 404)
- [ ] Verifier la structure JSON-LD (`@context`, `@id`, `@type`)
- [ ] Pour OAuth2 : initialiser le client test avant les requetes token

## Commandes

```bash
# Tous les tests d'integration
php bin/phpunit --testsuite integration

# Tests OAuth2 specifiques
php bin/phpunit --group oauth2

# Un fichier specifique
php bin/phpunit tests/Integration/Auth/RegisterUserIntegrationTest.php
```
