<?php

namespace App\Exceptions;

class UserNotFoundException extends \Exception
{
    public function __construct(string $userId)
    {
        parent::__construct("User '{$userId}' not found", 404);
    }
}
