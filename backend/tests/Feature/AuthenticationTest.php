<?php

namespace Tests\Feature;

/**
 * Tests pour les endpoints POST /auth/register et POST /auth/token
 */
class AuthenticationTest extends ApiTestCase
{

    public function test_login_happy_path_returns_200_with_token()
    {
        $userData = [
            'email'      => 'test-user-' . uniqid() . '@example.com',
            'password'   => 'ValidPassword123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];

        // Register user first
        $registerResponse = $this->apiPost('/auth/register', $userData);
        $this->assertStatus(201, $registerResponse);

        // Test token endpoint
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'password',
            'username'      => $userData['email'],
            'password'      => $userData['password'],
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(200, $response);
        $this->assertArrayHasKey('access_token', $response['body']);
        $this->assertArrayHasKey('token_type', $response['body']);
        $this->assertArrayHasKey('expires_in', $response['body']);
        $this->assertArrayHasKey('refresh_token', $response['body']);
        $this->assertEquals('Bearer', $response['body']['token_type']);
    }

    public function test_login_invalid_credentials_returns_401()
    {
        $userData = [
            'email'      => 'test-user-' . uniqid() . '@example.com',
            'password'   => 'ValidPassword123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];

        // Register user
        $registerResponse = $this->apiPost('/auth/register', $userData);
        $this->assertStatus(201, $registerResponse);

        // Test with invalid password
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'password',
            'username'      => $userData['email'],
            'password'      => 'WrongPassword123',
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertArrayHasKey('error_description', $response['body']);
    }

    public function test_login_nonexistent_user_returns_400()
    {
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'password',
            'username'      => 'nonexistent-' . uniqid() . '@example.com',
            'password'      => 'SomePassword123',
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertArrayHasKey('error_description', $response['body']);
    }

    public function test_login_missing_grant_type_returns_400()
    {
        $response = $this->apiPostFormEncoded('/auth/token', [
            'username'      => 'test@example.com',
            'password'      => 'SomePassword123',
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('error', $response['body']);
        $this->assertArrayHasKey('error_description', $response['body']);
    }
}
