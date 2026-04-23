<?php

namespace App\Libraries;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;

class OAuthServer
{
    private static ?self $instance = null;
    /** @phpstan-ignore property.onlyRead, property.unusedType */
    private ?AuthorizationServer $authServer = null;
    /** @phpstan-ignore property.onlyRead, property.unusedType */
    private ?ResourceServer $resourceServer = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initializeServers();
        }
        return self::$instance;
    }

    private function initializeServers(): void
    {
        // TODO: Initialize auth and resource servers
    }

    public function getAuthorizationServer(): ?AuthorizationServer
    {
        return $this->authServer;
    }

    public function getResourceServer(): ?ResourceServer
    {
        return $this->resourceServer;
    }
}
