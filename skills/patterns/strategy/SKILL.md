---
name: pattern:strategy
description: "Strategy pattern - Algorithmes interchangeables selectionnes a l'execution"
argument-hint: [calculator|algorithm|strategy]
triggers:
  - strategy pattern
  - calculateur
  - algorithme interchangeable
  - delegating
  - calculator
---

# Pattern: Strategy

Differentes strategies/algorithmes selectionnes a l'execution. Permet d'ajouter de nouveaux comportements sans modifier le code existant.

## Quand utiliser

- Plusieurs algorithmes pour une meme tache (calcul livraison, calcul taxe, calcul promo)
- Le choix de l'algorithme depend d'une configuration ou du contexte
- On veut ajouter de nouvelles strategies sans toucher au code existant

## Quand NE PAS utiliser

- Un seul algorithme, pas de variation previsible
- La logique est triviale (un simple `if/else` suffit)

## Structure

```
src/
  Calculator/
    CalculatorInterface.php           # Interface commune
    FlatRateCalculator.php            # Strategie 1
    PerUnitRateCalculator.php         # Strategie 2
    WeightBasedCalculator.php         # Strategie 3
    DelegatingCalculator.php          # Selecteur de strategie
```

## Implementation

### 1. Interface

```php
namespace App\Calculator;

interface ShippingCalculatorInterface
{
    public function calculate(Shipment $shipment, array $configuration): int;

    public function getType(): string;
}
```

### 2. Strategies concretes

```php
namespace App\Calculator;

class FlatRateCalculator implements ShippingCalculatorInterface
{
    public function calculate(Shipment $shipment, array $configuration): int
    {
        return $configuration['amount'];
    }

    public function getType(): string
    {
        return 'flat_rate';
    }
}

class PerUnitRateCalculator implements ShippingCalculatorInterface
{
    public function calculate(Shipment $shipment, array $configuration): int
    {
        return $configuration['amount'] * $shipment->getUnitsCount();
    }

    public function getType(): string
    {
        return 'per_unit_rate';
    }
}
```

### 3. Delegating (selecteur de strategie)

```php
namespace App\Calculator;

class DelegatingCalculator
{
    /** @var iterable<ShippingCalculatorInterface> */
    private iterable $calculators;

    public function __construct(iterable $calculators)
    {
        $this->calculators = $calculators;
    }

    public function calculate(Shipment $shipment): int
    {
        $method = $shipment->getMethod();

        foreach ($this->calculators as $calculator) {
            if ($calculator->getType() === $method->getCalculator()) {
                return $calculator->calculate($shipment, $method->getConfiguration());
            }
        }

        throw new \LogicException(sprintf('Aucun calculateur pour le type "%s"', $method->getCalculator()));
    }
}
```

### 4. Enregistrement via service tags

```yaml
# config/services.yaml
services:
    App\Calculator\FlatRateCalculator:
        tags: ['app.shipping_calculator']

    App\Calculator\PerUnitRateCalculator:
        tags: ['app.shipping_calculator']

    App\Calculator\DelegatingCalculator:
        arguments:
            $calculators: !tagged_iterator 'app.shipping_calculator'
```

### Alternative : AutoconfigureTag (PHP 8)

```php
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.shipping_calculator')]
interface ShippingCalculatorInterface
{
    public function calculate(Shipment $shipment, array $configuration): int;
    public function getType(): string;
}
```

## Reference Sylius

- Interface : `ShippingCalculator/CalculatorInterface.php`
- `FlatRateCalculator.php` / `PerUnitRateCalculator.php`
- `DelegatingCalculator.php` — choisit la strategie depuis un registre
