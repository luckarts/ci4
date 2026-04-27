<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\DTO\RegisterUserDTO;
use App\Modules\Auth\Exceptions\UserAlreadyExistsException;
use App\Modules\Auth\Libraries\OAuthServer;
use App\Modules\Auth\Services\TokenRevocationService;
use App\Modules\Auth\Services\UserRegistrationService;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\Response;

class AuthController extends Controller
{
    use ResponseTrait;

    private UserRegistrationService $registrationService;
    private TokenRevocationService $revocationService;
    private OAuthServer $oAuthServer;

    public function __construct()
    {
        $this->registrationService = service('userRegistrationService');
        $this->revocationService = service('tokenRevocationService');
        $this->oAuthServer = service('oAuthServer');
    }

    /**
     * POST /auth/register
     * Register a new user account.
     *
     * @http 201 Created - User successfully registered
     * @http 409 Conflict - User already exists with this email
     * @http 422 Unprocessable Entity - Validation failed
     * @http 500 Server Error - Registration failed
     */
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

            $userId = $this->registrationService->register($dto);

            return $this->respond(['id' => $userId], 201);
        } catch (UserAlreadyExistsException $e) {
            return $this->respond(['error' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(['errors' => json_decode($e->getMessage(), true)], 422);
        } catch (\Throwable $e) {
            return $this->respond(['error' => 'Registration failed'], 500);
        }
    }

    /**
     * POST /auth/token
     * Generate OAuth2 access token and refresh token (password grant flow, rate-limited).
     *
     * @http 200 OK - Access and refresh tokens generated
     * @http 400 Bad Request - Invalid credentials or missing parameters
     * @http 429 Too Many Requests - Rate limit exceeded
     * @http 500 Server Error - Server error
     */
    public function token()
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory
        );
        $serverRequest  = $creator->fromGlobals();
        $serverResponse = $psr17Factory->createResponse();

        try {
            $psrResponse = $this->oAuthServer
                ->getAuthorizationServer()
                ->respondToAccessTokenRequest($serverRequest, $serverResponse);
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            $psrResponse = $e->generateHttpResponse($serverResponse);
        } catch (\Exception $e) {
            $psrResponse = $serverResponse->withStatus(500)
                ->withBody($psr17Factory->createStream(json_encode(['error' => 'server_error'])));
        }

        $this->response->setStatusCode($psrResponse->getStatusCode());

        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->response->setHeader($name, $value);
            }
        }

        $body = (string) $psrResponse->getBody();
        if (!empty($body)) {
            $this->response->setBody($body);
        }

        return $this->response;
    }

    /**
     * POST /auth/revoke
     * Revoke OAuth2 token (access or refresh token, rate-limited).
     *
     * @http 200 OK - Token revoked or invalid token (RFC 7009 compliant)
     * @http 429 Too Many Requests - Rate limit exceeded
     */
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

        $token         = $input['token'] ?? '';
        $tokenTypeHint = $input['token_type_hint'] ?? '';

        $this->revocationService->revoke($token, $tokenTypeHint);

        return $this->respond([], 200);
    }
}
