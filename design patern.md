# Design Patterns — CI3 Task App

## Creational (création d'objets)

| Pattern | Usage dans task app |
|---------|---------------------|
| Factory | `TaskFactory::create('bug')` — crée différents types de tasks |
| Builder | Construire une task complexe étape par étape avec champs optionnels |

## Structural (structure des classes)

| Pattern   | Usage dans task app |
|-----------|---------------------|
| Decorator | `CachedRepository` enveloppe `TaskRepository` — transparent pour le service |
| Adapter   | Adapter un service email externe sans changer le code interne |
| Composite | Task → sous-tâches → sous-sous-tâches (hiérarchie) |

## Behavioral (comportement)

| Pattern                 | Usage dans task app |
|-------------------------|---------------------|
| Strategy                | Notifications : `EmailStrategy`, `SMSStrategy`, `PushStrategy` |
| State Machine           | Transitions de statut : `todo → in_progress → done → archived` |
| Chain of Responsibility | Middleware pipeline avant le controller |
| Command                 | `CreateTaskCommand`, `AssignTaskCommand` — CQRS léger |
| Template Method         | Base CRUD avec hooks `beforeSave()`, `afterDelete()` |
| Observer                | Déjà prévu — `EventDispatcher` |

---

## Priorité d'apprentissage

**State Machine** — très concret sur une task app, le statut d'une task a des règles de transition strictes
(on ne peut pas passer de `todo` à `archived` directement).

**Strategy** — les notifications sont le cas d'école parfait.

**Decorator** — le `CachedRepository` montre exactement comment la couche cache s'intègre sans polluer le repository.

**Builder** — utile quand une task a beaucoup de champs optionnels (`due_date`, `priority`, `assignee`, `tags`...).
