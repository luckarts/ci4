<?php

namespace App\Exceptions;

class UserAlreadyExistsException extends \Exception
{
    public function __construct(string $email)
    {
        parent::__construct("User with email '{$email}' already exists");
    }
}
