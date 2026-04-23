<?php

namespace Tests\Unit;

use App\DTO\RegisterUserDTO;
use PHPUnit\Framework\TestCase;

class RegisterDTOValidationTest extends TestCase
{
    public function testInvalidEmail(): void
    {
        $dto = new RegisterUserDTO('invalid', 'password123', 'First', 'Last');
        $errors = $dto->validate();
        $this->assertArrayHasKey('email', $errors);
    }

    public function testPasswordTooShort(): void
    {
        $dto = new RegisterUserDTO('user@test.com', 'short', 'First', 'Last');
        $errors = $dto->validate();
        $this->assertArrayHasKey('password', $errors);
    }

    public function testValidDTO(): void
    {
        $dto = new RegisterUserDTO('user@test.com', 'password123', 'First', 'Last');
        $errors = $dto->validate();
        $this->assertEmpty($errors);
    }
}
