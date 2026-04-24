<?php

namespace App\OAuth2\Repositories;

use App\OAuth2\Entities\UserEntity;
use CodeIgniter\Database\BaseConnection;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;

class UserOAuth2Repository implements UserRepositoryInterface
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        \League\OAuth2\Server\Entities\ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        $user = $this->db->table('users')
            ->where('email', $username)
            ->get()
            ->getRow();

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user->password_hash)) {
            return null;
        }

        return new UserEntity($user->id);
    }
}
