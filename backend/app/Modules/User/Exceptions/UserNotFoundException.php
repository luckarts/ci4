<?php

namespace App\Modules\User\Exceptions;

class UserNotFoundException extends \Exception
{
    public function __construct(string $userId)
    {
        parent::__construct("User '{$userId}' not found", 404);
    }
}
