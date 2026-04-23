---
name: pattern:decorator
description: "Decorator pattern - Enrichir des services existants sans modifier leur code"
argument-hint: [factory|processor|service]
triggers:
  - decorator
  - decorer
  - enrichir service
  - wrapper
  - extend processor
---

# Pattern: Decorator

Enrichit un service existant en l'encapsulant dans un autre qui ajoute du comportement, sans modifier le code original.

## Quand utiliser

- Ajouter un comportement a un service tiers/vendor sans le modifier
- Enrichir une factory avec des methodes supplementaires
- Ajouter du pre/post-traitement a un processor API Platform

## Quand NE PAS utiliser

- On peut simplement heriter (et l'heritage a du sens)
- Le service a decorer est le notre et on peut le modifier directement

## Structure

```
src/
  Factory/
    CartItemFactory.php          # Decore la factory de base
  State/
    Processor/
      HashPasswordProcessor.php  # Decore le persist processor
```

## Implementation

### 1. Decorator de Factory

```php
namespace App\Factory;

use App\Entity\CartItem;
use App\Entity\Product;

class CartItemFactory implements CartItemFactoryInterface
{
    public function __construct(
        private FactoryInterface $decoratedFactory,
    ) {}

    // Methode de base deleguee
    public function createNew(): CartItem
    {
        return $this->decoratedFactory->createNew();
    }

    // Methodes ajoutees par le decorator
    public function createForProduct(Product $product): CartItem
    {
        $item = $this->createNew();
        $item->setProduct($product);
        $item->setUnitPrice($product->getPrice());

        return $item;
    }
}
```

### 2. Decorator de State Processor (API Platform)

```php
namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class HashPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $decorated,
        private UserPasswordHasherInterface $hasher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Pre-traitement : hasher le mot de passe
        if ($data instanceof User && $data->getPlainPassword()) {
            $data->setPassword(
                $this->hasher->hashPassword($data, $data->getPlainPassword())
            );
            $data->eraseCredentials();
        }

        // Delegation au processor decore
        return $this->decorated->process($data, $operation, $uriVariables, $context);
    }
}
```

### 3. Configuration (decoration de service)

```yaml
# config/services.yaml
services:
    # Decoration explicite
    App\State\Processor\HashPasswordProcessor:
        decorates: 'api_platform.doctrine.orm.state.persist_processor'
        arguments:
            $decorated: '@.inner'

    # Ou avec l'attribut PHP
```

```php
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

#[AsDecorator(decorates: 'api_platform.doctrine.orm.state.persist_processor')]
class HashPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        #[AutowireDecorated] private ProcessorInterface $decorated,
        private UserPasswordHasherInterface $hasher,
    ) {}
}
```

## Referece Sylius

- `CartItemFactory.php` — decore FactoryInterface avec `createForProduct()` et `createForCart()`
- `AddressFactory.php` — ajoute `createForCustomer()`
- `PersistProcessor.php` — decore le processeur de persistance pour ajouter le hashing
