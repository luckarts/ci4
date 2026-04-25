<?php

namespace Tests\Unit;

use App\Services\DeleteUserService;
use App\Repositories\Contracts\UserRepositoryContract;
use App\Exceptions\UserNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;

class DeleteUserServiceTest extends CIUnitTestCase
{
    private DeleteUserService $service;
    private UserRepositoryContract $mockRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRepository = $this->createMock(UserRepositoryContract::class);
        $this->service = new DeleteUserService($this->mockRepository);
    }

    public function test_delete_user_calls_repository_delete_when_user_exists(): void
    {
        $userId = '123e4567-e89b-12d3-a456-426614174000';
        $user = [
            'id' => $userId,
            'email' => 'user@test.local',
            'first_name' => 'Test',
            'last_name' => 'User',
            'password_hash' => 'hashed_password',
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn($user);

        $this->mockRepository
            ->expects($this->once())
            ->method('delete')
            ->with($userId);

        $result = $this->service->deleteUser($userId);

        $this->assertArrayNotHasKey('password_hash', $result);
        $this->assertEquals($userId, $result['id']);
        $this->assertEquals('user@test.local', $result['email']);
    }

    public function test_delete_user_throws_exception_when_user_not_found(): void
    {
        $userId = '123e4567-e89b-12d3-a456-426614174000';

        $this->mockRepository
            ->expects($this->once())
            ->method('findById')
            ->with($userId)
            ->willReturn(null);

        $this->mockRepository
            ->expects($this->never())
            ->method('delete');

        $this->expectException(UserNotFoundException::class);

        $this->service->deleteUser($userId);
    }
}
