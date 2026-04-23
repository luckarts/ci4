---
name: pattern:cqrs-handler
description: "CQRS Handler pattern - Separation lecture/ecriture avec Symfony Messenger"
argument-hint: [command|query|handler]
triggers:
  - cqrs handler
  - command handler
  - query handler
  - messenger handler
  - AsMessageHandler
  - separation lecture ecriture
---

# Pattern: CQRS / Handler

Architecture Command/Query avec Symfony Messenger. Separe les intentions d'ecriture (Commands) des intentions de lecture (Queries).

## Quand utiliser (regle des 20%)

- Actions metier complexes avec effets de bord
- L'intention est plus importante que la donnee
- Traitement asynchrone possible
- Audit trail necessaire

## Quand NE PAS utiliser (regle des 80%)

- CRUD simple sans logique metier → utiliser les State Processors API Platform
- Simple lecture de donnees → utiliser les Providers API Platform
- Voir le skill `ddd-cqrs-guide` pour l'arbre de decision complet

## Structure

```
src/
  Application/
    Command/
      AddItemToCartCommand.php         # DTO d'intention d'ecriture
      CompleteOrderCommand.php
    Query/
      GetCustomerStatisticsQuery.php   # DTO d'intention de lecture
    Handler/
      Command/
        AddItemToCartHandler.php       # Logique metier d'ecriture
        CompleteOrderHandler.php
      Query/
        GetCustomerStatisticsHandler.php  # Logique metier de lecture
```

## Implementation

### 1. Command (intention d'ecriture)

```php
namespace App\Application\Command;

final class AddItemToCartCommand
{
    public function __construct(
        public readonly string $cartToken,
        public readonly string $productCode,
        public readonly int $quantity,
    ) {}
}
```

### 2. Command Handler

```php
namespace App\Application\Handler\Command;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddItemToCartHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private ProductRepositoryInterface $productRepository,
        private AvailabilityCheckerInterface $availabilityChecker,
        private OrderProcessorInterface $orderProcessor,
    ) {}

    public function __invoke(AddItemToCartCommand $command): void
    {
        $cart = $this->orderRepository->findByToken($command->cartToken);
        $product = $this->productRepository->findByCode($command->productCode);

        // Regles metier
        if (!$this->availabilityChecker->isStockAvailable($product, $command->quantity)) {
            throw new ProductOutOfStockException($product);
        }

        $cart->addItem($product, $command->quantity);

        // Recalcul (promotions, taxes, livraison)
        $this->orderProcessor->process($cart);
    }
}
```

### 3. Query (intention de lecture)

```php
namespace App\Application\Query;

final class GetCustomerStatisticsQuery
{
    public function __construct(
        public readonly int $customerId,
    ) {}
}
```

### 4. Query Handler

```php
namespace App\Application\Handler\Query;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetCustomerStatisticsHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {}

    public function __invoke(GetCustomerStatisticsQuery $query): CustomerStatistics
    {
        $orders = $this->orderRepository->findByCustomer($query->customerId);

        return new CustomerStatistics(
            totalOrders: count($orders),
            totalSpent: array_sum(array_map(fn($o) => $o->getTotal(), $orders)),
            averageOrderValue: /* ... */,
        );
    }
}
```

### 5. Integration API Platform (State Processor → Messenger)

```php
namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AddItemToCartProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $this->commandBus->dispatch(new AddItemToCartCommand(
            cartToken: $uriVariables['token'],
            productCode: $data->productCode,
            quantity: $data->quantity,
        ));
    }
}
```

## Reference Sylius

- `AddItemToCartHandler.php` — gere `AddItemToCart` avec `#[AsMessageHandler]`
- `CompleteOrderHandler.php` — finalise une commande
- `GetCustomerStatisticsHandler.php` — query handler pour les statistiques
