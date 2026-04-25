<?php

namespace App\Controllers;

use App\DTO\RegisterUserDTO;
use App\Exceptions\UserAlreadyExistsException;
use App\Services\UserRegistrationService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Response;

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
            $psrResponse = \App\Libraries\OAuthServer::getInstance()
                ->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            $psrResponse = $e->generateHttpResponse($serverResponse);
        } catch (\Exception $e) {
            $psrResponse = $serverResponse->withStatus(500)
                ->withBody($psr17Factory->createStream(json_encode(['error' => 'server_error'])));
        }

        // Convert PSR-7 response to CodeIgniter response
        $this->response->setStatusCode($psrResponse->getStatusCode());

        // Set headers
        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->response->setHeader($name, $value);
            }
        }

        // Set body
        $body = (string) $psrResponse->getBody();
        if (!empty($body)) {
            $this->response->setBody($body);
        }

        return $this->response;
    }

    public function revoke()
    {
        $input = [];
        try {
            $input = $this->request->getJSON(true) ?? [];
        } catch (\Exception $e) {
            // Not JSON, try form-encoded
        }
        if (empty($input)) {
            $input = $this->request->getPost();
        }

        $token = $input['token'] ?? '';
        $tokenTypeHint = $input['token_type_hint'] ?? '';

        if (empty($token)) {
            return $this->respond([], 200);
        }

        $db = \Config\Database::connect();
        $encryptionKey = getenv('OAUTH_ENCRYPTION_KEY');

        // Try to revoke as refresh token
        if ($tokenTypeHint === 'refresh_token' || empty($tokenTypeHint)) {
            if ($this->tryRevokeRefreshToken($token, $db, $encryptionKey)) {
                return $this->respond([], 200);
            }
        }

        // Try to revoke as access token
        if ($tokenTypeHint === 'access_token' || empty($tokenTypeHint)) {
            if ($this->tryRevokeAccessToken($token, $db)) {
                return $this->respond([], 200);
            }
        }

        // RFC 7009: always return 200, even if token is invalid
        return $this->respond([], 200);
    }

    private function tryRevokeRefreshToken(string $token, \CodeIgniter\Database\BaseConnection $db, string $encryptionKey): bool
    {
        try {
            $repo = new \App\OAuth2\Repositories\RefreshTokenRepository($db);

            // Decrypt with password (the encryptionKey is a string, not a Key object)
            $decrypted = \Defuse\Crypto\Crypto::decryptWithPassword($token, $encryptionKey);
            $payload = json_decode($decrypted, true);

            if (isset($payload['refresh_token_id'])) {
                $repo->revokeRefreshToken($payload['refresh_token_id']);
                return true;
            }
        } catch (\Throwable $e) {
            // Not a valid refresh token, continue
        }

        return false;
    }

    private function tryRevokeAccessToken(string $token, \CodeIgniter\Database\BaseConnection $db): bool
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            if (isset($payload['jti'])) {
                $repo = new \App\OAuth2\Repositories\AccessTokenRepository($db);
                $repo->revokeAccessToken($payload['jti']);
                return true;
            }
        } catch (\Throwable $e) {
            // Not a valid access token, continue
        }

        return false;
    }
}
