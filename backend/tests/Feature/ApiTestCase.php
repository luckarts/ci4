<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;

abstract class ApiTestCase extends CIUnitTestCase
{
    protected string $baseUrl = 'http://localhost:8080';

    protected function setUp(): void
    {
        parent::setUp();
        \App\Libraries\AuthContext::reset();
    }

    protected function apiPost(string $uri, array $data = [], array $headers = []): array
    {
        return $this->apiRequest('POST', $uri, $data, $headers);
    }

    protected function apiGet(string $uri, array $headers = []): array
    {
        return $this->apiRequest('GET', $uri, [], $headers);
    }

    protected function apiPut(string $uri, array $data = [], array $headers = []): array
    {
        return $this->apiRequest('PUT', $uri, $data, $headers);
    }

    protected function apiDelete(string $uri, array $headers = []): array
    {
        return $this->apiRequest('DELETE', $uri, [], $headers);
    }

    protected function apiPostFormEncoded(string $uri, array $data = []): array
    {
        $url = $this->baseUrl . $uri;
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
        ]);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        return [
            'status' => $statusCode,
            'body'   => $response ? json_decode($response, true) : null,
            'raw'    => $response,
        ];
    }

    private function apiRequest(string $method, string $uri, array $data = [], array $headers = []): array
    {
        $url = $this->baseUrl . $uri;
        $curl = curl_init($url);

        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $allHeaders,
            CURLOPT_TIMEOUT        => 10,
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \RuntimeException("HTTP request failed: {$error}");
        }

        return [
            'status' => $statusCode,
            'body'   => $response ? json_decode($response, true) : null,
            'raw'    => $response,
        ];
    }

    protected function createTestUser(array $override = []): array
    {
        $defaults = [
            'email'      => 'user-' . uniqid() . '@test.local',
            'password'   => 'password123',
            'first_name' => 'Test',
            'last_name'  => 'User',
        ];
        $userData = array_merge($defaults, $override);

        $response = $this->apiPost('/auth/register', $userData);
        if ($response['status'] !== 201) {
            throw new \RuntimeException("Failed to create test user: " . json_encode($response));
        }

        return $userData;
    }

    protected function getAccessToken(string $email, string $password): string
    {
        $tokens = $this->getTokenPair($email, $password);
        return $tokens['access_token'];
    }

    protected function getRefreshToken(string $email, string $password): string
    {
        $tokens = $this->getTokenPair($email, $password);
        return $tokens['refresh_token'];
    }

    protected function getTokenPair(string $email, string $password): array
    {
        $curl = curl_init($this->baseUrl . '/auth/token');

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'password',
                'username'      => $email,
                'password'      => $password,
                'client_id'     => getenv('OAUTH_CLIENT_ID') ?: 'app_client',
                'client_secret' => getenv('OAUTH_CLIENT_SECRET') ?: 'secret',
                'scope'         => 'profile',
            ]),
        ]);

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($statusCode !== 200) {
            throw new \RuntimeException("Failed to get tokens: " . $response);
        }

        $decoded = json_decode($response, true);
        return [
            'access_token' => $decoded['access_token'] ?? null,
            'refresh_token' => $decoded['refresh_token'] ?? null,
        ];
    }

    protected function assertStatus(int $expected, array $response): void
    {
        $this->assertEquals($expected, $response['status'],
            "Expected status {$expected}, got {$response['status']}");
    }
}
