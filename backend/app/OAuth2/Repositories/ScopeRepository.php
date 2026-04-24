<?php

namespace App\OAuth2\Repositories;

use App\OAuth2\Entities\ScopeEntity;
use CodeIgniter\Database\BaseConnection;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeRepository implements ScopeRepositoryInterface
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function getScopeEntityByIdentifier(string $scopeIdentifier): ?ScopeEntityInterface
    {
        $scope = $this->db->table('oauth_scopes')
            ->where('id', $scopeIdentifier)
            ->get()
            ->getRow();

        if (!$scope) {
            return null;
        }

        $entity = new ScopeEntity();
        $entity->setIdentifier($scope->id);

        return $entity;
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        \League\OAuth2\Server\Entities\ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        return $scopes;
    }
}
