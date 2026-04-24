<?php

namespace App\Libraries;

use App\OAuth2\Repositories\AccessTokenRepository;
use App\OAuth2\Repositories\ClientRepository;
use App\OAuth2\Repositories\RefreshTokenRepository;
use App\OAuth2\Repositories\ScopeRepository;
use App\OAuth2\Repositories\UserOAuth2Repository;
use CodeIgniter\Database\BaseConnection;
use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;

class OAuthServer
{
    private static ?OAuthServer $instance = null;
    private AuthorizationServer $authorizationServer;
    private ResourceServer $resourceServer;

    private function __construct()
    {
        $db = \Config\Database::connect();

        // Load keys from files
        $privateKeyPath = WRITEPATH . 'oauth_keys/private.key';
        $publicKeyPath = WRITEPATH . 'oauth_keys/public.key';

        if (!file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
            throw new \RuntimeException('OAuth2 RSA keys not found. Run: php spark oauth:setup');
        }

        $encryptionKey = getenv('OAUTH_ENCRYPTION_KEY');
        if (!$encryptionKey) {
            throw new \RuntimeException('OAUTH_ENCRYPTION_KEY not set in .env');
        }

        // AuthorizationServer requires ClientRepository, AccessTokenRepository, ScopeRepository
        $this->authorizationServer = new AuthorizationServer(
            new ClientRepository($db),
            new AccessTokenRepository($db),
            new ScopeRepository($db),
            $privateKeyPath,
            $encryptionKey
        );

        // Enable password grant
        $passwordGrant = new PasswordGrant(
            new UserOAuth2Repository($db),
            new RefreshTokenRepository($db)
        );
        $passwordGrant->setRefreshTokenTTL(new DateInterval('P30D'));
        $this->authorizationServer->enableGrantType($passwordGrant, new DateInterval('PT1H'));

        // Enable refresh token grant
        $refreshGrant = new RefreshTokenGrant(new RefreshTokenRepository($db));
        $refreshGrant->setAccessTokenRepository(new AccessTokenRepository($db));
        $refreshGrant->setRefreshTokenTTL(new DateInterval('P30D'));
        $this->authorizationServer->enableGrantType($refreshGrant, new DateInterval('PT1H'));

        // ResourceServer for JWT validation
        $this->resourceServer = new ResourceServer(
            new AccessTokenRepository($db),
            $publicKeyPath
        );
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getAuthorizationServer(): AuthorizationServer
    {
        return $this->authorizationServer;
    }

    public function getResourceServer(): ResourceServer
    {
        return $this->resourceServer;
    }
}
