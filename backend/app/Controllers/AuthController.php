<?php

namespace App\Controllers;

use App\DTO\RegisterUserDTO;
use App\Exceptions\UserAlreadyExistsException;
use App\Services\UserRegistrationService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;

class AuthController extends Controller
{
    use ResponseTrait;

    public function register()
    {
        $input = $this->request->getJSON(true);

        try {
            $dto = new RegisterUserDTO(
                email:      $input['email'] ?? '',
                password:   $input['password'] ?? '',
                first_name: $input['first_name'] ?? '',
                last_name:  $input['last_name'] ?? '',
            );

            $service = new UserRegistrationService(
                new \App\Repositories\UserRepository(\Config\Database::connect())
            );
            $userId = $service->register($dto);

            return $this->respond(['id' => $userId], 201);
        } catch (UserAlreadyExistsException $e) {
            return $this->respond(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(['errors' => json_decode($e->getMessage(), true)], 422);
        } catch (\Throwable $e) {
            return $this->respond(['error' => 'Registration failed'], 500);
        }
    }

    public function token()
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory
        );
        $serverRequest = $creator->fromGlobals();
        $serverResponse = $psr17Factory->createResponse();

        try {
            $response = \App\Libraries\OAuthServer::getInstance()
                ->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            $response = $e->generateHttpResponse($serverResponse);
        } catch (\Exception $e) {
            $response = $serverResponse->withStatus(500)
                ->withBody($psr17Factory->createStream(json_encode(['error' => 'server_error'])));
        }

        return $response;
    }
}
