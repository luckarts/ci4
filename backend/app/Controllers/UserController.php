<?php

namespace App\Controllers;

use App\DTO\UpdateProfileDTO;
use App\Exceptions\UserNotFoundException;
use App\Libraries\AuthContext;
use App\Services\DeleteUserService;
use App\Services\UpdateProfileService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class UserController extends Controller
{
    use ResponseTrait;

    /**
     * GET /users/{id}
     * Returns user profile information (authenticated users only)
     *
     * @param string $id User ID to retrieve
     * @return JSON User object without password hash
     * @throws 401 Unauthorized - No valid authentication token
     * @throws 403 Forbidden - User can only access their own profile
     * @throws 404 Not Found - User does not exist
     */
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

    /**
     * PUT /users/{id}/profile
     * Updates user profile information (authenticated users only)
     *
     * @param string $id User ID to update
     * @return JSON Updated user object without password hash
     * @throws 401 Unauthorized - No valid authentication token
     * @throws 403 Forbidden - User can only update their own profile
     * @throws 404 Not Found - User does not exist
     * @throws 422 Unprocessable Entity - Validation failed
     */
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

    /**
     * DELETE /users/{id}
     * Deletes user account (authenticated users only)
     *
     * @param string $id User ID to delete
     * @return JSON Confirmation of deletion
     * @throws 401 Unauthorized - No valid authentication token
     * @throws 403 Forbidden - User can only delete their own account
     * @throws 404 Not Found - User does not exist
     */
    public function destroy(string $id)
    {
        $authUserId = AuthContext::getUserId();
        if ($authUserId === null) {
            return $this->respond(['error' => 'Unauthorized'], 401);
        }

        if ($authUserId !== $id) {
            return $this->respond(['error' => 'Forbidden'], 403);
        }

        try {
            $service = new DeleteUserService(
                new \App\Repositories\UserRepository(\Config\Database::connect())
            );
            $deleted = $service->deleteUser($id);

            return $this->respond($deleted, 200);
        } catch (UserNotFoundException $e) {
            return $this->respond(['error' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            return $this->respond(['error' => 'Delete failed'], 500);
        }
    }
}
