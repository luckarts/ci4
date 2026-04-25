<?php

namespace App\Filters;

use App\Libraries\AuthContext;
use App\Libraries\OAuthServer;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $psr17Factory = new Psr17Factory();
        $creator = new ServerRequestCreator(
            $psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory
        );
        $psrRequest = $creator->fromGlobals();

        try {
            $validated = OAuthServer::getInstance()
                ->getResourceServer()
                ->validateAuthenticatedRequest($psrRequest);

            $userId = $validated->getAttribute('oauth_user_id');
            AuthContext::setUserId((string) $userId);
            return null;
        } catch (\League\OAuth2\Server\Exception\OAuthServerException $e) {
            return service('response')
                ->setStatusCode(401)
                ->setContentType('application/json')
                ->setBody(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
        } catch (\Throwable $e) {
            return service('response')
                ->setStatusCode(401)
                ->setContentType('application/json')
                ->setBody(json_encode(['error' => 'Unauthorized']));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
