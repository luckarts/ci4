<?php

namespace App\Controllers;

use App\DTO\UpdateProfileDTO;
use App\Exceptions\UserNotFoundException;
use App\Libraries\AuthContext;
use App\Services\UpdateProfileService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class UserController extends Controller
{
    use ResponseTrait;

    public function show(string $id)
    {
        $authUserId = AuthContext::getUserId();
        if ($authUserId === null) {
            return $this->respond(['error' => 'Unauthorized'], 401);
        }

        try {
            $repository = new \App\Repositories\UserRepository(\Config\Database::connect());
            $user = $repository->findById($id);
        } catch (\Throwable $e) {
            return $this->respond(['error' => 'User not found'], 404);
        }

        if ($user === null) {
            return $this->respond(['error' => 'User not found'], 404);
        }

        if ($authUserId !== $id) {
            return $this->respond(['error' => 'Forbidden'], 403);
        }

        unset($user['password_hash']);
        return $this->respond($user, 200);
    }

    public function update(string $id)
    {
        $authUserId = AuthContext::getUserId();
        if ($authUserId === null) {
            return $this->respond(['error' => 'Unauthorized'], 401);
        }

        if ($authUserId !== $id) {
            return $this->respond(['error' => 'Forbidden'], 403);
        }

        $input = $this->request->getJSON(true);

        try {
            $dto = new UpdateProfileDTO(
                first_name: $input['first_name'] ?? '',
                last_name:  $input['last_name'] ?? '',
            );

            $errors = $dto->validate();
            if (!empty($errors)) {
                return $this->respond(['errors' => $errors], 422);
            }

            $service = new UpdateProfileService(
                new \App\Repositories\UserRepository(\Config\Database::connect())
            );
            $updated = $service->updateProfile($id, $dto);

            unset($updated['password_hash']);
            return $this->respond($updated, 200);
        } catch (UserNotFoundException $e) {
            return $this->respond(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return $this->respond(['error' => 'Update failed'], 500);
        }
    }
}
