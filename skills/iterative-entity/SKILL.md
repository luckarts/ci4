---
name: iterative-entity
description: "Guide de developpement iteratif des entites - POC → MVP → Phase 3 par feature, avec le pattern Expand/Contract pour les migrations safe"
argument-hint: [poc|mvp|migration|expand-contract]
triggers:
  - poc entite
  - iteration entite
  - migration safe
  - expand contract
  - ajouter attribut
  - entite incomplete
  - evoluer schema
  - phase 1 phase 2 phase 3
  - quand ajouter colonne
---

# Guide : Developpement Iteratif des Entites

Le schema de base de donnees n'est **jamais complet au depart**. Les entites evoluent en permanence — c'est normal, c'est attendu. Ce skill definit comment iterer de facon safe.

---

## Principe fondamental

> Ne pas confondre "concevoir l'entite complete" avec "concevoir l'API complete".

| Couche | Approche | Raison |
|--------|----------|--------|
| **Schema Doctrine** | Iteratif, migrations additives | Toujours incomplet au depart — requirements evoluent |
| **API exposee** | Iteratif, vertical slice | Livrer de la valeur rapidement par feature |
| **Regles metier** | Iteratif, ajout par MVP | La logique emerge avec l'usage reel |

---

## Le cycle par Bounded Context

La bonne granularite est **le Bounded Context**, pas l'application entiere.

```
POC  → prouve que le BC fonctionne dans l'architecture
MVP  → livre la valeur metier principale
Ph3  → fonctionnalites avancees / relations complexes
```

### Exemple concret : BC Task

```
Sprint 1 — POC Task
  Entite  : id, title, status
  API     : POST /tasks, GET /tasks
  Objectif: prouver que le BC fonctionne

Sprint 3 — MVP Task
  Migration: ADD COLUMN assignee_id, due_date        ← additive, safe
  API      : PATCH /tasks/{id}, filtres, validation
  Objectif : livrer la valeur metier principale

Sprint 6 — Phase 3 Task
  Migration: CREATE TABLE task_tags, task_dependencies  ← additive, safe
  API      : POST /tasks/{id}/tags, dependencies
  Objectif : fonctionnalites avancees
```

---

## Pattern Expand / Contract

Le vrai enjeu n'est pas d'eviter les migrations — c'est de les rendre **safe en production**.

### Les 3 types de changements

| Type | Risque | Strategie |
|------|--------|-----------|
| **Additive** (ADD COLUMN, CREATE TABLE) | Aucun | Faire directement |
| **Transformatif** (changer une logique, renommer en interne) | Faible | Expand puis Contract |
| **Cassant** (DROP COLUMN, changer type, renommer colonne) | Eleve | Pattern complet ci-dessous |

### Le pattern complet pour les changements cassants

```
Phase 1 — EXPAND
  → Ajouter la nouvelle colonne (nullable)
  → Faire coexister ancien + nouveau
  → Deployer en prod

Phase 2 — MIGRATE
  → Remplir la nouvelle colonne avec les donnees existantes
  → Script de migration data (pas DDL)
  → Verifier l'integrite

Phase 3 — SWITCH
  → Le code utilise uniquement la nouvelle colonne
  → L'ancienne est ignoree mais toujours presente

Phase 4 — CONTRACT
  → DROP COLUMN de l'ancienne colonne
  → Seulement apres N releases (securite)
```

### Exemple : renommer `is_completed` → `status` (Enum)

```php
// Phase 1 — Expand : ajout de la nouvelle colonne
#[ORM\Column(type: 'string', enumType: TaskStatus::class, nullable: true)]
private ?TaskStatus $status = null;

// Phase 2 — Migrate : remplir status depuis is_completed
UPDATE task SET status = CASE
    WHEN is_completed = true  THEN 'completed'
    WHEN is_completed = false THEN 'todo'
END;

// Phase 3 — Switch : le code utilise uniquement status
// Phase 4 — Contract : DROP COLUMN is_completed (release suivante)
```

---

## Arbre de decision : dois-je ajouter cette colonne maintenant ?

```
Nouveau besoin metier identifie
│
├─ Est-ce une colonne nullable avec default ?
│  └─ OUI → Ajouter immediatement (migration additive, zero risque)
│
├─ Est-ce une nouvelle table de relation ?
│  └─ OUI → Ajouter immediatement (CREATE TABLE = toujours additive)
│
├─ Est-ce un renommage ou changement de type ?
│  └─ OUI → Pattern Expand/Contract (voir ci-dessus)
│
├─ Est-ce que ca concerne le MVP actuel ?
│  ├─ OUI → Faire maintenant
│  └─ NON → Ajouter dans la backlog Phase suivante
│
└─ Est-ce que c'est "nice to have" sans use case concret ?
   └─ OUI → YAGNI — ne pas ajouter
```

---

## Ce qu'il ne faut PAS faire

### BDUF (Big Design Up Front)

```php
// ❌ Concevoir l'entite "complete" avant d'avoir les requirements
class Task {
    private string $title;
    private ?string $description;
    private TaskStatus $status;
    private ?User $assignee;
    private ?\DateTimeImmutable $dueDate;
    private ?\DateTimeImmutable $startDate;
    private ?int $estimatedHours;
    private ?int $actualHours;
    private ?string $externalId;        // pour future integration
    private ?array $customMetadata;     // pour future extension
    // 20 champs pour un POC...
}
```

### Raison : le BDUF est du gaspillage

- Les requirements que tu "anticipes" changent 80% du temps
- Les colonnes non utilisees sont du bruit dans les migrations et le schema
- Chaque colonne ajoutee sans use case est une dette de maintenance

---

## Ce qu'il faut faire

```php
// ✅ POC : le strict minimum qui prouve le concept
class Task {
    private Uuid $id;
    private string $title;
    private TaskStatus $status;      // Enum : todo, in_progress, done
}

// ✅ MVP (sprint +2) : ajout des attributs metier reels
// Migration additive — aucun risque
ALTER TABLE task ADD COLUMN assignee_id UUID NULL;
ALTER TABLE task ADD COLUMN due_date TIMESTAMP NULL;

// ✅ Phase 3 (sprint +5) : relations complexes si le besoin est confirme
CREATE TABLE task_tags (...);
CREATE TABLE task_dependencies (...);
```

---

## Granularite POC/MVP/Phase 3

| Niveau | Granularite | Quand |
|--------|-------------|-------|
| **Par application** | Trop large | Bloque tous les developpeurs, time-to-value > 6 mois |
| **Par Bounded Context** | Correct | Un BC est independant et livrable |
| **Par attribut** | Trop fin | Cree du bruit dans les migrations et le planning |

**Regle pratique** : le cycle POC → MVP → Phase 3 s'applique au BC entier, pas a chaque champ individuellement.

---

## Checklist avant chaque migration

- [ ] La migration est-elle **additive** ? (ADD COLUMN nullable, CREATE TABLE)
- [ ] Si changement cassant : le pattern Expand/Contract est-il applique ?
- [ ] La migration est-elle **reversible** (down() implementee) ?
- [ ] La colonne ajoutee a-t-elle un **use case concret** dans le sprint en cours ?
- [ ] Les entites existantes gerent-elles la **valeur par defaut** correctement ?

---

## Reference : types de migrations Doctrine

```php
// ✅ Safe — toujours additive
$table->addColumn('due_date', 'datetime', ['notnull' => false]);
$schema->createTable('task_tags');

// ⚠️ Risque moyen — necessite Expand/Contract
$table->changeColumn('status', ['type' => Type::getType('string')]);

// ❌ Dangereux en prod — ne jamais faire directement
$table->dropColumn('old_field');
$table->renameColumn('old_name', 'new_name');  // pas supporte par tous les SGBD
```
