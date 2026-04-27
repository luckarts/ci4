<?php

namespace Tests\Integration;

use App\Modules\Auth\OAuth2\Repositories\AccessTokenRepository;
use App\Modules\Auth\OAuth2\Repositories\RefreshTokenRepository;
use App\Modules\Auth\OAuth2\Repositories\UserOAuth2Repository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use Ramsey\Uuid\Uuid;

class OAuthRepositoryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private string $testUserId;
    private string $testEmail;
    private string $testPassword = 'correct-password-123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->testUserId = Uuid::uuid4()->toString();
        $this->testEmail  = 'oauth-test-' . uniqid() . '@test.com';

        Database::connect()->table('users')->insert([
            'id'            => $this->testUserId,
            'email'         => $this->testEmail,
            'password_hash' => password_hash($this->testPassword, PASSWORD_BCRYPT),
            'first_name'    => 'OAuth',
            'last_name'     => 'Test',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    public function test_valid_credentials_return_user_entity(): void
    {
        $repo = new UserOAuth2Repository(Database::connect());

        $entity = $repo->getUserEntityByUserCredentials(
            $this->testEmail,
            $this->testPassword,
            'password',
            $this->mockClient()
        );

        $this->assertNotNull($entity);
        $this->assertEquals($this->testUserId, $entity->getIdentifier());
    }

    public function test_invalid_password_returns_false(): void
    {
        $repo = new UserOAuth2Repository(Database::connect());

        $entity = $repo->getUserEntityByUserCredentials(
            $this->testEmail,
            'wrong-password',
            'password',
            $this->mockClient()
        );

        $this->assertNull($entity);
    }

    public function test_access_token_revocation_persists(): void
    {
        $db      = Database::connect();
        $tokenId = bin2hex(random_bytes(32));

        $db->table('oauth_access_tokens')->insert([
            'id'         => $tokenId,
            'user_id'    => $this->testUserId,
            'client_id'  => 'app_client',
            'scopes'     => 'profile',
            'revoked'    => false,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $repo = new AccessTokenRepository($db);

        $this->assertFalse($repo->isAccessTokenRevoked($tokenId));

        $repo->revokeAccessToken($tokenId);

        $this->assertTrue($repo->isAccessTokenRevoked($tokenId));
    }

    public function test_refresh_token_revocation_persists(): void
    {
        $db             = Database::connect();
        $accessTokenId  = bin2hex(random_bytes(32));
        $refreshTokenId = bin2hex(random_bytes(32));

        $db->table('oauth_access_tokens')->insert([
            'id'         => $accessTokenId,
            'user_id'    => $this->testUserId,
            'client_id'  => 'app_client',
            'scopes'     => 'profile',
            'revoked'    => false,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $db->table('oauth_refresh_tokens')->insert([
            'id'              => $refreshTokenId,
            'access_token_id' => $accessTokenId,
            'revoked'         => false,
            'expires_at'      => date('Y-m-d H:i:s', time() + 86400),
        ]);

        $repo = new RefreshTokenRepository($db);

        $this->assertFalse($repo->isRefreshTokenRevoked($refreshTokenId));

        $repo->revokeRefreshToken($refreshTokenId);

        $this->assertTrue($repo->isRefreshTokenRevoked($refreshTokenId));
    }

    // ---------------------------------------------------------------------------

    private function mockClient(): ClientEntityInterface
    {
        return $this->createMock(ClientEntityInterface::class);
    }
}
