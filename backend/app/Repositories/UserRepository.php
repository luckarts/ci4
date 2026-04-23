<?php

namespace App\Repositories;

use App\Repositories\Contracts\UserRepositoryContract;
use CodeIgniter\Database\BaseConnection;

class UserRepository implements UserRepositoryContract
{
    public function __construct(
        private BaseConnection $db
    ) {}

    public function findById(string $id): ?array
    {
        return $this->db->table('users')->where('id', $id)->get()->getRowArray() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->table('users')->where('email', $email)->get()->getRowArray() ?: null;
    }

    public function existsByEmail(string $email): bool
    {
        return (bool) $this->db->table('users')->where('email', $email)->countAllResults();
    }

    public function save(array $data): string
    {
        $this->db->table('users')->insert($data);
        return $data['id'];
    }
}
