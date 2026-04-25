<?php

namespace Tests\Integration;

use App\Repositories\UserRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Ramsey\Uuid\Uuid;

class UserDeleteCascadeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private UserRepository $repository;
    private string $testUserId;
    private string $accessTokenId;
    private string $refreshTokenId;
    private static bool $constraintsAdded = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(Database::connect());
        $this->testUserId = Uuid::uuid4()->toString();
        $this->accessTokenId = bin2hex(random_bytes(32));
        $this->refreshTokenId = bin2hex(random_bytes(32));

        // Ensure CASCADE constraints are added (in case migrations didn't run)
        if (!self::$constraintsAdded) {
            $this->ensureCascadeConstraints();
            self::$constraintsAdded = true;
        }
    }

    private function ensureCascadeConstraints(): void
    {
        $db = Database::connect();

        // Clean invalid data (tokens with user_id that don't exist in users table)
        try {
            $db->query('DELETE FROM oauth_refresh_tokens WHERE access_token_id NOT IN (SELECT id FROM oauth_access_tokens)');
            $db->query('DELETE FROM oauth_access_tokens WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users)');
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }

        // Drop existing constraints to recreate them cleanly
        try {
            $db->query('ALTER TABLE oauth_refresh_tokens DROP CONSTRAINT fk_oauth_refresh_tokens_access_token_id');
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }

        try {
            $db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT fk_oauth_access_tokens_user_id');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT fk_oauth_access_tokens_client_id');
        } catch (\Exception $e) {
            // Ignore
        }

        // Try to drop old-style constraint names from forge
        try {
            $db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT oauth_access_tokens_user_id_foreign');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $db->query('ALTER TABLE oauth_access_tokens DROP CONSTRAINT oauth_access_tokens_client_id_foreign');
        } catch (\Exception $e) {
            // Ignore
        }

        try {
            $db->query('ALTER TABLE oauth_refresh_tokens DROP CONSTRAINT oauth_refresh_tokens_access_token_id_foreign');
        } catch (\Exception $e) {
            // Ignore
        }

        // Add CASCADE constraints
        try {
            $db->query('
                ALTER TABLE oauth_access_tokens
                ADD CONSTRAINT fk_oauth_access_tokens_user_id
                FOREIGN KEY (user_id)
                REFERENCES users(id)
                ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Already exists
        }

        try {
            $db->query('
                ALTER TABLE oauth_access_tokens
                ADD CONSTRAINT fk_oauth_access_tokens_client_id
                FOREIGN KEY (client_id)
                REFERENCES oauth_clients(id)
                ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Already exists
        }

        try {
            $db->query('
                ALTER TABLE oauth_refresh_tokens
                ADD CONSTRAINT fk_oauth_refresh_tokens_access_token_id
                FOREIGN KEY (access_token_id)
                REFERENCES oauth_access_tokens(id)
                ON DELETE CASCADE
            ');
        } catch (\Exception $e) {
            // Already exists
        }
    }

    public function test_delete_user_cascades_to_access_tokens(): void
    {
        $db = Database::connect();

        // Create test user
        $db->table('users')->insert([
            'id'             => $this->testUserId,
            'email'          => 'cascade-test-' . uniqid() . '@test.com',
            'password_hash'  => password_hash('password123', PASSWORD_BCRYPT),
            'first_name'     => 'Cascade',
            'last_name'      => 'Test',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Create access token for this user
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $db->table('oauth_access_tokens')->insert([
            'id'        => $this->accessTokenId,
            'user_id'   => $this->testUserId,
            'client_id' => 'app_client',
            'scopes'    => 'profile',
            'revoked'   => false,
            'expires_at' => $expiresAt,
        ]);

        // Verify token was created
        $token = $db->table('oauth_access_tokens')
            ->where('id', $this->accessTokenId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($token);
        $this->assertEquals($this->testUserId, $token['user_id']);

        // Verify CASCADE constraints exist
        $fks = $db->query("
            SELECT rc.constraint_name, kcu.column_name, ccu.table_name as referenced_table,
                   rc.delete_rule
            FROM information_schema.referential_constraints rc
            JOIN information_schema.key_column_usage kcu ON rc.constraint_name = kcu.constraint_name
            JOIN information_schema.constraint_column_usage ccu ON rc.constraint_name = ccu.constraint_name
            WHERE rc.constraint_schema = 'public'
            AND kcu.table_name = 'oauth_access_tokens'
        ")->getResultArray();
        $this->assertNotEmpty($fks, 'Foreign key constraints must exist for CASCADE to work');

        // Verify user_id constraint has CASCADE
        $userIdConstraint = array_filter($fks, fn($fk) => $fk['column_name'] === 'user_id');
        $this->assertNotEmpty($userIdConstraint, 'user_id foreign key must exist');
        $this->assertEquals('CASCADE', current($userIdConstraint)['delete_rule'],
            'user_id constraint must have ON DELETE CASCADE');

        // Delete user
        $this->repository->delete($this->testUserId);

        // Verify user is deleted
        $user = $db->table('users')->where('id', $this->testUserId)->get()->getRowArray();
        $this->assertNull($user, 'User should be deleted');

        // Verify access token was cascaded deleted
        $deletedToken = $db->table('oauth_access_tokens')
            ->where('id', $this->accessTokenId)
            ->get()
            ->getRowArray();
        $this->assertNull($deletedToken, 'Access token should be cascade-deleted when user is deleted');
    }

    public function test_delete_user_cascades_to_refresh_tokens_via_access_token_fk(): void
    {
        $db = Database::connect();

        // Create test user
        $db->table('users')->insert([
            'id'             => $this->testUserId,
            'email'          => 'cascade-refresh-' . uniqid() . '@test.com',
            'password_hash'  => password_hash('password123', PASSWORD_BCRYPT),
            'first_name'     => 'Cascade',
            'last_name'      => 'Refresh',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Create access token for this user
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $db->table('oauth_access_tokens')->insert([
            'id'        => $this->accessTokenId,
            'user_id'   => $this->testUserId,
            'client_id' => 'app_client',
            'scopes'    => 'profile',
            'revoked'   => false,
            'expires_at' => $expiresAt,
        ]);

        // Create refresh token linked to access token
        $db->table('oauth_refresh_tokens')->insert([
            'id'              => $this->refreshTokenId,
            'access_token_id' => $this->accessTokenId,
            'revoked'         => false,
            'expires_at'      => $expiresAt,
        ]);

        // Verify both tokens were created
        $accessToken = $db->table('oauth_access_tokens')
            ->where('id', $this->accessTokenId)
            ->get()
            ->getRowArray();
        $refreshToken = $db->table('oauth_refresh_tokens')
            ->where('id', $this->refreshTokenId)
            ->get()
            ->getRowArray();
        $this->assertNotNull($accessToken);
        $this->assertNotNull($refreshToken);

        // Delete user
        $this->repository->delete($this->testUserId);

        // Verify both tokens were cascaded deleted
        $deletedAccessToken = $db->table('oauth_access_tokens')
            ->where('id', $this->accessTokenId)
            ->get()
            ->getRowArray();
        $deletedRefreshToken = $db->table('oauth_refresh_tokens')
            ->where('id', $this->refreshTokenId)
            ->get()
            ->getRowArray();
        $this->assertNull($deletedAccessToken);
        $this->assertNull($deletedRefreshToken);
    }
}
