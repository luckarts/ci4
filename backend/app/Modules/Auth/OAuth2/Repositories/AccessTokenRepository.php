<?php

namespace App\Modules\Auth\OAuth2\Repositories;

use App\Modules\Auth\OAuth2\Entities\AccessTokenEntity;
use CodeIgniter\Database\BaseConnection;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function getNewToken(
        \League\OAuth2\Server\Entities\ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): AccessTokenEntityInterface {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        $accessToken->setUserIdentifier($userIdentifier);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $tokenId = $accessTokenEntity->getIdentifier();

        $this->db->table('oauth_access_tokens')->insert([
            'id'         => $tokenId,
            'user_id'    => $accessTokenEntity->getUserIdentifier(),
            'client_id'  => $accessTokenEntity->getClient()->getIdentifier(),
            'scopes'     => implode(' ', $this->scopesToArray($accessTokenEntity->getScopes())),
            'revoked'    => 'false',
            'expires_at' => date('Y-m-d H:i:s', $accessTokenEntity->getExpiryDateTime()->getTimestamp()),
        ]);
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $this->db->table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->update(['revoked' => true]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $token = $this->db->table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->get()
            ->getRow();

        return $token && ($token->revoked === true || $token->revoked === 't');
    }

    private function scopesToArray(array $scopes): array
    {
        return array_map(fn($scope) => $scope->getIdentifier(), $scopes);
    }
}
