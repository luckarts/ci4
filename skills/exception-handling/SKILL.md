---
name: exception-handling
description: "ExceptionSubscriber global pour mapper les exceptions domaine vers des codes HTTP — plus de catch dans les Processors"
argument-hint: [setup|map|ajouter]
triggers:
  - exception handling
  - exception subscriber
  - catch processor
  - 500 erreur domaine
  - mapper exception http
  - problem details
  - unprocessable entity
  - exception listener
---

# Exception Handling — ExceptionSubscriber global

**Probleme :** chaque Processor catch ses exceptions metier manuellement.
Avec 15 BCs × 5 Processors = 75 blocs catch a maintenir.

```php
// ❌ Pattern a eviter — catch dans chaque Processor
} catch (OrganizationSlugAlreadyExistsException $e) {
    throw new UnprocessableEntityHttpException($e->getMessage(), $e);
}
```

**Solution :** un seul `ExceptionSubscriber` dans `Shared/` qui gere tout.
Les Processors ne catchent plus rien — ils laissent les exceptions domaine remonter.

---

## Implementation

### 1. ExceptionSubscriber

```php
// src/Shared/Infrastructure/Api/ExceptionSubscriber.php

namespace App\Shared\Infrastructure\Api;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ExceptionSubscriber implements EventSubscriberInterface
{
    private const MAP = [
        // User BC
        \App\User\Domain\Exception\UserAlreadyExistsException::class       => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Organization BC
        \App\Organization\Domain\Exception\OrganizationNotFoundException::class        => Response::HTTP_NOT_FOUND,
        \App\Organization\Domain\Exception\OrganizationSlugAlreadyExistsException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \App\Organization\Domain\Exception\MemberAlreadyExistsException::class         => Response::HTTP_CONFLICT,
        // Shared
        \App\Shared\Domain\Exception\AccessDeniedException::class          => Response::HTTP_FORBIDDEN,
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $statusCode = self::MAP[$exception::class] ?? null;

        if ($statusCode === null) {
            return; // laisser Symfony/API Platform gerer les autres exceptions
        }

        $event->setResponse(new JsonResponse([
            'type'   => 'https://tools.ietf.org/html/rfc9110#section-15.5',
            'title'  => $exception->getMessage(),
            'status' => $statusCode,
        ], $statusCode));
    }
}
```

### 2. Enregistrement (autoconfigure suffit)

```yaml
# config/services.yaml
App\Shared\Infrastructure\Api\ExceptionSubscriber:
    tags:
        - { name: kernel.event_subscriber }
```

Avec `autoconfigure: true` global, le tag est automatique — rien a ajouter.

### 3. Processor apres refactor

```php
// ✅ Processor propre — zero catch
public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrganizationResource
{
    $organization = $this->organizationService->create(
        $data->name,
        $data->slug,
        $this->security->getUser()
    );

    return OrganizationResource::fromEntity($organization);
}
// L'exception OrganizationSlugAlreadyExistsException remonte → ExceptionSubscriber → 422
```

---

## Table de mapping recommandee par type

| Type d'exception domaine | Code HTTP | Raison |
|--------------------------|-----------|--------|
| `NotFoundException` | 404 | Ressource inexistante |
| `AlreadyExistsException` | 409 Conflict | Doublon |
| `SlugAlreadyExistsException` | 422 Unprocessable | Contrainte metier |
| `ValidationException` | 422 Unprocessable | Regles domaine violees |
| `AccessDeniedException` | 403 Forbidden | Permission insuffisante |
| `InvalidStateTransitionException` | 409 Conflict | Workflow invalide |
| `OptimisticLockException` | 409 Conflict | Conflit concurrent |

---

## Convention de nommage des exceptions domaine

```
src/{BC}/Domain/Exception/
├── {Resource}NotFoundException.php
├── {Resource}AlreadyExistsException.php
├── {Resource}SlugAlreadyExistsException.php
└── Invalid{Resource}StateException.php
```

Toutes les exceptions domaine etendent `\DomainException` ou `\RuntimeException` — jamais les exceptions HTTP Symfony.

---

## Ce qu'on NE fait PAS

```php
// ❌ Importer les exceptions HTTP dans le Domain
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

// ❌ Throw HTTP exception dans un Service
throw new NotFoundHttpException('Organization not found');

// ❌ Catch generique qui avale tout
} catch (\Exception $e) {
    throw new BadRequestHttpException($e->getMessage());
}
```

Le domaine ne connait pas HTTP. C'est l'Infrastructure (ExceptionSubscriber) qui traduit.

---

## Priorite du subscriber

```php
// Priorite 10 → s'execute avant le handler par defaut de Symfony (priorite 0)
// API Platform a son propre handler — le subscriber doit avoir une priorite plus haute
return [KernelEvents::EXCEPTION => ['onKernelException', 10]];
```

Si API Platform intercepte deja l'exception, augmenter la priorite a `20`.

---

## Reference

- `Sylius.md` point 15 — exception handling ad-hoc, probleme des 75 catch blocks
- `error.md` — cas concret `UserAlreadyExistsException → 422`
- `Shared/Infrastructure/Api/ExceptionSubscriber.php` — fichier a creer
