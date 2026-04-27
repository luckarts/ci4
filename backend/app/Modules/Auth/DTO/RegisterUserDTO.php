<?php

namespace App\Modules\Auth\DTO;

class RegisterUserDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public string $first_name,
        public string $last_name,
    ) {}

    public function validate(): array
    {
        $errors = [];

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }
        if (strlen($this->password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        if (empty($this->first_name)) {
            $errors['first_name'] = 'First name is required';
        }
        if (empty($this->last_name)) {
            $errors['last_name'] = 'Last name is required';
        }

        return $errors;
    }
}
