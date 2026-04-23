---
name: soft-delete
description: "Soft Delete avec SoftDeletableTrait + Doctrine Filter global — eviter les suppressions irreversibles"
argument-hint: [setup|filter|restaurer]
triggers:
  - soft delete
  - suppression irreversible
  - deleted at
  - hard delete
  - doctrine filter
  - SoftDeletableTrait
  - restaurer entite
  - cascade delete
  - corbeille
---

# Soft Delete — SoftDeletableTrait + Doctrine Filter

**Probleme :** `DELETE` en base = donnees irrecuperables.
Supprimer une Organization en cascade supprime tous ses projets, taches, membres, logs.

**Solution :** marquer comme supprime (`deleted_at IS NOT NULL`) + Doctrine Filter global qui exclut automatiquement les entites supprimees.

---

## 1. Le Trait partagé

```php
// src/Shared/Domain/Trait/SoftDeletableTrait.php

namespace App\Shared\Domain\Trait;

use Doctrine\ORM\Mapping as ORM;

trait SoftDeletableTrait
{
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function softDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
```

---

## 2. Appliquer sur une entite

```php
// src/Organization/Domain/Entity/Organization.php

#[ORM\Entity]
class Organization
{
    use SoftDeletableTrait;

    // ... reste de l'entite
}
```

La migration ajoute juste une colonne nullable :

```php
// migrations/VersionXXX.php
$this->addSql('ALTER TABLE organization ADD deleted_at TIMESTAMPTZ DEFAULT NULL');
$this->addSql('ALTER TABLE project ADD deleted_at TIMESTAMPTZ DEFAULT NULL');
$this->addSql('ALTER TABLE task ADD deleted_at TIMESTAMPTZ DEFAULT NULL');
```

---

## 3. Doctrine Filter global

Le filtre s'applique automatiquement a toutes les requetes — zero modification dans les Repositories.

```php
// src/Shared/Infrastructure/Doctrine/Filter/SoftDeleteFilter.php

namespace App\Shared\Infrastructure\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

final class SoftDeleteFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->reflClass->hasTrait(\App\Shared\Domain\Trait\SoftDeletableTrait::class)) {
            return '';
        }

        return $targetTableAlias . '.deleted_at IS NULL';
    }
}
```

### Enregistrement dans Doctrine

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        filters:
            soft_delete:
                class: App\Shared\Infrastructure\Doctrine\Filter\SoftDeleteFilter
                enabled: true
```

---

## 4. Le Processor

```php
// src/Organization/Infrastructure/ApiPlatform/State/Processor/DeleteOrganizationProcessor.php

public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
{
    $organization = $this->organizationRepository->find($uriVariables['id']);

    if (!$organization) {
        throw new OrganizationNotFoundException();
    }

    // ✅ Soft delete — pas de DELETE SQL
    $organization->softDelete();
    $this->organizationRepository->save($organization);
}
```

---

## 5. Desactiver le filtre ponctuellement

Pour les taches d'administration (restauration, purge, rapport) :

```php
// Recuperer les entites supprimees (admin, purge RGPD)
$this->entityManager->getFilters()->disable('soft_delete');
$deleted = $this->organizationRepository->findAll(); // inclut les supprimees
$this->entityManager->getFilters()->enable('soft_delete');
```

Ou dans un Repository dedie :

```php
public function findDeleted(): array
{
    $this->entityManager->getFilters()->disable('soft_delete');
    $result = $this->createQueryBuilder('o')
        ->where('o.deletedAt IS NOT NULL')
        ->getQuery()
        ->getResult();
    $this->entityManager->getFilters()->enable('soft_delete');
    return $result;
}
```

---

## 6. Purge RGPD — suppression physique apres TTL

Le soft delete ne dispense pas d'une purge eventuelle pour la conformite RGPD :

```php
// Commande Symfony a lancer en cron
// src/Shared/Infrastructure/Command/PurgeDeletedEntitiesCommand.php

$cutoff = new \DateTimeImmutable('-90 days');

$this->entityManager->getFilters()->disable('soft_delete');
$this->entityManager->createQuery(
    'DELETE FROM App\Organization\Domain\Entity\Organization o
     WHERE o.deletedAt < :cutoff'
)->setParameter('cutoff', $cutoff)->execute();
$this->entityManager->getFilters()->enable('soft_delete');
```

---

## Quelles entites soft-deleter

| Entite | Soft Delete | Raison |
|--------|-------------|--------|
| `Organization` | Oui | Cascade enorme, donnees critiques |
| `Project` | Oui | Archive utile |
| `Task` | Oui | Historique, audit |
| `User` | Oui | RGPD — anonymisation differee |
| `OrganizationMember` | Non | Relation, un DELETE direct suffit |
| `TeamMember` | Non | Relation simple |
| `AuditLog` | Non | Jamais supprimer un log d'audit |

---

## Ce qu'on NE fait PAS

```php
// ❌ Hard delete avec cascade
$this->entityManager->remove($organization);
$this->entityManager->flush();
// → supprime org + tous ses projets + toutes ses taches

// ❌ Filtre manuel dans chaque Repository
->where('o.deletedAt IS NULL') // → oublie possible, duplication
```

---

## Reference

- `Sylius.md` point 20 — hard delete partout, donnees irrecuperables
- `Shared/Domain/Trait/SoftDeletableTrait.php` — fichier a creer
- `Shared/Infrastructure/Doctrine/Filter/SoftDeleteFilter.php` — fichier a creer
