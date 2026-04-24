<?php

namespace App\Services;

use App\DTO\RegisterUserDTO;
use App\Exceptions\UserAlreadyExistsException;
use App\Repositories\Contracts\UserRepositoryContract;
use Ramsey\Uuid\Uuid;

class UserRegistrationService
{
    public function __construct(
        private UserRepositoryContract $repository
    ) {}

    public function register(RegisterUserDTO $dto): string
    {
        $errors = $dto->validate();
        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors));
        }

        if ($this->repository->existsByEmail($dto->email)) {
            throw new UserAlreadyExistsException($dto->email);
        }

        $userId = Uuid::uuid4()->toString();
        $this->repository->save([
            'id'             => $userId,
            'email'          => $dto->email,
            'password_hash'  => password_hash($dto->password, PASSWORD_BCRYPT),
            'first_name'     => $dto->first_name,
            'last_name'      => $dto->last_name,
        ]);

        return $userId;
    }
}
