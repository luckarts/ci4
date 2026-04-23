---
name: ci3-architecture
description: Architecture complète CI3 task app — Repository pattern, DI manuel, DTOs, design patterns et refactoring
---

# Architecture CI3 Task App

> Repository pattern + DI manuel + DTOs — l'équivalent de ce que Laravel/Symfony imposent nativement.

---

## Structure des dossiers

```
application/
  controllers/
    Auth.php
    Tasks.php
    Projects.php

  services/
    TaskService.php
    ProjectService.php
    AuthService.php

  repositories/
    interfaces/
      TaskRepositoryInterface.php
      ProjectRepositoryInterface.php
    TaskRepository.php
    ProjectRepository.php

  models/
    Task_model.php
    Project_model.php
    User_model.php

  dto/
    CreateTaskDTO.php
    UpdateTaskDTO.php
    CreateProjectDTO.php

  events/
    TaskCreated.php
    TaskAssigned.php

  listeners/
    ActivityLogListener.php
    NotificationListener.php

  core/
    Container.php
    EventDispatcher.php

  views/
    tasks/
    projects/
    auth/
```

---

## Flux complet

```
HTTP Request
    ↓
Controller         — reçoit, valide input, construit DTO
    ↓
DTO                — objet typé qui traverse les couches
    ↓
Service            — logique métier, orchestre
    ↓
Repository         — interface, le service ne voit pas la DB
    ↓
Model → DB         — SQL uniquement

Service → EventDispatcher → Listeners (log, notif)
```

**Règle** : le Service dépend de `TaskRepositoryInterface`, jamais de `TaskRepository` directement.

---

## Schéma BDD

| Table           | Colonnes |
|-----------------|----------|
| `users`         | id, name, email, password |
| `projects`      | id, name, owner_id |
| `tasks`         | id, title, status, priority, project_id, assignee_id, due_date |
| `activity_logs` | id, user_id, action, entity_type, entity_id |

---

## Ordre de construction

1. `Container` DI + `EventDispatcher`
2. Models + Repositories + Interfaces
3. DTOs
4. Services
5. Controllers
6. Views
7. Listeners

---

## Design Patterns à implémenter

### Creational

| Pattern | Implémentation |
|---------|----------------|
| **Factory** | `TaskFactory::create('bug')` — crée différents types de tasks selon le type |
| **Builder** | `TaskBuilder` — construit une task avec champs optionnels (`due_date`, `priority`, `assignee`, `tags`) |

### Structural

| Pattern | Implémentation |
|---------|----------------|
| **Decorator** | `CachedTaskRepository` enveloppe `TaskRepository` — transparent pour le Service |
| **Adapter** | Wrapper autour d'un service email externe sans changer le code interne |
| **Composite** | Task → sous-tâches → sous-sous-tâches (hiérarchie récursive) |
| **Singleton + Façade** | `CacheManager::clearAll()` — classe utilitaire statique, cache la complexité |

### Behavioral

| Pattern | Implémentation |
|---------|----------------|
| **Strategy** | `NotifierInterface` → `EmailStrategy`, `SMSStrategy`, `PushStrategy` |
| **State Machine** | Transitions strictes : `todo → in_progress → done → archived` (pas de saut) |
| **Chain of Responsibility** | Middleware pipeline avant le controller (auth, rate limit, validation) |
| **Command** | `CreateTaskCommand`, `AssignTaskCommand` — CQRS léger |
| **Template Method** | Base CRUD avec hooks `beforeSave()`, `afterDelete()` |
| **Observer** | `EventDispatcher` — `TaskCreated` → `ActivityLogListener` + `NotificationListener` |

---

## Patterns en refactoring

Chaque pattern = **un commit dédié** avec message explicatif.

```
refactor(repository): extract TaskRepositoryInterface for DI
refactor(cache): add CachedTaskRepository decorator
refactor(notifications): introduce Strategy pattern
refactor(status): implement State Machine for task transitions
```

### CacheManager — Singleton + Façade statique

```php
class CacheManager {
    private function __construct() {} // pas d'instanciation

    public static function clearAll(): void { ... }
    public static function clearPageCache(): void { ... }
}
```

Tradeoffs :

| Avantage | Inconvénient |
|----------|--------------|
| Simple à appeler partout | Couplage fort — difficile à tester |
| Pas besoin de DI | Pas swappable (pas d'interface) |
| Lisible | Global state caché |

---

## Priorité d'apprentissage

1. **State Machine** — règles de transition strictes, très concret sur une task app
2. **Strategy** — cas d'école parfait avec les notifications
3. **Decorator** — `CachedRepository` sans polluer le Repository
4. **Builder** — quand les champs optionnels se multiplient

---

## Mapping problème → pattern

| Problème | Pattern |
|----------|---------|
| Créer différents types de tasks | Factory |
| Task avec 6+ champs optionnels | Builder |
| Ajouter du cache sans toucher le Service | Decorator |
| Envoyer des notifs par plusieurs canaux | Strategy |
| Transitions de statut avec règles métier | State Machine |
| Pipeline de validation avant le controller | Chain of Responsibility |
| Découpler actions métier de leur exécution | Command |
| Réagir à des événements (log, notif) | Observer |
