---
name: test:e2e-debug
description: "Diagnostiquer et corriger les erreurs courantes dans les tests E2E de ce projet (Symfony + API Platform + Docker + PHPUnit)"
argument-hint: [500|404|redirect|collection|upload|binary|isolation]
triggers:
  - test e2e échoue
  - erreur test e2e
  - 500 dans les tests
  - test attachment
  - test upload fichier
  - BinaryFileResponse getContent false
  - UploadedFile getMimeType
  - assertCount collection
  - hydra member
  - member collection
  - db isolation test
  - disableReboot
  - make test-db-reset
  - var/log/test.log
  - 301 redirect test
  - test échoue sans raison
---

# Déboguer les erreurs E2E — Symfony + API Platform 4 + PHPUnit

Guide de diagnostic basé sur les erreurs réelles rencontrées dans ce projet.
**Règle d'or** : lire `var/log/test.log` en premier avant de modifier le code.

---

## Étape 1 — Lire les logs Symfony

```bash
# Lancer le test qui échoue + lire le log immédiatement après
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php sh -c \
  'bin/phpunit --filter nom_du_test --testdox; cat var/log/test.log | tail -50'
```

Les logs contiennent le vrai message d'exception (pas juste le code HTTP).

---

## Erreur 1 — 500 sur upload de fichier (`UploadedFile` après `move()`)

### Symptôme
```
Failed asserting that 500 is identical to 201.
# Dans var/log/test.log :
Symfony\Component\Mime\Exception\InvalidArgumentException:
"The "/tmp/att_test_xxx.txt" file does not exist or is not readable."
```

### Cause
`getMimeType()` / `guessExtension()` sont appelés **après** `$file->move()` dans le processor.
`move()` déplace le fichier physique → la source n'existe plus.

```php
// ❌ Bug : getMimeType() appelé après store() qui déplace le fichier
$this->storage->store($file, $storagePath);  // ← déplace le fichier
$attachment = new Attachment(
    $file->getMimeType() ?? 'application/octet-stream',  // ← fichier inexistant !
);

// ✅ Fix : capturer toutes les métadonnées AVANT store()
$originalName = $file->getClientOriginalName();
$mimeType = $file->getMimeType() ?? 'application/octet-stream';
$size = (int) $file->getSize();
$extension = $file->guessExtension() ?? 'bin';
$storagePath = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);

$this->storage->store($file, $storagePath);  // déplace le fichier

$attachment = new Attachment($originalName, $mimeType, $size, $storagePath, ...);
```

### Règle
> Toujours capturer `getMimeType()`, `getSize()`, `getClientOriginalName()`, `guessExtension()`
> **avant** tout appel à `store()` / `move()` sur l'`UploadedFile`.

---

## Erreur 2 — `assertCount(1, $data)` retourne 5 sur une collection

### Symptôme
```
Failed asserting that actual size 5 matches expected size 1.
# Le test uploade 1 fichier, GET /api/tasks/{id}/attachments retourne "5 éléments"
```

### Cause
API Platform 4 retourne une réponse JSON-LD pour `GetCollection`.
`$data` est un **objet** avec 5 clés, pas un tableau de ressources :

```json
{
  "@context": "/api/contexts/Attachment",
  "@id": "/api/tasks/.../attachments",
  "@type": "hydra:Collection",
  "member": [ {...} ],
  "totalItems": 1
}
```

`assertCount(1, $data)` compte les clés JSON-LD (5), pas les membres (1).

### Fix
```php
// ❌ Compte les clés JSON-LD, pas les membres
$data = json_decode($response->getContent(), true);
$this->assertCount(1, $data);
$this->assertSame($id, $data[0]['id']);

// ✅ Accéder à 'member' (clé API Platform 4)
$data = json_decode($response->getContent(), true);
$members = $data['member'];
$this->assertCount(1, $members);
$this->assertSame($id, $members[0]['id']);
```

### Structure de référence — API Platform 4
| Clé | Valeur |
|-----|--------|
| `member` | `list<Resource>` — la vraie liste |
| `totalItems` | `int` — nombre total |
| `@context` | string URL |
| `@id` | string URL |
| `@type` | `"hydra:Collection"` |

---

## Erreur 3 — `getContent()` retourne `false` sur un download

### Symptôme
```
Failed asserting that false is identical to 'expected content'.
# Sur une réponse BinaryFileResponse (download de fichier)
```

### Cause
`BinaryFileResponse::getContent()` retourne **toujours `false`** en PHP.
Le contenu est streamed vers l'output, pas stocké en mémoire.

```php
// ❌ Impossible : BinaryFileResponse ne stocke pas le contenu
$this->assertSame('expected content', $downloadResponse->getContent()); // false !

// ✅ Option 1 : vérifier les headers (meilleure approche E2E)
$this->assertSame(Response::HTTP_OK, $downloadResponse->getStatusCode());
$this->assertStringContainsString(
    'attachment',
    $downloadResponse->headers->get('Content-Disposition') ?? ''
);
$this->assertStringContainsString(
    'text/plain',
    $downloadResponse->headers->get('Content-Type') ?? ''
);

// ✅ Option 2 : capturer via output buffering (si le contenu est critique)
ob_start();
$downloadResponse->sendContent();
$content = ob_get_clean();
$this->assertSame('expected content', $content);
```

### Quand utiliser quelle option
- **E2E** → option 1 (headers) : vérifie le contrat API sans accéder aux internals
- **Intégration** → option 2 (ob_start) : si le contenu exact est critique

---

## Erreur 4 — Tests échouent sur une DB "fraîche" après plusieurs runs

### Symptôme
```
Failed asserting that actual size 5 matches expected size 1.
# Même après make test-db-reset, le problème persiste sur le premier test
```

### Diagnostic
Si le problème persiste sur DB fraîche → ce n'est **pas** un problème d'isolation.
Voir Erreur 2 (structure JSON-LD) ou Erreur 5 (EntityManager).

Si le problème disparaît après `make test-db-reset` → isolation entre runs :

```bash
# Réinitialiser avant chaque session de tests
make test-db-reset && make test-e2e

# Ne pas utiliser test-db (update --force) → accumule les données
# make test-db  ← NE PAS UTILISER si les tests accumulent des données
```

---

## Erreur 5 — Comportement inattendu avec `disableReboot()`

### Contexte
`AbstractApiTestCase::setUp()` appelle `$this->client->disableReboot()`.
Cela maintient le même kernel (et EntityManager) **entre les requêtes** d'un même test.

### Conséquence : EntityManager partagé
Le cache de l'EntityManager accumule des entités entre les requêtes.
Les `find()` peuvent retourner des entités depuis le cache au lieu de la BDD.

```php
// setUp() dans AbstractApiTestCase
protected function setUp(): void
{
    $this->client = static::createClient();
    $this->client->disableReboot();  // ← kernel partagé entre requêtes
}
```

### Fix si les collections retournent trop de résultats
```php
// Dans le repository, forcer une requête fraîche
$this->getEntityManager()->clear();  // vide le cache de l'EM
return $this->findBy(['task' => $task]);
```

### Fix si les entités ne se sauvegardent pas
Le kernel est réutilisé entre tests dans la même suite → fermer proprement le kernel :
```php
protected function tearDown(): void
{
    parent::tearDown();
    // Pas nécessaire avec createClient() dans setUp() — ça recrée le kernel
}
```

---

## Erreur 6 — 301 Redirect sur les endpoints de download

### Symptôme
```
Failed asserting that 301 is identical to 200.
# Sur GET /api/attachments/{id}/download
```

### Cause
Trailing slash manquant ou `router.request_context.base_url` mal configuré en test.
Ou bien le `KernelBrowser` suit des redirections automatiquement.

### Diagnostic
```bash
# Voir les routes réelles
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/console debug:router --env=test | grep download
```

### Fix
```php
// Vérifier l'URL exacte depuis la resource (downloadUrl généré par le provider)
$downloadUrl = $attachment['downloadUrl'];  // ex: /api/attachments/{id}/download
$this->apiRequest('GET', $downloadUrl, $token);  // utiliser l'URL générée

// Ne pas hardcoder l'URL dans le test
// ❌ $this->apiRequest('GET', "/api/attachments/{$id}/download/", $token);
// ✅ $this->apiRequest('GET', $attachment['downloadUrl'], $token);
```

---

## Checklist de diagnostic rapide

```
Test échoue avec 500 ?
  └─ Lire var/log/test.log → chercher "CRITICAL" ou "Exception"
     ├─ "file does not exist" → Erreur 1 (capturer métadonnées avant move())
     └─ Autre exception → corriger la source du problème

Test échoue sur assertCount ?
  ├─ count > attendu sur une collection API Platform → Erreur 2 (utiliser ['member'])
  └─ count = 0 mais données existent → Erreur 5 (cache EntityManager / disableReboot)

Test échoue avec getContent() = false ?
  └─ BinaryFileResponse → Erreur 3 (vérifier headers ou ob_start)

Tests échouent après plusieurs runs mais pas sur DB fraîche ?
  └─ make test-db-reset → Erreur 4 (isolation entre sessions)

Test échoue avec 301 ?
  └─ Erreur 6 (utiliser l'URL générée par le provider, pas une URL hardcodée)
```

---

## Commandes de debug utiles

```bash
# Lancer UN test avec logs Symfony
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php sh -c \
  'bin/phpunit --filter nom_du_test && cat var/log/test.log | tail -30'

# Reset DB + tests d'un BC
make test-db-reset && \
  docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/phpunit --group attachment --testdox

# PHPStan sur un BC avant de chercher le bug dans les tests
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php vendor/bin/phpstan analyse src/Attachment --memory-limit=512M

# Voir les routes du BC en test
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/console debug:router --env=test | grep attachment

# Vérifier le schéma en sync
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/console doctrine:schema:update --force --env=test --no-interaction
```
