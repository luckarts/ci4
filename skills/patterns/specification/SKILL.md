---
name: pattern:specification
description: "Specification/Rule pattern - Regles metier composables et verifiables"
argument-hint: [rule|checker|eligibility]
triggers:
  - specification
  - rule checker
  - eligibility
  - regle metier
  - business rule
  - promotion rule
---

# Pattern: Specification / Rule

Encapsule des regles metier dans des objets independants et composables. Chaque regle est testable isolement.

## Quand utiliser

- Des regles metier complexes et combinables (promotions, eligibilite, validation)
- Les regles changent souvent ou sont configurables par l'utilisateur
- On veut tester chaque regle individuellement

## Quand NE PAS utiliser

- Validation simple (utiliser les Assert de Symfony)
- Une seule regle sans combinaison previsible

## Structure

```
src/
  Checker/
    RuleCheckerInterface.php              # Interface commune
    ItemTotalRuleChecker.php              # Regle : total minimum
    CartQuantityRuleChecker.php           # Regle : quantite minimum
    HasTaxonRuleChecker.php               # Regle : taxon present
    PromotionRulesEligibilityChecker.php  # Orchestrateur des regles
```

## Implementation

### 1. Interface

```php
namespace App\Checker;

interface RuleCheckerInterface
{
    public function isEligible(OrderInterface $order, array $configuration): bool;

    public function getType(): string;
}
```

### 2. Regles concretes

```php
namespace App\Checker;

class ItemTotalRuleChecker implements RuleCheckerInterface
{
    public function isEligible(OrderInterface $order, array $configuration): bool
    {
        return $order->getItemsTotal() >= $configuration['amount'];
    }

    public function getType(): string
    {
        return 'item_total';
    }
}

class CartQuantityRuleChecker implements RuleCheckerInterface
{
    public function isEligible(OrderInterface $order, array $configuration): bool
    {
        return $order->getTotalQuantity() >= $configuration['count'];
    }

    public function getType(): string
    {
        return 'cart_quantity';
    }
}

class HasTaxonRuleChecker implements RuleCheckerInterface
{
    public function isEligible(OrderInterface $order, array $configuration): bool
    {
        foreach ($order->getItems() as $item) {
            if ($item->getProduct()->hasTaxon($configuration['taxon_code'])) {
                return true;
            }
        }

        return false;
    }

    public function getType(): string
    {
        return 'has_taxon';
    }
}
```

### 3. Orchestrateur (verifie toutes les regles d'une promotion)

```php
namespace App\Checker;

class PromotionRulesEligibilityChecker
{
    /** @var iterable<RuleCheckerInterface> */
    private iterable $ruleCheckers;

    public function __construct(iterable $ruleCheckers)
    {
        $this->ruleCheckers = $ruleCheckers;
    }

    public function isEligible(OrderInterface $order, PromotionInterface $promotion): bool
    {
        foreach ($promotion->getRules() as $rule) {
            $checker = $this->getChecker($rule->getType());

            if (!$checker->isEligible($order, $rule->getConfiguration())) {
                return false;
            }
        }

        return true;
    }

    private function getChecker(string $type): RuleCheckerInterface
    {
        foreach ($this->ruleCheckers as $checker) {
            if ($checker->getType() === $type) {
                return $checker;
            }
        }

        throw new \LogicException(sprintf('Aucun checker pour le type "%s"', $type));
    }
}
```

### 4. Enregistrement

```yaml
services:
    _instanceof:
        App\Checker\RuleCheckerInterface:
            tags: ['app.promotion_rule_checker']

    App\Checker\PromotionRulesEligibilityChecker:
        arguments:
            $ruleCheckers: !tagged_iterator 'app.promotion_rule_checker'
```

## Reference Sylius

- `PromotionRulesEligibilityChecker.php` — verifie si un sujet satisfait toutes les regles
- `ItemTotalRuleChecker.php` / `CartQuantityRuleChecker.php` / `HasTaxonRuleChecker.php`
- Chaque regle implemente `RuleCheckerInterface` et est enregistree via service tag
