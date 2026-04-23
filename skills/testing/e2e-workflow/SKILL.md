---
name: test:workflow
description: "Tests E2E cross-entités — *WorkflowTest.php à la fin de chaque Bounded Context majeur"
argument-hint: [bc-name|phase-number]
triggers:
  - workflow test
  - test workflow
  - cross-entity test
  - WorkflowTest
  - test interactions entités
  - test cross bc
  - test fin de phase
  - test bout en bout complexe
  - scénario réaliste
  - test cumul entités
---

# Tests E2E Workflow (Cross-Entités)

Un `*WorkflowTest.php` est créé **une fois par Bounded Context majeur**, à la fin de la phase, pour tester les interactions réalistes entre le nouveau BC et tous les BCs précédents.

Référence canonique : `tests/E2E/Project/ProjectWorkflowTest.php`

---

## Quand créer un WorkflowTest

### Arbre de décision

```
La feature ajoute-t-elle de nouvelles entités visibles via l'API ?
│
├── NON → Pas de WorkflowTest
│         (refacto interne, infra, pattern archi, async, CQRS…)
│         → Vérifier que les tests existants passent encore (régression)
│
└── OUI → Ces entités interagissent-elles avec des entités d'autres BCs ?
          │
          ├── NON → Pas de WorkflowTest
          │         (entité autonome sans dépendances croisées)
          │
          └── OUI → Créer {BC}WorkflowTest.php ✅
```

### Features qui NE créent PAS de WorkflowTest

Ces features changent l'architecture interne mais ne modifient pas les interactions entre entités. Les tests E2E existants suffisent à vérifier la non-régression.

| Feature | Pourquoi pas de WorkflowTest |
|---------|------------------------------|
| `feature/async-messenger` | Refacto interne — l'API surface reste identique, seul le transport change |
| `feature/cqrs-buses` | Extraction CQRS — même comportement observable, différent découpage interne |
| `feature/soft-delete` | Trait technique — pas de nouvelles interactions entre entités |
| `feature/domain-events` | Découplage interne — les endpoints API ne changent pas |
| `feature/exception-handling` | Middleware global — change les codes d'erreur, pas les entités |
| `feature/value-objects` | Refacto de types — aucune entité ajoutée |
| `feature/audit-logs` (si internal) | Si pas d'endpoint API exposé, pas de parcours utilisateur à tester |

**Pour ces features :** lancer `php bin/phpunit --group e2e` après la feature pour s'assurer qu'aucune régression n'a été introduite. C'est suffisant.

### Features qui créent un WorkflowTest

```
BC majeur avec nouvelles entités API cross-BCs :

Phase 2 — Project BC  ✅ → ProjectWorkflowTest  (Org + Project + Member + Column)
Phase 3 — Task BC        → TaskWorkflowTest     (+ Task + Tag + state machine)
Phase 4 — Collab BC      → CommentWorkflowTest  (+ Comment + Reaction + Attachment)
Phase 5 — Time BC        → TimeTrackingWorkflowTest (+ TimeEntry + agrégations)
Phase 6 — Compliance     → AuditWorkflowTest    (AuditLog exposé via API → tous BCs)
Phase 7 — CustomField    → CustomFieldWorkflowTest (Project + Task + FieldValue)
Phase 8 — Notif          → NotificationWorkflowTest (events métier → Notification API)
```

---

## Localisation

```
tests/E2E/{BC}/{BC}WorkflowTest.php

Exemples :
  tests/E2E/Project/ProjectWorkflowTest.php   ✅ existant
  tests/E2E/Task/TaskWorkflowTest.php
  tests/E2E/Collaboration/CommentWorkflowTest.php
```

---

## Les 4 scénarios obligatoires

Chaque `*WorkflowTest` couvre exactement ces 4 scénarios dans cet ordre.

### Scénario 1 — Happy path complet (`smoke`)

Lifecycle end-to-end réaliste : setup de toutes les entités nécessaires + actions métier dans l'ordre naturel.

```php
#[Test]
#[Group('smoke')]
#[Group('e2e')]
#[Group('{bc}')]
#[Group('{bc}-complex')]
public function complete_{bc}_lifecycle_workflow(): void
{
    // 1. Setup des BCs précédents (org, projet, colonnes, membres)
    // 2. Créer les entités du nouveau BC
    // 3. Effectuer les actions métier dans l'ordre réaliste
    // 4. Vérifier l'état final cohérent
}
```

**Ce que "réaliste" signifie :**
- Un vrai utilisateur ferait ces étapes dans cet ordre
- Plusieurs acteurs avec des rôles différents (owner, editor, viewer)
- Vérification d'état final (GET après les mutations)

### Scénario 2 — Isolation cross-org (`smoke`)

Même scénario avec deux organisations distinctes. Vérifie que les données d'une org ne "fuient" pas vers l'autre.

```php
#[Test]
#[Group('smoke')]
#[Group('e2e')]
#[Group('{bc}')]
#[Group('{bc}-complex')]
public function {bc}_data_is_isolated_between_organizations(): void
{
    // Org A + ses données
    // Org B + ses données
    // User Org A ne peut pas accéder aux données de Org B → 403
    // User Org B ne peut pas accéder aux données de Org A → 403
}
```

### Scénario 3 — Cascade de retrait

Ce qui se passe quand une entité parente change d'état (archivage, suppression, retrait de membre).

```php
#[Test]
#[Group('e2e')]
#[Group('{bc}')]
#[Group('{bc}-complex')]
public function cascade_when_parent_entity_changes(): void
{
    // Setup complet
    // Action sur l'entité parente (archiver, retirer, supprimer)
    // Vérifier l'effet sur les entités enfants du nouveau BC
}
```

### Scénario 4 — Matrice de permissions

Qui peut faire quoi selon son rôle dans le contexte de ce BC.

```php
#[Test]
#[Group('e2e')]
#[Group('{bc}')]
#[Group('{bc}-complex')]
public function permission_matrix_by_role_for_{bc}(): void
{
    // Setup : owner + editor (CAN_EDIT) + viewer (CAN_COMMENT) + outsider
    // OWNER       → peut tout faire
    // CAN_EDIT    → peut créer/modifier, ne peut pas supprimer/admin
    // CAN_COMMENT → lecture seule sur les ressources protégées
    // OUTSIDER    → 403 sur tout (projet privé) ou lecture seule (public)
}
```

---

## Progression cumulative des entités

À chaque phase, le setup du scénario 1 hérite du setup des phases précédentes et ajoute une couche.

```
Phase 2 (Project)
  setup: createUser → createOrg → inviteMembers
  new:   createProject → addProjectMembers → createColumns

Phase 3 (Task)
  setup: [Phase 2 complet]
  new:   createTask (DRAFT) → publish → assign → transition → createSubtask → addTag

Phase 4 (Collaboration)
  setup: [Phase 3 complet avec tâche IN_PROGRESS]
  new:   addComment → addReaction → checkIsInternal visibility

Phase 5 (TimeTracking)
  setup: [Phase 3 complet avec tâche assignée]
  new:   startTimer → stopTimer → verifyDuration → checkAggregation

Phase 6 (Compliance)
  setup: actions sur toutes les entités des phases 1-5
  new:   vérifier AuditLog généré pour chaque action

Phase 7 (CustomField)
  setup: [Phase 3 complet]
  new:   createFieldDefinition → assignValue → filterByValue

Phase 8 (Notifications)
  setup: déclencher events métier des phases 1-7
  new:   vérifier Notification créée → vérifier préférences
```

---

## Template complet

```php
<?php

declare(strict_types=1);

namespace App\Tests\E2E\{BC};

use App\Tests\E2E\AbstractApiTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests d'intégration cross-entités : workflows complets impliquant
 * {liste des BCs impliqués}.
 *
 * Ces tests couvrent des scénarios réalistes :
 *  - {scénario 1 résumé}
 *  - {scénario 2 résumé}
 *  - {scénario 3 résumé}
 *  - {scénario 4 résumé}
 */
class {BC}WorkflowTest extends AbstractApiTestCase
{
    // ──────────────────────────────────────────────
    // Scénario 1 : Happy path complet
    // ──────────────────────────────────────────────

    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('{bc}')]
    #[Group('{bc}-complex')]
    public function complete_{bc}_lifecycle_workflow(): void
    {
        // 1. Setup BCs précédents
        $this->createUser('wf-owner@example.com', 'password123', 'Owner', 'Test');
        $ownerToken = $this->getOAuth2Token('wf-owner@example.com', 'password123');
        $this->apiRequest('POST', '/api/organizations', $ownerToken, [
            'name' => 'Workflow Org',
            'slug' => 'wf-org',
        ]);
        // ... setup projet, colonnes, membres ...

        // 2. Actions du nouveau BC
        // ...

        // 3. Vérification état final
        // ...
    }

    // ──────────────────────────────────────────────
    // Scénario 2 : Isolation cross-org
    // ──────────────────────────────────────────────

    #[Test]
    #[Group('smoke')]
    #[Group('e2e')]
    #[Group('{bc}')]
    #[Group('{bc}-complex')]
    public function {bc}_data_is_isolated_between_organizations(): void
    {
        // Org A
        // Org B
        // Vérifier 403 dans les deux sens
    }

    // ──────────────────────────────────────────────
    // Scénario 3 : Cascade
    // ──────────────────────────────────────────────

    #[Test]
    #[Group('e2e')]
    #[Group('{bc}')]
    #[Group('{bc}-complex')]
    public function cascade_when_parent_entity_changes(): void
    {
        // ...
    }

    // ──────────────────────────────────────────────
    // Scénario 4 : Matrice de permissions
    // ──────────────────────────────────────────────

    #[Test]
    #[Group('e2e')]
    #[Group('{bc}')]
    #[Group('{bc}-complex')]
    public function permission_matrix_by_role_for_{bc}(): void
    {
        // owner, editor (CAN_EDIT), viewer (CAN_COMMENT), outsider
    }
}
```

---

## Conventions PHPUnit

| Group | Signification | Quand lancer |
|-------|--------------|-------------|
| `smoke` | Scénarios 1 et 2 uniquement | CI rapide, pre-merge |
| `e2e` | Tous les scénarios workflow | CI complet |
| `{bc}` | Tous les tests du BC (CRUD + workflow) | Ciblé sur un BC |
| `{bc}-complex` | Workflow cross-entités uniquement | Debug isolation |

```bash
# CI rapide : seulement les smoke
php bin/phpunit --group smoke

# Tous les workflow tests
php bin/phpunit --group task-complex
php bin/phpunit --group project-complex

# Tout le BC Task (CRUD + workflow)
php bin/phpunit --group task
```

---

## Checklist avant de committer le WorkflowTest

- [ ] Le fichier est dans `tests/E2E/{BC}/{BC}WorkflowTest.php`
- [ ] 4 méthodes de test (un par scénario)
- [ ] Scénarios 1 et 2 ont `#[Group('smoke')]`
- [ ] Tous les emails/slugs sont préfixés de façon unique (éviter collisions entre tests)
- [ ] Le scénario 1 couvre toutes les entités des phases précédentes + le nouveau BC
- [ ] Le scénario 4 teste explicitement owner / CAN_EDIT / CAN_COMMENT / outsider
- [ ] Chaque assertion a un message explicatif pour les 403 attendus
- [ ] Vérification d'état final via GET après les mutations (pas seulement le status code)

---

## Lien avec apex:milestone

Ce skill est invoqué dans la **Phase 2 : Test** de `apex:milestone` lorsque le milestone clôt un Bounded Context majeur.

Voir : `skills/apex/orchestrators/milestone/SKILL.md`
