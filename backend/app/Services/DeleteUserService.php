<?php

namespace App\Services;

use App\Exceptions\UserNotFoundException;
use App\Repositories\Contracts\UserRepositoryContract;

class DeleteUserService
{
    public function __construct(
        private UserRepositoryContract $repository
    ) {}

    public function deleteUser(string $userId): array
    {
        $user = $this->repository->findById($userId);
        if ($user === null) {
            throw new UserNotFoundException($userId);
        }

        $this->repository->delete($userId);

        unset($user['password_hash']);
        return $user;
    }
}
