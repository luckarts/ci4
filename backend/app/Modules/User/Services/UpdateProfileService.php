<?php

namespace App\Modules\User\Services;

use App\Modules\Shared\Contracts\UserRepositoryContract;
use App\Modules\User\DTO\UpdateProfileDTO;
use App\Modules\User\Exceptions\UserNotFoundException;

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
