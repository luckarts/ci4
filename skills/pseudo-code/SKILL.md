---
name: pseudo-code
description: "Traduire un pseudo-code commenté en Processor API Platform — flow standard : assert → findProject → auth → new Entity → save → transformer → return Resource"
argument-hint: [create|update|delete]
triggers:
  - pseudo code
  - pseudo-code
  - pseudocode
  - // va cherche
  - // cherche le projet
  - // new Entity
  - // save repository
  - // retourne resource
  - flow processor
  - template processor
  - squelette processor
---

# Pseudo-code → Processor API Platform

Ce skill traduit un pseudo-code commenté en `ProcessorInterface` concret,
en appliquant les conventions du projet (DDD, PHPStan level 6, Security facade).

---

## Flow standard (Create)

```
// assert $data instanceof XxxResource
// récupère l'id (uriVariables ou $data->xyzId)
// cherche l'entité parente (ex: Project)
// si null → NotFoundHttpException
// user autorisé ? → isGranted() sinon AccessDeniedHttpException
// new Entity(champs, parent)
// repository->save()
// transformer->toResource($entity)
// retourne Resource
```

**Traduit en :**

```php
<?php

declare(strict_types=1);

namespace App\{BC}\Infrastructure\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Project\Domain\Contract\ProjectRepositoryInterface;
use App\Project\Infrastructure\Security\PersonalProjectVoter;
use App\{BC}\Domain\Contract\{Entity}RepositoryInterface;
use App\{BC}\Domain\Entity\{Entity};
use App\{BC}\Infrastructure\ApiPlatform\Resource\{Entity}Resource;
use App\{BC}\Infrastructure\ApiPlatform\Transformer\{Entity}ResourceTransformer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<{Entity}Resource, {Entity}Resource>
 */
final class Create{Entity}Processor implements ProcessorInterface
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
        private readonly {Entity}RepositoryInterface ${entity}Repository,
        private readonly {Entity}ResourceTransformer $transformer,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): {Entity}Resource
    {
        assert($data instanceof {Entity}Resource);

        // 1. Récupérer le projet (depuis uriVariables si sous-ressource, sinon depuis $data)
        $projectId = (string) ($uriVariables['projectId'] ?? $data->projectId ?? '');
        $project = $this->projectRepository->findById($projectId);

        if (null === $project) {
            throw new NotFoundHttpException(sprintf('Project "%s" not found.', $projectId));
        }

        // 2. Autorisation
        if (!$this->security->isGranted(PersonalProjectVoter::PROJECT_EDIT, $project)) {
            throw new AccessDeniedHttpException();
        }

        // 3. Créer l'entité
        $entity = new {Entity}($data->name, $project);

        // 4. Persister
        $this->{entity}Repository->save($entity);

        // 5. Retourner le Resource DTO
        return $this->transformer->toResource($entity);
    }
}
```

---

## Variantes du flow

### Pas de projet parent (ressource top-level)

```php
// Supprimer les étapes 1 et 2
// L'autorisation porte sur l'org ou est implicite (authenticated)
$org = $this->orgRepository->findById($uriVariables['orgId'] ?? '');
if (null === $org) {
    throw new NotFoundHttpException('Organization not found.');
}
if (!$this->security->isGranted(OrganizationVoter::MANAGE, $org)) {
    throw new AccessDeniedHttpException();
}
```

### Update (PUT / PATCH)

```php
// $data est l'entité chargée par le Provider (read: true)
assert($data instanceof {Entity});

if (!$this->security->isGranted(ProjectVoter::EDIT, $data->getProject())) {
    throw new AccessDeniedHttpException();
}

$data->update($resource->name);         // méthode domain
$this->{entity}Repository->save($data);

return $this->transformer->toResource($data);
```

### Delete

```php
// Processor avec read: false → $data est le Resource, pas l'entité
// Charger manuellement l'entité depuis uriVariables
$entity = $this->{entity}Repository->findById($uriVariables['id'] ?? '');
if (null === $entity) {
    throw new NotFoundHttpException('{Entity} not found.');
}
if (!$this->security->isGranted(ProjectVoter::DELETE, $entity->getProject())) {
    throw new AccessDeniedHttpException();
}
$this->{entity}Repository->remove($entity);
// pas de return (void ou null)
```

---

## Règles invariantes

| Règle | Détail |
|-------|--------|
| `assert($data instanceof XxxResource)` | Toujours en première ligne — PHPStan narrow |
| `isGranted()` jamais `denyAccessUnlessGranted()` | PHPStan level 6 ne reconnaît pas cette méthode sur Security |
| `findById()` retourne `?Entity` | Toujours vérifier `null` → `NotFoundHttpException` |
| `save()` dans le repo, pas `flush()` direct | Encapsulation Doctrine dans l'infra |
| Pas de try/catch | `ExceptionSubscriber` global gère les exceptions HTTP — voir skill `exception-handling` |
| `declare(strict_types=1)` | Obligatoire en tête de fichier |

---

## Checklist après implémentation

- [ ] `assert($data instanceof XxxResource)` présent
- [ ] `findById` → check `null` → `NotFoundHttpException`
- [ ] `isGranted()` → check `false` → `AccessDeniedHttpException`
- [ ] `new Entity(...)` avec les bons arguments du constructeur
- [ ] `repository->save($entity)` appelé
- [ ] `transformer->toResource($entity)` retourné
- [ ] PHPStan : `make phpstan` sans erreur
- [ ] Test E2E : happy path + 403 + 404 couverts

---

## Voir aussi

- `skills/authorization/SKILL.md` — Voters, matrix de permissions
- `skills/exception-handling/SKILL.md` — pas de try/catch dans les Processors
- `skills/patterns/resource-transformer/SKILL.md` — transformer Entity → Resource DTO
