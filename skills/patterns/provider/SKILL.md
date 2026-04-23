---
name: pattern:provider
description: "Provider pattern - Fournir des donnees ou configurations selon le contexte"
argument-hint: [provider|config|data source]
triggers:
  - provider pattern
  - data provider
  - configuration provider
  - fournisseur de donnees
  - api platform provider
---

# Pattern: Provider

Fournit des donnees ou configurations selon le contexte. Abstrait la source des donnees derriere une interface.

## Quand utiliser

- Plusieurs sources possibles pour une meme donnee
- La donnee fournie depend du contexte (channel, user, locale)
- API Platform : exposer des donnees qui ne viennent pas directement de Doctrine

## Quand NE PAS utiliser

- Les donnees viennent d'une seule source simple (Doctrine Provider par defaut suffit)

## Structure

```
src/
  Provider/
    PaymentConfigurationProviderInterface.php
    StripeConfigurationProvider.php
    PaypalConfigurationProvider.php
    CompositePaymentConfigurationProvider.php
  State/
    Provider/
      DashboardStatsProvider.php        # API Platform custom provider
```

## Implementation

### 1. Provider composite (multiple sources)

```php
namespace App\Provider;

interface PaymentConfigurationProviderInterface
{
    public function getConfiguration(PaymentMethod $method): array;
    public function supports(PaymentMethod $method): bool;
}

class StripeConfigurationProvider implements PaymentConfigurationProviderInterface
{
    public function getConfiguration(PaymentMethod $method): array
    {
        return [
            'publishable_key' => $method->getGatewayConfig()->getConfig()['publishable_key'],
        ];
    }

    public function supports(PaymentMethod $method): bool
    {
        return $method->getGatewayConfig()->getFactoryName() === 'stripe';
    }
}

class CompositePaymentConfigurationProvider implements PaymentConfigurationProviderInterface
{
    /** @var iterable<PaymentConfigurationProviderInterface> */
    private iterable $providers;

    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    public function getConfiguration(PaymentMethod $method): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($method)) {
                return $provider->getConfiguration($method);
            }
        }

        return [];
    }

    public function supports(PaymentMethod $method): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($method)) {
                return true;
            }
        }

        return false;
    }
}
```

### 2. API Platform State Provider

```php
namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

class DashboardStatsProvider implements ProviderInterface
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DashboardStats
    {
        return new DashboardStats(
            totalOrders: $this->orderRepository->countAll(),
            totalRevenue: $this->orderRepository->getTotalRevenue(),
            newCustomers: $this->customerRepository->countNewThisMonth(),
        );
    }
}
```

### 3. Ressource API Platform avec provider custom

```php
use ApiPlatform\Metadata\Get;

#[Get(provider: DashboardStatsProvider::class)]
class DashboardStats
{
    public int $totalOrders;
    public float $totalRevenue;
    public int $newCustomers;
}
```

## Reference Sylius

- `CompositePaymentConfigurationProvider.php` — itere sur des handlers pour la config de paiement
- `CompositeNotificationProvider.php` — agrege les notifications de sources multiples
