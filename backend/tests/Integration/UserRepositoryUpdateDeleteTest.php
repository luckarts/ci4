<?php

namespace Tests\Integration;

use App\Repositories\UserRepository;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Ramsey\Uuid\Uuid;

class UserRepositoryUpdateDeleteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private UserRepository $repository;
    private string $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(Database::connect());
        $this->testUserId = Uuid::uuid4()->toString();
    }

    public function test_update_modifies_user_and_returns_updated_user(): void
    {
        $db = Database::connect();

        $db->table('users')->insert([
            'id'             => $this->testUserId,
            'email'          => 'update-test-' . uniqid() . '@test.com',
            'password_hash'  => password_hash('password123', PASSWORD_BCRYPT),
            'first_name'     => 'Original',
            'last_name'      => 'Name',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->repository->update($this->testUserId, [
            'first_name' => 'Updated',
            'last_name'  => 'User',
        ]);

        $this->assertIsArray($updated);
        $this->assertEquals('Updated', $updated['first_name']);
        $this->assertEquals('User', $updated['last_name']);
        $this->assertNotEmpty($updated['updated_at']);
    }

    public function test_delete_removes_user_from_database(): void
    {
        $db = Database::connect();

        $db->table('users')->insert([
            'id'             => $this->testUserId,
            'email'          => 'delete-test-' . uniqid() . '@test.com',
            'password_hash'  => password_hash('password123', PASSWORD_BCRYPT),
            'first_name'     => 'Delete',
            'last_name'      => 'Test',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->repository->delete($this->testUserId);

        $user = $this->repository->findById($this->testUserId);
        $this->assertNull($user);
    }

}
