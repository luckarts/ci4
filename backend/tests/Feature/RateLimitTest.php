<?php

namespace Tests\Feature;

/**
 * @group rate
 * Rate limiting tests require real cache to function.
 * Run with: ./vendor/bin/phpunit --group=rate
 */
class RateLimitTest extends ApiTestCase
{
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
        // Use unique IP per test run to avoid cache collisions
        $testIp = '192.168.' . random_int(1, 254) . '.' . random_int(1, 254);

        // Make 5 valid requests (within capacity of 5)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->apiPostFormEncoded('/auth/token', [
                'grant_type'    => 'password',
                'username'      => $userData['email'],
                'password'      => $userData['password'],
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'scope'         => 'profile',
            ], [
                'X-Rate-Limit-Capacity: 5',
                'X-Test-Client-IP: ' . $testIp,
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
        ], [
            'X-Rate-Limit-Capacity: 5',
            'X-Test-Client-IP: ' . $testIp,
        ]);

        $this->assertStatus(429, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }
}
