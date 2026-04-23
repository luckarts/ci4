<?php

namespace App\Repositories\Contracts;

interface UserRepositoryContract
{
    public function findById(string $id): ?array;
    public function findByEmail(string $email): ?array;
    public function existsByEmail(string $email): bool;
    public function save(array $data): string;
}
