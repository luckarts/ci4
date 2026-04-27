<?php

namespace App\Modules\User\Services;

use App\Modules\Shared\Contracts\UserRepositoryContract;
use App\Modules\User\Exceptions\UserNotFoundException;

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
