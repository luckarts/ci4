<?php

namespace Tests\E2E;

use Tests\AbstractCiTestCase;

class BootstrapTest extends AbstractCiTestCase
{
    public function testDatabaseConnectsSuccessfully(): void
    {
        $result = self::$db->query('SELECT 1');
        $this->assertNotNull($result);
    }

    public function testUsersTableExists(): void
    {
        $tableExists = self::$db->table_exists('users');
        $this->assertTrue($tableExists, 'Users table should exist after migrations');
    }

    public function testOAuthTablesExist(): void
    {
        $this->assertTrue(self::$db->table_exists('oauth2_clients'));
        $this->assertTrue(self::$db->table_exists('oauth2_access_tokens'));
        $this->assertTrue(self::$db->table_exists('oauth2_refresh_tokens'));
        $this->assertTrue(self::$db->table_exists('oauth2_auth_codes'));
        $this->assertTrue(self::$db->table_exists('oauth2_scopes'));
    }

    public function testCanCreateAndRetrieveUser(): void
    {
        $user = $this->createUser([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $retrieved = $this->getUserFromDb('test@example.com');

        $this->assertNotNull($retrieved);
        $this->assertEquals('test@example.com', $retrieved['email']);
        $this->assertEquals('John', $retrieved['first_name']);
        $this->assertEquals('Doe', $retrieved['last_name']);
    }

    public function testDatabaseIsClearedBetweenTests(): void
    {
        self::$db->insert('users', [
            'email' => 'temp@example.com',
            'password' => password_hash('test', PASSWORD_BCRYPT),
            'first_name' => 'Temp',
            'last_name' => 'User',
        ]);

        $this->assertNotNull($this->getUserFromDb('temp@example.com'));
    }

    public function testDatabaseIsCleanedAfterPreviousTest(): void
    {
        // This test verifies that the database was cleaned after the previous test
        $user = $this->getUserFromDb('temp@example.com');
        $this->assertNull($user, 'Database should be cleaned between tests');
    }
}
