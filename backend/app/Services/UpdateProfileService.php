<?php

namespace App\Services;

use App\DTO\UpdateProfileDTO;
use App\Exceptions\UserNotFoundException;
use App\Repositories\Contracts\UserRepositoryContract;

class UpdateProfileService
{
    public function __construct(
        private UserRepositoryContract $repository
    ) {}

    public function updateProfile(string $userId, UpdateProfileDTO $dto): array
    {
        $user = $this->repository->findById($userId);
        if ($user === null) {
            throw new UserNotFoundException($userId);
        }

        $updated = $this->repository->update($userId, [
            'first_name' => $dto->first_name,
            'last_name'  => $dto->last_name,
        ]);

        return $updated;
    }
}
