<?php

namespace App\OAuth2\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ClientEntity implements ClientEntityInterface
{
    use ClientTrait;

    private string $identifier = '';

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function setRedirectUri($uris): void
    {
        $this->redirectUri = $uris;
    }

    public function setIsConfidential($isConfidential): void
    {
        $this->isConfidential = $isConfidential;
    }
}
