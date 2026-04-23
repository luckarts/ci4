---
name: pattern:state-machine
description: "State Machine pattern - Gestion des transitions d'etat securisees avec Symfony Workflow"
argument-hint: [entity|workflow|transition]
triggers:
  - state machine
  - workflow
  - transition
  - etat
  - status change
  - order status
---

# Pattern: State Machine

Gestion des transitions d'etat securisees. Empeche les etats invalides et declenche des effets de bord via des listeners.

## Quand utiliser

- Une entite a un cycle de vie avec des etats distincts (commande, paiement, expedition)
- Les transitions entre etats doivent etre controlees (on ne peut pas passer de "annule" a "livre")
- Des actions doivent se declencher automatiquement lors des transitions

## Quand NE PAS utiliser

- Un simple champ `status` avec 2-3 valeurs sans regles de transition
- Pas de contraintes sur les transitions possibles

## Structure Symfony

```
config/
  packages/workflow.yaml          # Definition des etats et transitions

src/
  Entity/Order.php                # Entite avec propriete status
  Workflow/
    Guard/
      OrderGuard.php              # Bloque des transitions selon des conditions
    Listener/
      AssignOrderNumberListener.php  # Reagit aux transitions
```

## Implementation

### 1. Configuration du Workflow

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        order:
            type: state_machine
            audit_trail:
                enabled: true
            marking_store:
                type: method
                property: status
            supports:
                - App\Entity\Order
            initial_marking: cart
            places:
                - cart
                - new
                - confirmed
                - shipped
                - delivered
                - cancelled
            transitions:
                create:
                    from: cart
                    to: new
                cancel:
                    from: [cart, new, confirmed]
                    to: cancelled
                confirm:
                    from: new
                    to: confirmed
                ship:
                    from: confirmed
                    to: shipped
                deliver:
                    from: shipped
                    to: delivered
```

### 2. Entite

```php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Order
{
    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'cart';

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}
```

### 3. Guard (bloquer une transition conditionnellement)

```php
namespace App\Workflow\Guard;

use App\Entity\Order;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsEventListener(event: 'workflow.order.guard.ship')]
class OrderShipGuard
{
    public function __invoke(GuardEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();

        if (!$order->isPaid()) {
            $event->setBlocked(true, 'La commande doit etre payee avant expedition.');
        }
    }
}
```

### 4. Listener (reagir a une transition)

```php
namespace App\Workflow\Listener;

use App\Entity\Order;
use App\Service\OrderNumberGenerator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.order.completed.create')]
class AssignOrderNumberListener
{
    public function __construct(
        private OrderNumberGenerator $generator,
    ) {}

    public function __invoke(CompletedEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();
        $order->setNumber($this->generator->generate());
    }
}
```

### 5. Utilisation dans un service

```php
use Symfony\Component\Workflow\WorkflowInterface;

class OrderService
{
    public function __construct(
        private WorkflowInterface $orderStateMachine,
    ) {}

    public function confirm(Order $order): void
    {
        if ($this->orderStateMachine->can($order, 'confirm')) {
            $this->orderStateMachine->apply($order, 'confirm');
        }
    }
}
```

## Reference Sylius

- Config : `src/Sylius/Bundle/OrderBundle/Resources/config/workflow/state_machine.yaml`
- Listener : `AssignOrderNumberListener.php` — transition cart → new
- Composite : `CompositeStateMachine.php` — delegation vers Symfony Workflow ou WinzouStateMachine
