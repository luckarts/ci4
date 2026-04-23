---
name: test:e2e-write
description: "Écrire un test E2E sans erreur — template, conventions email, assertions sécurisées, patterns collection et binaire"
argument-hint: [resource-name|bc-name]
triggers:
  - écrire test e2e
  - nouveau test e2e
  - template test e2e
  - comment tester un endpoint
  - ajouter test e2e
  - test e2e nouveau bc
  - test e2e nouveau fichier
  - convention email test
  - email collision test
  - assertion intermédiaire test
  - vérifier réponse intermédiaire
  - assertSame réponse
---

# Écrire un test E2E sans erreur

Guide prescriptif pour écrire un nouveau test E2E dans ce projet.
Basé sur `AbstractApiTestCase` + `ApiTestHelper` (trait réel du projet).

---

## Méthodes disponibles via `ApiTestHelper`

```php
// Créer un utilisateur en BDD
$this->createUser(string $email, string $password, string $firstName = 'John', string $lastName = 'Doe'): User

// Obtenir un token OAuth2
$token = $this->getOAuth2Token(string $email, string $password): string

// Requête API Platform (JSON-LD)
$response = $this->apiRequest(string $method, string $uri, ?string $token = null, array $data = []): Response

// Requête multipart pour upload de fichier
$response = $this->apiUploadRequest(string $method, string $uri, ?string $token = null, array $files = []): Response
```

---

## Template de base

```php
<?php

declare(strict_types=1);

namespace App\Tests\E2E\{BC};

use App\Tests\E2E\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class {Resource}Test extends AbstractApiTestCase
{
    #[Test]
    #[Group('e2e')]
    #[Group('{bc}')]
    public function test_name(): void
    {
        // 1. Setup
        $this->createUser('{bc}-{action}@example.com', 'password123');
        $token = $this->getOAuth2Token('{bc}-{action}@example.com', 'password123');

        // 2. Appel intermédiaire — TOUJOURS vérifier le status code
        $taskResponse = $this->apiRequest('POST', '/api/tasks', $token, ['title' => 'Task']);
        $this->assertSame(Response::HTTP_CREATED, $taskResponse->getStatusCode());
        $taskId = json_decode($taskResponse->getContent(), true)['id'];

        // 3. Appel cible
        $response = $this->apiRequest('GET', "/api/tasks/{$taskId}", $token);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame($taskId, $data['id']);
    }
}
```

---

## Règle 1 — Convention d'email (éviter les collisions)

Chaque test utilise un email unique et descriptif. La base de données n'est pas réinitialisée entre les méthodes de test.

```
Pattern : {bc}-{action}-{variante}@example.com

Exemples :
  att-upload@example.com          ← BC attachment, action upload
  att-del-owner@example.com       ← BC attachment, action delete, variante owner
  att-del-other@example.com       ← BC attachment, action delete, variante other user
  att-wf-alice@example.com        ← BC attachment, workflow, variante alice
  att-wf-bob@example.com          ← BC attachment, workflow, variante bob
```

**Règle :** chaque méthode de test = emails distincts. Ne jamais réutiliser le même email dans deux méthodes différentes d'un même fichier, ni dans deux fichiers du même BC.

---

## Règle 2 — Vérifier TOUTES les réponses intermédiaires

Les réponses intermédiaires (POST pour créer une entité parente) doivent toutes être vérifiées.
Sans ça, un `json_decode(...)['id']` retourne `null` et les assertions suivantes échouent avec un message cryptique.

```php
// ❌ Pas de vérification → erreur cryptique si POST échoue
$taskResponse = $this->apiRequest('POST', '/api/tasks', $token, ['title' => 'Task']);
$taskId = json_decode($taskResponse->getContent(), true)['id'];  // null si 500

// ✅ Toujours asserter le status intermédiaire
$taskResponse = $this->apiRequest('POST', '/api/tasks', $token, ['title' => 'Task']);
$this->assertSame(Response::HTTP_CREATED, $taskResponse->getStatusCode());
$taskId = json_decode($taskResponse->getContent(), true)['id'];
```

---

## Règle 3 — Collections JSON-LD : accéder à `['member']`

API Platform 4 retourne une enveloppe JSON-LD pour les collections. `assertCount` sur `$data` compte les clés JSON-LD (5), pas les ressources.

```php
// ❌ Compte les clés de l'enveloppe JSON-LD (toujours 5)
$data = json_decode($response->getContent(), true);
$this->assertCount(1, $data);        // FAIL : 5 clés (@context, @id, @type, member, totalItems)
$this->assertSame($id, $data[0]['id']); // FAIL : $data[0] n'existe pas

// ✅ Accéder à 'member' (API Platform 4)
$data = json_decode($response->getContent(), true);
$members = $data['member'];
$this->assertCount(1, $members);
$this->assertSame($id, $members[0]['id']);
```

Structure JSON-LD de référence :
```json
{
  "@context": "/api/contexts/Attachment",
  "@id": "/api/tasks/{id}/attachments",
  "@type": "hydra:Collection",
  "member": [ { "id": "...", "originalFilename": "..." } ],
  "totalItems": 1
}
```

---

## Règle 4 — BinaryFileResponse : vérifier les headers, pas le contenu

`BinaryFileResponse::getContent()` retourne **toujours `false`**. Le contenu est streamé, pas stocké en mémoire.

```php
// ❌ Retourne false
$this->assertStringContainsString('expected', $downloadResponse->getContent());

// ✅ Vérifier les headers (approche E2E correcte)
$this->assertSame(Response::HTTP_OK, $downloadResponse->getStatusCode());
$this->assertStringContainsString(
    'attachment',
    $downloadResponse->headers->get('Content-Disposition') ?? ''
);
$this->assertStringContainsString(
    'text/plain',
    $downloadResponse->headers->get('Content-Type') ?? ''
);
```

---

## Règle 5 — `createTempFile` : ne pas dupliquer

La méthode `createTempFile` est dupliquée dans `UploadAttachmentTest`, `DeleteAttachmentTest`, `AttachmentWorkflowTest`, `GetAttachmentTest`. Pour un nouveau BC avec upload, la mettre dans `ApiTestHelper` ou dans un trait dédié au BC.

```php
// Pattern actuel (dupliqué dans chaque fichier) — accepté mais non idéal
private function createTempFile(string $content, string $extension, string $mimeType): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'att_test_') . '.' . $extension;
    file_put_contents($path, $content);
    return new UploadedFile($path, 'test.' . $extension, $mimeType, null, true);
}

// Pour un nouveau BC avec upload : ajouter dans ApiTestHelper plutôt que dupliquer
```

---

## Règle 6 — Capturer les métadonnées UploadedFile AVANT store()

Dans les Processors qui traitent des fichiers uploadés, `getMimeType()`, `getSize()`, `getClientOriginalName()`, `guessExtension()` doivent être appelés **avant** `store()` / `move()`.

```php
// ❌ getMimeType() après move() → exception "file does not exist"
$this->storage->store($file, $storagePath);     // déplace le fichier
$mimeType = $file->getMimeType();               // FAIL : fichier introuvable

// ✅ Capturer toutes les métadonnées avant store()
$originalName = $file->getClientOriginalName();
$mimeType     = $file->getMimeType() ?? 'application/octet-stream';
$size         = (int) $file->getSize();
$extension    = $file->guessExtension() ?? 'bin';

$this->storage->store($file, $storagePath);    // maintenant safe
```

---

## Checklist avant de committer un test E2E

- [ ] Email unique par méthode de test, format `{bc}-{action}-{variante}@example.com`
- [ ] Toutes les réponses intermédiaires vérifiées avec `assertSame(HTTP_CREATED, ...)`
- [ ] Collections : `$data['member']` et non `$data`
- [ ] BinaryFileResponse : headers vérifiés, pas `getContent()`
- [ ] `#[Group('e2e')]` + `#[Group('{bc}')]` présents sur chaque méthode
- [ ] Happy path : `#[Group('smoke')]` ajouté
- [ ] Pas de `createTempFile` dupliqué si déjà présent dans le BC
- [ ] URL de download récupérée depuis `$data['downloadUrl']`, pas hardcodée

---

## Commandes de vérification rapide

```bash
# Lancer les tests d'un BC
make test-e2e  # tous les E2E

# Ou cibler un groupe
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/phpunit --group attachment --testdox

# Lire les logs Symfony si un test retourne 500
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php sh -c \
  'bin/phpunit --filter nom_du_test --testdox; cat var/log/test.log | tail -50'

# Réinitialiser la DB avant les tests (évite les collisions email)
make test-db-reset && make test-e2e
```
