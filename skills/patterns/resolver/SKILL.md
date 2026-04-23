---
name: pattern:resolver
description: "Resolver pattern - Resoudre la bonne valeur/objet selon le contexte metier"
argument-hint: [resolver|shipping|payment|state]
triggers:
  - resolver
  - resolution contextuelle
  - resoudre
  - determine state
  - default method
---

# Pattern: Resolver

Resout la bonne valeur ou le bon objet selon le contexte metier (zone, channel, etat des sous-entites, etc.).

## Quand utiliser

- La valeur a determiner depend de plusieurs criteres contextuels
- La logique de resolution est complexe et merite son propre service
- Plusieurs entites/strategies doivent etre filtrees pour trouver la bonne

## Quand NE PAS utiliser

- Un simple `findOneBy()` suffit
- La logique tient dans une methode de repository

## Structure

```
src/
  Resolver/
    ShippingMethodsResolverInterface.php
    ZoneAndChannelBasedShippingMethodsResolver.php
    DefaultPaymentMethodResolver.php
    OrderStateResolver.php
```

## Implementation

### 1. Resolver de methodes de livraison

```php
namespace App\Resolver;

interface ShippingMethodsResolverInterface
{
    /** @return ShippingMethod[] */
    public function getSupportedMethods(Shipment $shipment): array;
}

class ZoneAndChannelBasedShippingMethodsResolver implements ShippingMethodsResolverInterface
{
    public function __construct(
        private ShippingMethodRepositoryInterface $repository,
        private ZoneMatcherInterface $zoneMatcher,
        private EligibilityCheckerInterface $eligibilityChecker,
    ) {}

    public function getSupportedMethods(Shipment $shipment): array
    {
        $order = $shipment->getOrder();
        $channel = $order->getChannel();
        $zone = $this->zoneMatcher->match($order->getShippingAddress());

        $methods = $this->repository->findEnabledForChannelAndZone($channel, $zone);

        return array_filter(
            $methods,
            fn(ShippingMethod $method) => $this->eligibilityChecker->isEligible($shipment, $method)
        );
    }
}
```

### 2. Resolver de methode de paiement par defaut

```php
namespace App\Resolver;

class DefaultPaymentMethodResolver
{
    public function __construct(
        private PaymentMethodRepositoryInterface $repository,
    ) {}

    public function getDefaultPaymentMethod(Channel $channel): PaymentMethod
    {
        $methods = $this->repository->findEnabledForChannel($channel);

        if (empty($methods)) {
            throw new UnresolvedPaymentMethodException($channel);
        }

        return $methods[0];
    }
}
```

### 3. State Resolver (determine l'etat d'une entite)

```php
namespace App\Resolver;

class OrderStateResolver
{
    public function resolve(Order $order): string
    {
        if ($this->allShipmentsDelivered($order) && $this->allPaymentsCompleted($order)) {
            return Order::STATE_FULFILLED;
        }

        if ($this->allPaymentsCompleted($order)) {
            return Order::STATE_PAID;
        }

        if ($this->hasAtLeastOnePayment($order)) {
            return Order::STATE_PARTIALLY_PAID;
        }

        return Order::STATE_NEW;
    }

    private function allPaymentsCompleted(Order $order): bool
    {
        return $order->getPayments()
            ->forAll(fn($_, Payment $p) => $p->getState() === Payment::STATE_COMPLETED);
    }

    private function allShipmentsDelivered(Order $order): bool
    {
        return $order->getShipments()
            ->forAll(fn($_, Shipment $s) => $s->getState() === Shipment::STATE_DELIVERED);
    }
}
```

## Reference Sylius

- `ZoneAndChannelBasedShippingMethodsResolver.php` — filtre par zone + channel + eligibilite
- `DefaultPaymentMethodResolver.php` — premiere methode de paiement active du channel
- `OrderStateResolver.php` — determine l'etat selon paiements et expeditions
