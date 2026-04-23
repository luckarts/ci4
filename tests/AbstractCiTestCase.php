<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

abstract class AbstractCiTestCase extends TestCase
{
    protected static $db = null;
    protected static $migration = null;
    protected string $baseUrl = 'http://localhost:8080';
    private static bool $bootstrapped = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!self::$bootstrapped) {
            self::bootCI();
            self::$bootstrapped = true;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootCI();
        self::resetDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::truncateAllTables();
    }

    protected static function bootCI(): void
    {
        if (self::$db !== null) {
            return;
        }

        // Load the test bootstrap
        require_once __DIR__ . '/bootstrap-ci.php';

        self::$db = $GLOBALS['test_db'];
        self::$migration = $GLOBALS['test_migration'];
    }

    protected static function resetDatabase(): void
    {
        self::truncateAllTables();
        self::runMigrations();
    }

    protected static function truncateAllTables(): void
    {
        if (self::$db === null) {
            return;
        }

        self::$db->query('
            DO $$ DECLARE
                r RECORD;
            BEGIN
                FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = current_schema())
                LOOP
                    EXECUTE \'DROP TABLE IF EXISTS \' || quote_ident(r.tablename) || \' CASCADE\';
                END LOOP;
            END $$;
        ');
    }

    protected static function runMigrations(): void
    {
        if (self::$migration === null) {
            return;
        }

        self::$migration->latest();
    }

    protected function request(
        string $method,
        string $uri,
        array $data = [],
        array $headers = []
    ): array {
        $url = $this->baseUrl . $uri;
        $curl = curl_init($url);

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 10,
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        return [
            'status' => $httpCode,
            'body' => $response ? json_decode($response, true) : null,
            'raw' => $response,
        ];
    }

    protected function get(string $uri, array $headers = []): array
    {
        return $this->request('GET', $uri, [], $headers);
    }

    protected function post(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    protected function put(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $uri, $data, $headers);
    }

    protected function patch(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('PATCH', $uri, $data, $headers);
    }

    protected function delete(string $uri, array $headers = []): array
    {
        return $this->request('DELETE', $uri, [], $headers);
    }

    protected function assertStatus(int $expected, array $response, string $message = ''): void
    {
        $this->assertEquals(
            $expected,
            $response['status'],
            $message ?: "Expected status {$expected}, got {$response['status']}"
        );
    }

    protected function createUser(array $data = []): array
    {
        $defaults = [
            'email' => 'user-' . uniqid() . '@test.local',
            'password' => 'password123',
            'first_name' => 'Test',
            'last_name' => 'User',
        ];

        $user = array_merge($defaults, $data);

        self::$db->insert('users', [
            'email' => $user['email'],
            'password' => password_hash($user['password'], PASSWORD_BCRYPT),
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
        ]);

        return $user;
    }

    protected function getUserFromDb(string $email): ?array
    {
        $results = self::$db->select('users', ['email' => $email]);
        return $results ? $results[0] : null;
    }
}
