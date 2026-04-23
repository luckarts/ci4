---
name: pattern:repository
description: "Repository pattern - Encapsulation des requetes d'acces aux donnees"
argument-hint: [repository|query|doctrine]
triggers:
  - repository
  - doctrine repository
  - requete metier
  - query builder
  - findBy
  - acces donnees
  - entitymanager direct
  - inject entitymanager
  - n+1
  - trop de requetes sql
  - lazy loading
  - addSelect
  - join relation
---

# Pattern: Repository

Encapsule les requetes d'acces aux donnees derriere une interface metier. Isole la couche de persistance du domaine.

## Quand utiliser

- Toujours pour les entites Doctrine (c'est le pattern par defaut)
- Requetes complexes avec QueryBuilder
- Besoin d'une interface pour decoupler du ORM (testabilite)

## Quand NE PAS utiliser

- Requetes triviales → `findOneBy()` herite de base suffit
- Lectures simples exposees via API Platform → le Provider par defaut suffit

## Anti-patterns à éviter

### ❌ Accès direct à l'EntityManager ou aux repositories dans plusieurs classes

Appeler `$entityManager->getRepository(Foo::class)` ou injecter `EntityManagerInterface`
dans des Processors/Providers/Services crée un couplage fort à Doctrine et rend les tests difficiles.

**Problème :**
```php
// ❌ Mauvais — couplage direct à Doctrine dans un Processor
class CreateTaskProcessor implements ProcessorInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function process(mixed $data, ...): mixed
    {
        $project = $this->em->getRepository(Project::class)->find($data->projectId);
        // ...
        $this->em->persist($task);
        $this->em->flush();
    }
}
```

**Solution :** injecter les interfaces de repository (Domain/Contract) + un service métier :
```php
// ✅ Bien — couplage via interface, logique dans un service dédié
class CreateTaskProcessor implements ProcessorInterface
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private TaskServiceInterface $taskService,
    ) {}

    public function process(mixed $data, ...): mixed
    {
        $project = $this->projectRepository->findById($data->projectId);
        return $this->taskService->create($project, $data);
    }
}
```

## Structure

```
src/
  Repository/
    OrderRepositoryInterface.php        # Interface (domaine)
    OrderRepository.php                 # Implementation (infrastructure)
    CustomerRepositoryInterface.php
    CustomerRepository.php
```

## Implementation

### 1. Interface (couche domaine)

```php
namespace App\Repository;

interface OrderRepositoryInterface
{
    public function findByToken(string $token): ?Order;

    public function findCartByCustomer(Customer $customer): ?Order;

    /** @return Order[] */
    public function findCompletedInPeriod(\DateTimeInterface $from, \DateTimeInterface $to): array;

    public function countByCustomer(Customer $customer): int;

    public function getTotalRevenue(): int;
}
```

### 2. Implementation Doctrine

```php
namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByToken(string $token): ?Order
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findCartByCustomer(Customer $customer): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->andWhere('o.status = :status')
            ->setParameter('customer', $customer)
            ->setParameter('status', Order::STATE_CART)
            ->orderBy('o.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findCompletedInPeriod(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.completedAt BETWEEN :from AND :to')
            ->setParameter('status', Order::STATE_FULFILLED)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('o.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCustomer(Customer $customer): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.customer = :customer')
            ->andWhere('o.status != :cart')
            ->setParameter('customer', $customer)
            ->setParameter('cart', Order::STATE_CART)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalRevenue(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('SUM(o.total)')
            ->where('o.status = :status')
            ->setParameter('status', Order::STATE_FULFILLED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
```

### 3. Éviter le problème N+1

Le problème N+1 survient quand Doctrine exécute **1 requête** pour la liste + **N requêtes**
pour chaque relation lazy-loaded. Se détecte avec Symfony Profiler (> 10 requêtes sur un listing).

**Problème :**
```php
// ❌ Provoque N+1 : 1 requête pour les tasks + 1 par task pour charger son project
$tasks = $this->findAll();
foreach ($tasks as $task) {
    echo $task->getProject()->getName(); // lazy load → requête SQL à chaque iteration
}
```

**Solution :** `JOIN` + `addSelect()` dans le repository :
```php
// ✅ Une seule requête SQL avec JOIN
public function findAllWithProject(): array
{
    return $this->createQueryBuilder('t')
        ->leftJoin('t.project', 'p')
        ->addSelect('p')                 // ← charge 'project' dans la même requête
        ->leftJoin('t.assignee', 'u')
        ->addSelect('u')
        ->getQuery()
        ->getResult();
}
```

**Règle :** chaque méthode de listing dans un repository doit JOINer toutes les relations
qui seront utilisées dans la réponse API (Provider/DTO mapping).

### 4. QueryBuilder reutilisable (pour les listings)

```php
class OrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function createListQueryBuilder(string $locale): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.customer', 'c')
            ->andWhere('o.status != :cart')
            ->setParameter('cart', Order::STATE_CART);
    }

    public function createByCustomerQueryBuilder(Customer $customer): QueryBuilder
    {
        return $this->createListQueryBuilder('fr')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer);
    }
}
```

### 4. Enregistrement

```yaml
# config/services.yaml
services:
    App\Repository\OrderRepositoryInterface:
        alias: App\Repository\OrderRepository
```

Ou via l'attribut sur l'entite :

```php
#[ORM\Entity(repositoryClass: OrderRepository::class)]
class Order
{
    // ...
}
```

## Reference Sylius

- `OrderRepository.php` — `findCartByTokenValue`, `createListQueryBuilder`
- `CustomerRepository.php` — `countCustomers`, `countCustomersInPeriod`, `findLatest`
