<?php

namespace App\Modules\User\Controllers;

use App\Modules\Shared\Libraries\AuthContext;
use App\Modules\Shared\Repositories\UserRepository;
use App\Modules\User\DTO\UpdateProfileDTO;
use App\Modules\User\Exceptions\UserNotFoundException;
use App\Modules\User\Services\DeleteUserService;
use App\Modules\User\Services\UpdateProfileService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class UserController extends Controller
{
    use ResponseTrait;

    /**
     * GET /users/{id}
     * Returns user profile information (authenticated users only).
     *
     * @param string $id User ID to retrieve
     * @http 200 Success - User object
     * @http 401 Unauthorized - No valid authentication token
     * @http 403 Forbidden - Can only access own profile
     * @http 404 Not Found - User does not exist
     */
    public function show(string $id)
    {
        $authUserId = AuthContext::getUserId();
        if ($authUserId === null) {
            return $this->respond(['error' => 'Unauthorized'], 401);
        }

        try {
            $repository = new UserRepository(\Config\Database::connect());
            $user       = $repository->findById($id);
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
     * Updates user profile information (authenticated users only).
     *
     * @param string $id User ID to update
     * @http 200 Success - Updated user object
     * @http 401 Unauthorized - No valid authentication token
     * @http 403 Forbidden - Can only update own profile
     * @http 404 Not Found - User does not exist
     * @http 422 Validation Failed - Invalid input data
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
                new UserRepository(\Config\Database::connect())
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
     * Deletes user account (authenticated users only).
     *
     * @param string $id User ID to delete
     * @http 200 Success - Account deleted
     * @http 401 Unauthorized - No valid authentication token
     * @http 403 Forbidden - Can only delete own account
     * @http 404 Not Found - User does not exist
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
                new UserRepository(\Config\Database::connect())
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
