---
name: test:first
description: "Workflow TDD Red→Green : écrire les tests avant le code, avec structure DDD/Hexagonale et config phpunit.xml.dist"
argument-hint: [entity|service|usecase]
triggers:
  - test first
  - tdd
  - red green
  - red green refactor
  - test avant code
  - phpunit.xml
  - test suite
  - unit vs integration
  - premiers tests
---

# Test First — TDD Red→Green (Symfony 7.3 + DDD)

Écrire les tests **avant** le code de production. Le test échoue (rouge), on implémente le minimum pour le passer (vert), puis on améliore (refactor).

## Ce qu'on teste en Unitaire vs ce qu'on ne teste PAS

| Layer | On teste | Type | Outils |
|-------|----------|------|--------|
| Domain | Entités, Value Objects, règles métier (`if ($price < 0)`) | **Unitaire** | PHPUnit pur — pas de mock, pas de container |
| Application | Services/Handlers, orchestration, appels aux repositories | **Unitaire** | PHPUnit + Mocks |
| Infrastructure | Contrôleurs API Platform, Repositories Doctrine | **Intégration** | ApiPlatform Test Client (KernelBrowser) |

## Structure DDD cible

```
src/
├── Domain/
│   ├── Entity/          ← Cœur métier (PHP pur, sans Symfony)
│   ├── Exception/       ← Exceptions métier (DomainException)
│   └── Interface/       ← Ports (interfaces des repositories)
├── Application/
│   └── UseCase/         ← Cas d'utilisation (orchestration)
└── Infrastructure/      ← Doctrine, API Platform, Symfony

tests/
├── Unit/
│   ├── Domain/
│   │   └── Entity/      ← Tester les règles métier
│   └── Application/
│       └── UseCase/     ← Tester l'orchestration avec mocks
└── Integration/
    └── Api/             ← Tester les endpoints HTTP
```

## Configuration phpunit.xml.dist (séparation Unit / Integration)

**Créer ou remplacer** `phpunit.xml.dist` à la racine :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         stopOnFailure="false"
>
    <testsuites>
        <!-- Tests unitaires : rapides, sans infrastructure -->
        <testsuite name="unit">
            <directory>tests/Unit</directory>
        </testsuite>

        <!-- Tests d'intégration : BDD, HTTP, OAuth2 -->
        <testsuite name="integration">
            <directory>tests/Integration</directory>
        </testsuite>

        <!-- Tous les tests -->
        <testsuite name="all">
            <directory>tests/Unit</directory>
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>

    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="APP_ENV" value="test" force="true"/>
        <server name="SHELL_VERBOSITY" value="-1"/>
    </php>
</phpunit>
```

**Commandes résultantes :**
```bash
php bin/phpunit --testsuite unit         # Tests unitaires seuls (< 50ms)
php bin/phpunit --testsuite integration  # Tests d'intégration seuls
php bin/phpunit --testsuite all          # Tout
php bin/phpunit --group prod             # Tests critiques (pre-push)
```

---

## Workflow TDD : Rouge → Vert → Refactor

### Étape 0 : Écrire le test (il est ROUGE — ça ne compile pas encore)

```php
// tests/Unit/Domain/Entity/ProductTest.php
<?php

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Product;
use DomainException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('prod')]
#[Group('unit')]
final class ProductTest extends TestCase
{
    // Happy Path
    public function testProductIsCreatedSuccessfully(): void
    {
        $product = new Product('iPhone 15', 999.99);

        $this->assertSame('iPhone 15', $product->getName());
        $this->assertEquals(999.99, $product->getPrice());
    }

    // Règle métier : prix négatif interdit
    public function testCannotCreateProductWithNegativePrice(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Le prix ne peut pas être négatif.");

        new Product('Bad Product', -10);
    }

    // Changement d'état valide
    public function testChangePriceWithValidAmount(): void
    {
        $product = new Product('iPhone 15', 1000);
        $product->changePrice(800);

        $this->assertEquals(800, $product->getPrice());
    }

    // Changement d'état invalide
    public function testCannotChangePriceToNegativeAmount(): void
    {
        $this->expectException(DomainException::class);

        $product = new Product('iPhone 15', 1000);
        $product->changePrice(-50);
    }
}
```

```bash
php bin/phpunit --testsuite unit
# → ROUGE : "Class App\Domain\Entity\Product not found"
```

### Étape 1 : Implémenter le minimum pour passer au VERT

```php
// src/Domain/Entity/Product.php
<?php

namespace App\Domain\Entity;

use DomainException;

class Product
{
    public function __construct(
        private string $name,
        private float $price
    ) {
        $this->ensurePriceIsValid($price);
    }

    public function changePrice(float $newPrice): void
    {
        $this->ensurePriceIsValid($newPrice);
        $this->price = $newPrice;
    }

    private function ensurePriceIsValid(float $price): void
    {
        if ($price < 0) {
            throw new DomainException("Le prix ne peut pas être négatif.");
        }
    }

    public function getPrice(): float { return $this->price; }
    public function getName(): string { return $this->name; }
}
```

```bash
php bin/phpunit --testsuite unit
# → VERT : 4 tests, 0 failures
```

---

## Tester la Service Layer (Application) avec Mock

### Étape 0 : Le Port (Interface)

```php
// src/Domain/Interface/ProductRepositoryInterface.php
<?php

namespace App\Domain\Interface;

use App\Domain\Entity\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;
}
```

### Étape 1 : Écrire le test du Service (ROUGE)

```php
// tests/Unit/Application/UseCase/CreateProductServiceTest.php
<?php

namespace App\Tests\Unit\Application\UseCase;

use App\Application\UseCase\CreateProductService;
use App\Domain\Entity\Product;
use App\Domain\Interface\ProductRepositoryInterface;
use DomainException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('prod')]
#[Group('unit')]
final class CreateProductServiceTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $repositoryMock;
    private CreateProductService $service;

    protected function setUp(): void
    {
        // PHPUnit génère un faux repository en mémoire
        $this->repositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->service = new CreateProductService($this->repositoryMock);
    }

    // Arrange → Act → Assert
    public function testItCreatesAndSavesProduct(): void
    {
        // On vérifie que save() est appelé exactement 1 fois avec un Product
        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Product::class));

        $product = $this->service->execute('Samsung S24', 850.00);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Samsung S24', $product->getName());
        $this->assertEquals(850.00, $product->getPrice());
    }

    // Le service propage l'exception du Domain
    public function testItPropagatesDomainExceptionForNegativePrice(): void
    {
        // save() ne doit JAMAIS être appelé si le Domain lève une exception
        $this->repositoryMock->expects($this->never())->method('save');

        $this->expectException(DomainException::class);

        $this->service->execute('Bad Product', -10.00);
    }
}
```

```bash
php bin/phpunit --testsuite unit
# → ROUGE : "Class App\Application\UseCase\CreateProductService not found"
```

### Étape 2 : Implémenter le Service (VERT)

```php
// src/Application/UseCase/CreateProductService.php
<?php

namespace App\Application\UseCase;

use App\Domain\Entity\Product;
use App\Domain\Interface\ProductRepositoryInterface;

class CreateProductService
{
    public function __construct(
        private ProductRepositoryInterface $repository
    ) {}

    public function execute(string $name, float $price): Product
    {
        // 1. Déléguer la logique métier au Domain
        $product = new Product($name, $price);

        // 2. Persister via le Port (jamais Doctrine directement ici)
        $this->repository->save($product);

        return $product;
    }
}
```

```bash
php bin/phpunit --testsuite unit
# → VERT : 6 tests, 0 failures
```

---

## Règles TDD dans ce projet

- **Rouge d'abord** — ne jamais créer `src/` avant `tests/`
- **Minimum viable** — implémenter le strict nécessaire pour passer au vert
- **Un comportement = un test** — pas de mega-test qui vérifie tout
- **Pas d'infrastructure** en tests unitaires (pas de `KernelTestCase`, pas de BDD)
- **Mock les interfaces** (repositories, services externes), jamais les classes concrètes
- **`#[Group('prod')]`** sur tous les tests unitaires — doivent passer avant chaque push

## Checklist TDD

- [ ] Test écrit **avant** le code de production
- [ ] Test est ROUGE (erreur ou échec confirmé)
- [ ] Implémentation minimale pour passer au VERT
- [ ] Refactor si nécessaire (sans casser les tests)
- [ ] Commit séparé : `test: add ProductTest` puis `feat: implement Product entity`
- [ ] `php bin/phpunit --testsuite unit` passe en < 1 seconde

## Voir aussi

- `test:unit` — Patterns avancés (DataProvider, domain events, Value Objects)
- `test:integration` — Tester les endpoints API Platform avec OAuth2
- `pattern:cqrs-handler` — Tester les Command/Query Handlers Symfony Messenger


