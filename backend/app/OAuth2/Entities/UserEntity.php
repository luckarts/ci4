<?php

namespace App\OAuth2\Entities;

use League\OAuth2\Server\Entities\UserEntityInterface;

class UserEntity implements UserEntityInterface
{
    private string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getIdentifier(): string
    {
        return $this->id;
    }
}
