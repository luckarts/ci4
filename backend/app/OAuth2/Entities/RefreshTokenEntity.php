<?php

namespace App\OAuth2\Entities;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

class RefreshTokenEntity implements RefreshTokenEntityInterface
{
    private string $identifier = '';
    private DateTimeImmutable $expiryDateTime;
    private ?AccessTokenEntityInterface $accessToken = null;

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

    public function getAccessToken(): AccessTokenEntityInterface
    {
        return $this->accessToken;
    }

    public function setAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $this->accessToken = $accessTokenEntity;
    }
}
