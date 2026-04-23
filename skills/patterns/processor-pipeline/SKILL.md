---
name: pattern:processor-pipeline
description: "Processor Pipeline pattern - Chaine de traitements ordonnes par priorite"
argument-hint: [processor|pipeline|order processing]
triggers:
  - processor pipeline
  - order processing
  - chaine de traitement
  - pipeline
  - processing chain
---

# Pattern: Processor (Pipeline)

Traite une entite via une serie d'operations chainees, executees dans un ordre defini par priorite.

## Quand utiliser

- Plusieurs etapes de traitement independantes sur un meme objet
- L'ordre d'execution compte (taxes avant total, promos avant taxes, etc.)
- Chaque etape est independante et testable isolement

## Quand NE PAS utiliser

- Une seule etape de traitement
- Les etapes sont fortement couplees entre elles

## Structure

```
src/
  Processor/
    OrderProcessorInterface.php          # Interface commune
    TaxProcessor.php                     # Etape 1 : calcul taxes
    ShippingChargesProcessor.php         # Etape 2 : frais livraison
    PromotionProcessor.php               # Etape 3 : promotions
    PaymentProcessor.php                 # Etape 4 : paiements
    CompositeOrderProcessor.php          # Orchestrateur (voir pattern Composite)
```

## Implementation

### 1. Interface

```php
namespace App\Processor;

interface OrderProcessorInterface
{
    public function process(Order $order): void;
}
```

### 2. Processeurs individuels

```php
namespace App\Processor;

class ShippingChargesProcessor implements OrderProcessorInterface
{
    public function __construct(
        private ShippingCalculatorInterface $calculator,
    ) {}

    public function process(Order $order): void
    {
        if (!$order->canBeShipped()) {
            $order->setShippingTotal(0);
            return;
        }

        foreach ($order->getShipments() as $shipment) {
            $cost = $this->calculator->calculate($shipment);
            $shipment->setShippingCost($cost);
        }

        $order->setShippingTotal(
            array_sum(array_map(fn($s) => $s->getShippingCost(), $order->getShipments()->toArray()))
        );
    }
}

class PaymentProcessor implements OrderProcessorInterface
{
    public function process(Order $order): void
    {
        $total = $order->getTotal();

        if ($total <= 0) {
            $order->removePayments();
            return;
        }

        $payment = $order->getLastPayment() ?? new Payment();
        $payment->setAmount($total);
        $payment->setCurrency($order->getCurrency());

        $order->addPayment($payment);
    }
}
```

### 3. Orchestration via Composite

```php
// Voir le skill pattern:composite pour l'implementation de CompositeOrderProcessor
// Les processeurs sont chaines par priorite via tagged_iterator
```

### 4. Configuration avec priorite

```yaml
services:
    App\Processor\TaxProcessor:
        tags: [{ name: 'app.order_processor', priority: 10 }]

    App\Processor\ShippingChargesProcessor:
        tags: [{ name: 'app.order_processor', priority: 20 }]

    App\Processor\PromotionProcessor:
        tags: [{ name: 'app.order_processor', priority: 30 }]

    App\Processor\PaymentProcessor:
        tags: [{ name: 'app.order_processor', priority: 40 }]
```

### 5. Utilisation

```php
class OrderService
{
    public function __construct(
        private OrderProcessorInterface $orderProcessor,
    ) {}

    public function recalculate(Order $order): void
    {
        // Execute tous les processeurs dans l'ordre de priorite
        $this->orderProcessor->process($order);
    }
}
```

## Reference Sylius

- `OrderPaymentProcessor.php` — cree/met a jour les paiements
- `ShippingChargesProcessor.php` — calcule les frais de livraison
- `CompositeOrderProcessor` orchestre tous les processeurs dans l'ordre de priorite
