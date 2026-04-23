---
name: pattern:observer
description: "Observer/Event Listener pattern - Communication decouplee via evenements Symfony"
argument-hint: [listener|subscriber|event]
triggers:
  - observer
  - event listener
  - event subscriber
  - evenement
  - listener
  - decouplage
---

# Pattern: Observer / Event Listener

Communication decouplee via le systeme d'evenements de Symfony. Les services reagissent a des evenements sans connaitre l'emetteur.

## Quand utiliser

- Effets de bord decouples (envoyer un email apres une commande, logger une action)
- Reagir aux transitions de workflow (state machine)
- Modifier le comportement du framework (kernel events)
- Synchroniser des systemes externes

## Quand NE PAS utiliser

- La logique est synchrone et fait partie integrante du use case → mettre dans le handler/service directement
- Un seul "listener" sans decouplage reel → appel direct plus simple

## Structure

```
src/
  EventListener/
    AssignOrderNumberListener.php     # Reagit a une transition workflow
    CancelPaymentListener.php         # Annulation en cascade
    SendConfirmationEmailListener.php # Notification
  EventSubscriber/
    KernelRequestSubscriber.php       # Subscriber multi-events
  Event/
    OrderCompletedEvent.php           # Event custom
```

## Implementation

### 1. Event custom

```php
namespace App\Event;

class OrderCompletedEvent
{
    public function __construct(
        public readonly Order $order,
    ) {}
}
```

### 2. Listener (un event, une methode)

```php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: OrderCompletedEvent::class)]
class SendConfirmationEmailListener
{
    public function __construct(
        private MailerInterface $mailer,
    ) {}

    public function __invoke(OrderCompletedEvent $event): void
    {
        $order = $event->order;

        $this->mailer->send(
            (new Email())
                ->to($order->getCustomer()->getEmail())
                ->subject(sprintf('Commande %s confirmee', $order->getNumber()))
                ->html($this->renderEmail($order))
        );
    }
}
```

### 3. Listener sur transition workflow

```php
namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

#[AsEventListener(event: 'workflow.order.completed.cancel')]
class CancelPaymentListener
{
    public function __invoke(CompletedEvent $event): void
    {
        /** @var Order $order */
        $order = $event->getSubject();

        foreach ($order->getPayments() as $payment) {
            if ($payment->getState() === Payment::STATE_NEW) {
                $payment->setState(Payment::STATE_CANCELLED);
            }
        }
    }
}
```

### 4. Subscriber (plusieurs events dans une classe)

```php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiAvailabilitySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Verification disponibilite API
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // Gestion centralisee des erreurs
    }
}
```

### 5. Dispatcher un event custom

```php
class OrderService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function complete(Order $order): void
    {
        // Logique metier...
        $order->complete();

        // Dispatcher l'event pour les effets de bord
        $this->dispatcher->dispatch(new OrderCompletedEvent($order));
    }
}
```

## Listener vs Subscriber

| | Listener | Subscriber |
|---|---------|-----------|
| Config | Attribut `#[AsEventListener]` | Implements `EventSubscriberInterface` |
| Events | 1 event = 1 classe | N events = 1 classe |
| Priorite | Via attribut | Via `getSubscribedEvents()` |
| Usage | Cas simple, decouple | Grouper des events lies |

## Reference Sylius

- `AssignOrderNumberListener.php` — ecoute les transitions de workflow
- `CancelPaymentListener.php` — annulation paiement quand commande annulee
- `ProductUpdatedListener.php` — recalcule les promotions catalogue
- `KernelRequestEventSubscriber.php` — valide la disponibilite API
