---
name: pattern:composite
description: "Composite pattern - Agreger N implementations derriere une seule interface"
argument-hint: [processor|context|checker]
triggers:
  - composite
  - agreger
  - chain of responsibility
  - composite processor
  - composite checker
---

# Pattern: Composite

Agrege plusieurs implementations d'une meme interface derriere un seul objet. Permet de traiter un groupe d'objets comme un seul.

## Quand utiliser

- Plusieurs services implementent la meme interface et doivent etre executes ensemble
- On veut chainer des traitements par priorite (pipeline)
- On veut essayer plusieurs strategies jusqu'a ce qu'une reussisse (fallback)

## Variantes

| Variante | Logique | Exemple |
|----------|---------|---------|
| **All** | Tous doivent reussir | CompositeEligibilityChecker |
| **First match** | Le premier qui repond gagne | CompositeChannelContext |
| **Pipeline** | Tous executent dans l'ordre | CompositeOrderProcessor |

## Implementation

### 1. Composite "Pipeline" (tous executent dans l'ordre)

```php
namespace App\Processor;

interface OrderProcessorInterface
{
    public function process(Order $order): void;
}

class CompositeOrderProcessor implements OrderProcessorInterface
{
    /** @var iterable<OrderProcessorInterface> */
    private iterable $processors;

    public function __construct(iterable $processors)
    {
        $this->processors = $processors;
    }

    public function process(Order $order): void
    {
        foreach ($this->processors as $processor) {
            $processor->process($order);
        }
    }
}
```

### 2. Composite "First Match" (le premier qui repond)

```php
namespace App\Context;

interface ChannelContextInterface
{
    public function getChannel(): Channel;
}

class CompositeChannelContext implements ChannelContextInterface
{
    /** @var iterable<ChannelContextInterface> */
    private iterable $contexts;

    public function __construct(iterable $contexts)
    {
        $this->contexts = $contexts;
    }

    public function getChannel(): Channel
    {
        foreach ($this->contexts as $context) {
            try {
                return $context->getChannel();
            } catch (ChannelNotFoundException) {
                continue;
            }
        }

        throw new ChannelNotFoundException();
    }
}
```

### 3. Composite "All must pass" (tous doivent reussir)

```php
namespace App\Checker;

interface EligibilityCheckerInterface
{
    public function isEligible(Order $order, Promotion $promotion): bool;
}

class CompositeEligibilityChecker implements EligibilityCheckerInterface
{
    /** @var iterable<EligibilityCheckerInterface> */
    private iterable $checkers;

    public function __construct(iterable $checkers)
    {
        $this->checkers = $checkers;
    }

    public function isEligible(Order $order, Promotion $promotion): bool
    {
        foreach ($this->checkers as $checker) {
            if (!$checker->isEligible($order, $promotion)) {
                return false;
            }
        }

        return true;
    }
}
```

### 4. Enregistrement avec priorite

```yaml
services:
    App\Processor\TaxProcessor:
        tags: [{ name: 'app.order_processor', priority: 10 }]

    App\Processor\ShippingProcessor:
        tags: [{ name: 'app.order_processor', priority: 20 }]

    App\Processor\PromotionProcessor:
        tags: [{ name: 'app.order_processor', priority: 30 }]

    App\Processor\CompositeOrderProcessor:
        arguments:
            $processors: !tagged_iterator { tag: 'app.order_processor', default_priority_method: 'getPriority' }
```

## Reference Sylius

- `CompositeOrderProcessor.php` — chaine les processors par priorite
- `CompositeChannelContext.php` — essaie plusieurs contextes (first match)
- `CompositePromotionEligibilityChecker.php` — tous les checkers doivent retourner true
- `CompositeProductVariantResolver.php` — retourne le premier resultat non-null
