<?php

namespace App\OAuth2\Repositories;

use App\OAuth2\Entities\ClientEntity;
use CodeIgniter\Database\BaseConnection;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;

class ClientRepository implements ClientRepositoryInterface
{
    private BaseConnection $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = $this->db->table('oauth_clients')
            ->where('id', $clientIdentifier)
            ->get()
            ->getRow();

        if (!$client) {
            return null;
        }

        $entity = new ClientEntity();
        $entity->setIdentifier($client->id);
        $entity->setName($client->name);
        $entity->setRedirectUri(explode(',', $client->redirect_uris ?? ''));
        $entity->setIsConfidential($client->is_confidential);

        return $entity;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $client = $this->db->table('oauth_clients')
            ->where('id', $clientIdentifier)
            ->get()
            ->getRow();

        if (!$client) {
            return false;
        }

        if ($client->is_confidential && !hash_equals($client->secret ?? '', $clientSecret ?? '')) {
            return false;
        }

        return true;
    }
}
