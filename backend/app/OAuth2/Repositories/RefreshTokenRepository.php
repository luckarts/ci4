<?php

namespace App\OAuth2\Repositories;

use App\OAuth2\Entities\RefreshTokenEntity;
use CodeIgniter\Database\BaseConnection;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function getNewRefreshToken(): RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $this->db->table('oauth_refresh_tokens')->insert([
            'id' => $refreshTokenEntity->getIdentifier(),
            'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            'revoked' => false,
            'expires_at' => date('Y-m-d H:i:s', $refreshTokenEntity->getExpiryDateTime()->getTimestamp()),
        ]);
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->db->table('oauth_refresh_tokens')
            ->where('id', $tokenId)
            ->update(['revoked' => true]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $token = $this->db->table('oauth_refresh_tokens')
            ->where('id', $tokenId)
            ->get()
            ->getRow();

        if (!$token) {
            return false;
        }

        // SQLite returns boolean as 0/1 or 'f'/'t'
        return $token->revoked === 1 || $token->revoked === '1' || $token->revoked === true || $token->revoked === 't';
    }
}
