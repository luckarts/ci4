<?php

namespace Tests\Unit;

use App\Modules\User\DTO\UpdateProfileDTO;
use CodeIgniter\Test\CIUnitTestCase;

class UpdateProfileDTOTest extends CIUnitTestCase
{
    public function test_validate_valid_data_returns_empty_array()
    {
        $dto = new UpdateProfileDTO(
            first_name: 'John',
            last_name:  'Doe',
        );

        $errors = $dto->validate();

        $this->assertEmpty($errors);
    }

    public function test_validate_empty_first_name_returns_error()
    {
        $dto = new UpdateProfileDTO(
            first_name: '',
            last_name:  'Doe',
        );

        $errors = $dto->validate();

        $this->assertArrayHasKey('first_name', $errors);
        $this->assertCount(1, $errors);
    }

    public function test_validate_empty_last_name_returns_error()
    {
        $dto = new UpdateProfileDTO(
            first_name: 'John',
            last_name:  '',
        );

        $errors = $dto->validate();

        $this->assertArrayHasKey('last_name', $errors);
        $this->assertCount(1, $errors);
    }

    public function test_validate_both_empty_returns_two_errors()
    {
        $dto = new UpdateProfileDTO(
            first_name: '',
            last_name:  '',
        );

        $errors = $dto->validate();

        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertCount(2, $errors);
    }

    public function test_validate_whitespace_only_is_invalid()
    {
        $dto = new UpdateProfileDTO(
            first_name: '   ',
            last_name:  '   ',
        );

        $errors = $dto->validate();

        $this->assertArrayHasKey('first_name', $errors);
        $this->assertArrayHasKey('last_name', $errors);
    }
}
