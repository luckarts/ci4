---
name: pattern:resource-transformer
description: "Extraire la conversion Entity→Resource DTO dans un service dédié injecté — remplace les méthodes statiques dans les Providers/Processors"
argument-hint: [transformer|toResource|entity to dto|mapping]
triggers:
  - resource transformer
  - toResource statique
  - extract transformer
  - entity to dto
  - mapping entity resource
  - static toResource
  - BoardColumnResourceTransformer
  - ProjectResourceTransformer
  - TaskResourceTransformer
---

# Pattern: Resource Transformer

Extrait la logique de conversion `Entity → Resource DTO` dans une classe dédiée injectée, au lieu de la laisser comme méthode statique (ou inline) dans les Providers/Processors.

## Quand utiliser

- Un Provider ou Processor contient une méthode `toResource()` statique ou privée
- La même conversion Entity→Resource est dupliquée dans plusieurs Providers (collection + item + processor)
- Tu ajoutes un champ au Resource DTO et tu dois le mettre à jour à plusieurs endroits

## Quand NE PAS utiliser

- La conversion est triviale et n'est utilisée qu'en un seul endroit (YAGNI)
- Le mapping est déjà géré par `EntityDtoMapper` avec config YAML (scalaires simples)

## Structure

```
src/{BC}/Infrastructure/ApiPlatform/
├── Transformer/
│   └── {Entity}ResourceTransformer.php   ← classe finale, service autowired
├── State/
│   ├── Provider/
│   │   ├── {Entity}CollectionProvider.php  ← injecte le transformer
│   │   └── {Entity}ItemProvider.php        ← injecte le transformer
│   └── Processor/
│       └── Update{Entity}Processor.php     ← injecte le transformer
└── Resource/
    └── {Entity}Resource.php
```

## Implementation

### 1. Créer le Transformer

```php
// src/Project/Infrastructure/ApiPlatform/Transformer/BoardColumnResourceTransformer.php

declare(strict_types=1);

namespace App\Project\Infrastructure\ApiPlatform\Transformer;

use App\Project\Domain\Entity\BoardColumn;
use App\Project\Infrastructure\ApiPlatform\Resource\BoardColumnResource;

final class BoardColumnResourceTransformer
{
    public function toResource(BoardColumn $boardColumn): BoardColumnResource
    {
        $resource = new BoardColumnResource();
        $resource->id        = (string) $boardColumn->getId();
        $resource->title     = $boardColumn->getTitle();
        $resource->position  = $boardColumn->getPosition();
        $resource->wipLimit  = $boardColumn->getWipLimit();
        $resource->isDefault = $boardColumn->isDefault();
        $resource->projectId = (string) $boardColumn->getProject()->getId();
        $resource->createdAt = $boardColumn->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $resource->updatedAt = $boardColumn->getUpdatedAt()->format(\DateTimeInterface::ATOM);

        return $resource;
    }
}
```

- Classe `final`, sans interface (un seul implémenteur attendu)
- Autowired automatiquement par Symfony (pas de config manuelle)
- Enumérations mappées manuellement (`->value`)

### 2. Injecter dans le Provider

```php
// AVANT — méthode statique couplée au Provider
class BoardColumnCollectionProvider implements ProviderInterface
{
    public function provide(...): array
    {
        foreach ($columns as $column) {
            $resources[] = BoardColumnCollectionProvider::toResource($column); // ❌
        }
        return $resources;
    }

    private static function toResource(BoardColumn $col): BoardColumnResource { ... }
}

// APRÈS — transformer injecté
class BoardColumnCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly BoardColumnRepositoryInterface $boardColumnRepository,
        private readonly BoardColumnResourceTransformer $transformer, // ✅
        private readonly Security $security,
    ) {}

    public function provide(...): array
    {
        foreach ($columns as $column) {
            $resources[] = $this->transformer->toResource($column); // ✅
        }
        return $resources;
    }
}
```

### 3. Réutiliser dans tous les Providers/Processors du même BC

```php
// ItemProvider
$resources[] = $this->transformer->toResource($column);

// UpdateProcessor — retourner le resource après persist
return $this->transformer->toResource($updatedColumn);
```

## Checklist refacto

- [ ] Créer `src/{BC}/Infrastructure/ApiPlatform/Transformer/{Entity}ResourceTransformer.php`
- [ ] Injecter `{Entity}ResourceTransformer $transformer` dans chaque Provider/Processor concerné
- [ ] Remplacer tous les appels `ClassName::toResource()` ou méthodes inline par `$this->transformer->toResource()`
- [ ] Supprimer les méthodes statiques/privées `toResource()` des Providers/Processors
- [ ] PHPStan : `make phpstan` doit passer (level 6)

## Référence projet

- `src/Project/Infrastructure/ApiPlatform/Transformer/BoardColumnResourceTransformer.php`
- `src/Project/Infrastructure/ApiPlatform/Transformer/ProjectResourceTransformer.php`
- `src/Task/Infrastructure/ApiPlatform/Transformer/TaskResourceTransformer.php`
