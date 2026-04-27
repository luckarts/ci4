<?php

namespace App\Modules\Auth\OAuth2\Entities;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;

class AccessTokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait;

    private string $identifier = '';
    private DateTimeImmutable $expiryDateTime;
    private ?ClientEntityInterface $client = null;
    private ?string $userIdentifier = null;
    private array $scopes = [];

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getExpiryDateTime(): DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function setExpiryDateTime(DateTimeImmutable $dateTime): void
    {
        $this->expiryDateTime = $dateTime;
    }

    public function getClient(): ClientEntityInterface
    {
        return $this->client;
    }

    public function setClient(ClientEntityInterface $clientEntity): void
    {
        $this->client = $clientEntity;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier($userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function addScope(\League\OAuth2\Server\Entities\ScopeEntityInterface $scopeEntity): void
    {
        $this->scopes[$scopeEntity->getIdentifier()] = $scopeEntity;
    }

    public function getScopes(): array
    {
        return array_values($this->scopes);
    }
}
