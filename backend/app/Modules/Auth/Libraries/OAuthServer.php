<?php

namespace App\Modules\Auth\Libraries;

use App\Modules\Auth\OAuth2\Repositories\AccessTokenRepository;
use App\Modules\Auth\OAuth2\Repositories\ClientRepository;
use App\Modules\Auth\OAuth2\Repositories\RefreshTokenRepository;
use App\Modules\Auth\OAuth2\Repositories\ScopeRepository;
use App\Modules\Auth\OAuth2\Repositories\UserOAuth2Repository;
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

        $privateKeyPath = WRITEPATH . 'oauth_keys/private.key';
        $publicKeyPath = WRITEPATH . 'oauth_keys/public.key';

        if (!file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
            throw new \RuntimeException('OAuth2 RSA keys not found. Run: php spark oauth:setup');
        }

        $encryptionKey = getenv('OAUTH_ENCRYPTION_KEY');
        if (!$encryptionKey) {
            throw new \RuntimeException('OAUTH_ENCRYPTION_KEY not set in .env');
        }

        $accessTokenTTL  = $this->getTTL('OAUTH_ACCESS_TOKEN_TTL', 'PT1H');
        $refreshTokenTTL = $this->getTTL('OAUTH_REFRESH_TOKEN_TTL', 'P30D');

        $this->authorizationServer = new AuthorizationServer(
            new ClientRepository($db),
            new AccessTokenRepository($db),
            new ScopeRepository($db),
            $privateKeyPath,
            $encryptionKey
        );

        $passwordGrant = new PasswordGrant(
            new UserOAuth2Repository($db),
            new RefreshTokenRepository($db)
        );
        $passwordGrant->setRefreshTokenTTL($refreshTokenTTL);
        $this->authorizationServer->enableGrantType($passwordGrant, $accessTokenTTL);

        $refreshGrant = new RefreshTokenGrant(new RefreshTokenRepository($db));
        $refreshGrant->setAccessTokenRepository(new AccessTokenRepository($db));
        $refreshGrant->setRefreshTokenTTL($refreshTokenTTL);
        $this->authorizationServer->enableGrantType($refreshGrant, $accessTokenTTL);

        $this->resourceServer = new ResourceServer(
            new AccessTokenRepository($db),
            $publicKeyPath
        );
    }

    private function getTTL(string $envVar, string $default): DateInterval
    {
        $value = getenv($envVar) ?: $default;
        try {
            return new DateInterval($value);
        } catch (\Exception $e) {
            service('logger')->warning("Invalid ISO 8601 duration for $envVar=$value, using default $default");
            return new DateInterval($default);
        }
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
