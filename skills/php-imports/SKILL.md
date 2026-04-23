---
name: php-imports
description: "Toujours déclarer un `use` en haut du fichier — jamais de FQCN inline dans le code"
triggers:
  - use statement
  - fqcn inline
  - fully qualified class name
  - backslash devant classe
  - import manquant
  - TagResource
  - use App\
---

# Conventions d'imports PHP

## Règle absolue

**Toujours** déclarer un `use` en haut du fichier PHP.
**Jamais** de FQCN (Fully Qualified Class Name) inline dans le corps du code.

---

## ❌ Interdit

```php
// FQCN inline dans le corps du code
public function __construct(
    private \App\Task\Infrastructure\ApiPlatform\Resource\TagResource $tag,
) {}

// ou dans un docblock / attribut
#[\App\Task\Infrastructure\ApiPlatform\Resource\TagResource]
```

## ✅ Correct

```php
use App\Task\Infrastructure\ApiPlatform\Resource\TagResource;

// puis utilisation courte partout
public function __construct(
    private TagResource $tag,
) {}
```

---

## Pourquoi

- Lisibilité : le code métier n'est pas pollué par les namespaces complets.
- Cohérence : tous les fichiers du projet suivent la même convention.
- Refactoring : déplacer une classe = changer le `use`, pas N occurrences inline.
- PHPStan / CS-Fixer : les FQCN inline peuvent déclencher des warnings de style.

---

## Cas courants dans ce projet

| Classe fréquente | Import correct |
|---|---|
| `TagResource` | `use App\Task\Infrastructure\ApiPlatform\Resource\TagResource;` |
| `TaskResource` | `use App\Task\Infrastructure\ApiPlatform\Resource\TaskResource;` |
| `MilestoneResource` | `use App\Project\Infrastructure\ApiPlatform\Resource\MilestoneResource;` |
| `Link` (API Platform) | `use ApiPlatform\Metadata\Link;` |
| `UuidGenerator` | `use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;` |

---

## Checklist avant commit

- [ ] Aucun `\App\` ou `\Symfony\` inline dans le corps des méthodes
- [ ] Tous les `use` sont en haut du fichier, après `namespace`
- [ ] Pas de FQCN dans les attributs PHP 8 (`#[Route(...)]`, `#[ORM\Entity]`, etc.) — utiliser le short name via `use`
