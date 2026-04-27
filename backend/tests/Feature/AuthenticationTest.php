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

    public function test_refresh_token_returns_new_access_token()
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

        // Get tokens
        $tokens = $this->getTokenPair($userData['email'], $userData['password']);
        $this->assertNotEmpty($tokens['access_token']);
        $this->assertNotEmpty($tokens['refresh_token']);

        // Sleep to ensure new token has different timestamp
        sleep(1);

        // Use refresh token to get new access token
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(200, $response);
        $this->assertArrayHasKey('access_token', $response['body']);
        $this->assertNotEquals($tokens['access_token'], $response['body']['access_token']);
    }

    public function test_invalid_refresh_token_returns_400()
    {
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'invalid_refresh_token_value',
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_revoke_refresh_token_returns_200()
    {
        $userData = [
            'email'      => 'test-user-' . uniqid() . '@example.com',
            'password'   => 'ValidPassword123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];

        $registerResponse = $this->apiPost('/auth/register', $userData);
        $this->assertStatus(201, $registerResponse);

        $refreshToken = $this->getRefreshToken($userData['email'], $userData['password']);

        // Revoke the refresh token
        $response = $this->apiPostFormEncoded('/auth/revoke', [
            'token'             => $refreshToken,
            'token_type_hint'   => 'refresh_token',
        ]);

        $this->assertStatus(200, $response);
    }

    public function test_revoked_refresh_token_cannot_be_reused()
    {
        $userData = [
            'email'      => 'test-user-' . uniqid() . '@example.com',
            'password'   => 'ValidPassword123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];

        $registerResponse = $this->apiPost('/auth/register', $userData);
        $this->assertStatus(201, $registerResponse);

        $tokens = $this->getTokenPair($userData['email'], $userData['password']);

        // Revoke the refresh token
        $revokeResponse = $this->apiPostFormEncoded('/auth/revoke', [
            'token'           => $tokens['refresh_token'],
            'token_type_hint' => 'refresh_token',
        ]);
        $this->assertStatus(200, $revokeResponse);

        // Try to use revoked refresh token
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_revoke_access_token_returns_200()
    {
        $userData = [
            'email'      => 'test-user-' . uniqid() . '@example.com',
            'password'   => 'ValidPassword123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];

        $registerResponse = $this->apiPost('/auth/register', $userData);
        $this->assertStatus(201, $registerResponse);

        $accessToken = $this->getAccessToken($userData['email'], $userData['password']);

        // Revoke the access token
        $response = $this->apiPostFormEncoded('/auth/revoke', [
            'token'           => $accessToken,
            'token_type_hint' => 'access_token',
        ]);

        $this->assertStatus(200, $response);
    }

    public function test_rotated_refresh_token_cannot_be_reused()
    {
        $userData = [
            'email'      => 'test-user-' . uniqid() . '@example.com',
            'password'   => 'ValidPassword123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];

        $registerResponse = $this->apiPost('/auth/register', $userData);
        $this->assertStatus(201, $registerResponse);

        // Get initial tokens
        $tokens = $this->getTokenPair($userData['email'], $userData['password']);
        $oldRefreshToken = $tokens['refresh_token'];

        // Sleep to ensure new token has different timestamp
        sleep(1);

        // Use refresh token to get new tokens (rotation occurs here)
        $refreshResponse = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $oldRefreshToken,
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(200, $refreshResponse);
        $this->assertNotEmpty($refreshResponse['body']['refresh_token']);
        $this->assertNotEquals($oldRefreshToken, $refreshResponse['body']['refresh_token']);

        // Try to reuse the old (rotated) refresh token
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $oldRefreshToken,
            'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
            'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
            'scope'         => 'profile',
        ]);

        $this->assertStatus(400, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }
}
