<?php

namespace Tests\Feature;

/**
 * @group rate
 * Rate limiting tests require real cache to function.
 * Run with: ./vendor/bin/phpunit --group=rate
 */
class RateLimitTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Enable real cache for rate limiting tests instead of dummy
        $cacheConfig = new \Config\Cache();
        $cacheConfig->handler = 'file';
    }

    public function test_rate_limit_returns_429_after_capacity_exceeded()
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

        $clientId = getenv('OAUTH_CLIENT_ID') ?: 'app_client';
        $clientSecret = getenv('OAUTH_CLIENT_SECRET') ?: 'secret';

        // Make 5 valid requests (within capacity)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->apiPostFormEncoded('/auth/token', [
                'grant_type'    => 'password',
                'username'      => $userData['email'],
                'password'      => $userData['password'],
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'profile',
            ]);

            $this->assertStatus(200, $response);
            $this->assertArrayHasKey('access_token', $response['body']);
        }

        // 6th request should be rate limited
        $response = $this->apiPostFormEncoded('/auth/token', [
            'grant_type'    => 'password',
            'username'      => $userData['email'],
            'password'      => $userData['password'],
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'profile',
        ]);

        $this->assertStatus(429, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }
}
