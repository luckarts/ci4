<?php

namespace Tests\Feature;

class UserProfileTest extends ApiTestCase
{
    // GET /users/{id} tests

    public function test_get_own_profile_returns_200_with_user_data()
    {
        $userData = $this->createTestUser([
            'email'      => 'getprofile-' . uniqid() . '@test.local',
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $token = $this->getAccessToken($userData['email'], $userData['password']);
        $userId = $this->getUserIdFromEmail($userData['email']);

        $response = $this->apiGet("/users/{$userId}", [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(200, $response);
        $this->assertArrayHasKey('email', $response['body']);
        $this->assertArrayHasKey('first_name', $response['body']);
        $this->assertArrayHasKey('last_name', $response['body']);
        $this->assertArrayNotHasKey('password_hash', $response['body']);
        $this->assertEquals($userData['email'], $response['body']['email']);
        $this->assertEquals('John', $response['body']['first_name']);
        $this->assertEquals('Doe', $response['body']['last_name']);
    }

    public function test_get_profile_without_token_returns_401()
    {
        $userData = $this->createTestUser();
        $userId = $this->getUserIdFromEmail($userData['email']);

        $response = $this->apiGet("/users/{$userId}");

        $this->assertStatus(401, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_get_other_user_profile_returns_403()
    {
        $user1 = $this->createTestUser([
            'email' => 'user1-' . uniqid() . '@test.local',
        ]);
        $user2 = $this->createTestUser([
            'email' => 'user2-' . uniqid() . '@test.local',
        ]);

        $user1Id = $this->getUserIdFromEmail($user1['email']);
        $user2Id = $this->getUserIdFromEmail($user2['email']);
        $user2Token = $this->getAccessToken($user2['email'], $user2['password']);

        $response = $this->apiGet("/users/{$user1Id}", [
            'Authorization: Bearer ' . $user2Token,
        ]);

        $this->assertStatus(403, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_get_nonexistent_user_returns_404()
    {
        $userData = $this->createTestUser();
        $token = $this->getAccessToken($userData['email'], $userData['password']);
        $fakeId = 'fake-id-' . uniqid();

        $response = $this->apiGet("/users/{$fakeId}", [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(404, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    // PUT /users/{id}/profile tests

    public function test_update_profile_happy_path_returns_200_with_updated_data()
    {
        $userData = $this->createTestUser([
            'email'      => 'update-' . uniqid() . '@test.local',
            'first_name' => 'Original',
            'last_name'  => 'Name',
        ]);

        $userId = $this->getUserIdFromEmail($userData['email']);
        $token = $this->getAccessToken($userData['email'], $userData['password']);

        $response = $this->apiPut("/users/{$userId}/profile", [
            'first_name' => 'Updated',
            'last_name'  => 'Profile',
        ], [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(200, $response);
        $this->assertArrayHasKey('first_name', $response['body']);
        $this->assertArrayHasKey('last_name', $response['body']);
        $this->assertArrayNotHasKey('password_hash', $response['body']);
        $this->assertEquals('Updated', $response['body']['first_name']);
        $this->assertEquals('Profile', $response['body']['last_name']);
    }

    public function test_update_profile_without_token_returns_401()
    {
        $userData = $this->createTestUser();
        $userId = $this->getUserIdFromEmail($userData['email']);

        $response = $this->apiPut("/users/{$userId}/profile", [
            'first_name' => 'Updated',
            'last_name'  => 'Profile',
        ]);

        $this->assertStatus(401, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_update_other_user_profile_returns_403()
    {
        $user1 = $this->createTestUser([
            'email' => 'user1-update-' . uniqid() . '@test.local',
        ]);
        $user2 = $this->createTestUser([
            'email' => 'user2-update-' . uniqid() . '@test.local',
        ]);

        $user1Id = $this->getUserIdFromEmail($user1['email']);
        $user2Token = $this->getAccessToken($user2['email'], $user2['password']);

        $response = $this->apiPut("/users/{$user1Id}/profile", [
            'first_name' => 'Hacker',
            'last_name'  => 'Attempt',
        ], [
            'Authorization: Bearer ' . $user2Token,
        ]);

        $this->assertStatus(403, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_update_profile_with_empty_first_name_returns_422()
    {
        $userData = $this->createTestUser();
        $userId = $this->getUserIdFromEmail($userData['email']);
        $token = $this->getAccessToken($userData['email'], $userData['password']);

        $response = $this->apiPut("/users/{$userId}/profile", [
            'first_name' => '',
            'last_name'  => 'Valid',
        ], [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(422, $response);
        $this->assertArrayHasKey('errors', $response['body']);
        $this->assertArrayHasKey('first_name', $response['body']['errors']);
    }

    public function test_update_profile_with_empty_last_name_returns_422()
    {
        $userData = $this->createTestUser();
        $userId = $this->getUserIdFromEmail($userData['email']);
        $token = $this->getAccessToken($userData['email'], $userData['password']);

        $response = $this->apiPut("/users/{$userId}/profile", [
            'first_name' => 'Valid',
            'last_name'  => '',
        ], [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(422, $response);
        $this->assertArrayHasKey('errors', $response['body']);
        $this->assertArrayHasKey('last_name', $response['body']['errors']);
    }

    // DELETE /users/{id} tests

    public function test_delete_own_user_returns_200_with_user_data()
    {
        $userData = $this->createTestUser([
            'email'      => 'delete-' . uniqid() . '@test.local',
            'first_name' => 'To',
            'last_name'  => 'Delete',
        ]);

        $userId = $this->getUserIdFromEmail($userData['email']);
        $token = $this->getAccessToken($userData['email'], $userData['password']);

        $response = $this->apiDelete("/users/{$userId}", [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(200, $response);
        $this->assertArrayHasKey('email', $response['body']);
        $this->assertArrayHasKey('id', $response['body']);
        $this->assertArrayNotHasKey('password_hash', $response['body']);
        $this->assertEquals($userData['email'], $response['body']['email']);
    }

    public function test_delete_user_without_token_returns_401()
    {
        $userData = $this->createTestUser();
        $userId = $this->getUserIdFromEmail($userData['email']);

        $response = $this->apiDelete("/users/{$userId}");

        $this->assertStatus(401, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_delete_other_user_returns_403()
    {
        $user1 = $this->createTestUser([
            'email' => 'user1-delete-' . uniqid() . '@test.local',
        ]);
        $user2 = $this->createTestUser([
            'email' => 'user2-delete-' . uniqid() . '@test.local',
        ]);

        $user1Id = $this->getUserIdFromEmail($user1['email']);
        $user2Token = $this->getAccessToken($user2['email'], $user2['password']);

        $response = $this->apiDelete("/users/{$user1Id}", [
            'Authorization: Bearer ' . $user2Token,
        ]);

        $this->assertStatus(403, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    public function test_delete_nonexistent_user_returns_404()
    {
        $userData = $this->createTestUser();
        $token = $this->getAccessToken($userData['email'], $userData['password']);
        $fakeId = 'fake-id-' . uniqid();

        $response = $this->apiDelete("/users/{$fakeId}", [
            'Authorization: Bearer ' . $token,
        ]);

        $this->assertStatus(404, $response);
        $this->assertArrayHasKey('error', $response['body']);
    }

    // Helper methods

    private function getUserIdFromEmail(string $email): string
    {
        $db = \Config\Database::connect();
        $result = $db->table('users')
            ->select('id')
            ->where('email', $email)
            ->get()
            ->getResultArray();

        if (empty($result)) {
            throw new \RuntimeException("User with email {$email} not found");
        }

        return $result[0]['id'];
    }
}
