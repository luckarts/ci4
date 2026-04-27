<?php

namespace App\Modules\User\DTO;

class UpdateProfileDTO
{
    public function __construct(
        public string $first_name,
        public string $last_name,
    ) {}

    public function validate(): array
    {
        $errors = [];

        if (empty(trim($this->first_name))) {
            $errors['first_name'] = 'First name is required';
        }
        if (empty(trim($this->last_name))) {
            $errors['last_name'] = 'Last name is required';
        }

        return $errors;
    }
}
