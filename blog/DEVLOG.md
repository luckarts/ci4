# DevLog — PalaceWork CI3

**Date** : 2026-04-22  
**Status** : MVP Perso en cours  
**Stack** : CodeIgniter 3 · PHP 8+ · PostgreSQL · Twig + jQuery

---

## Vision du projet

**PalaceWork** — Task manager personnel et collaboratif où chaque tâche porte son palace de connaissance.

Deux stores indépendants liés par `task.uuid` :
- **PostgreSQL** — le *quoi* : titre, statut, assignee, colonnes, dates
- **MemPalace** — le *pourquoi* : décisions prises, contexte des sessions Claude, alternatives rejetées

Quand un dev travaille sur une tâche avec Claude, la conversation est automatiquement indexée dans MemPalace. L'onglet Notes de la tâche affiche ce contenu — journal de bord alimenté sans saisie manuelle.

---

## Structure Monorepo

```
PalaceWork/
├── backend/                  # CI3 application
│   ├── application/
│   │   ├── modules/          # Bounded Contexts (HMVC léger)
│   │   │   ├── Auth/         # ⏳ En cours
│   │   │   ├── Task/         # ⏳ En cours
│   │   │   ├── Project/      # ⏳ En cours
│   │   │   ├── Palace/       # 🔜 Phase PalaceWork
│   │   │   └── Shared/       # ⏳ En cours
│   │   ├── config/
│   │   └── libraries/
│   ├── system/               # CI3 core
│   └── public/               # index.php
├── palace/                   # MemPalace lib (MIT v3.3.0)
├── docker/                   # Docker Compose
├── tests/
│   ├── unit/                 # PHPUnit — 0 DB, 0 CI3 bootstrap
│   └── integration/          # PHPUnit — vraie DB requise
├── docs/                     # Documentation
├── scripts/palace/           # Extraction & verification tools
├── .memtags.yml              # Ground truth pour la stack
└── Makefile
```

---

## Stack technique (Ground Truth)

| Couche | Technologie | Status |
|--------|------------|--------|
| **Backend** | CodeIgniter 3 · PHP 8+ | ✅ Actif |
| **Database** | PostgreSQL 14+ | ✅ Actif |
| **Auth** | OAuth2 (bshaffer/oauth2-server-php) | ✅ Fondation |
| **Queue** | Table DB + workers PHP CLI | ⏳ ASYNC001 |
| **Frontend** | Twig · jQuery · AJAX | ✅ Actif |
| **Graphs** | D3.js (vues standalone) | 🔜 Phase 4 |
| **Tests** | PHPUnit 9 | ✅ Fondation |
| **CI/CD** | GitHub Actions | ✅ Fondation |
| **Memory** | MemPalace (local, MIT v3.3.0) | ✅ Actif |
| **Vectorization** | pgvector (PostgreSQL) | 🔜 SPIKE-PALACE-001 |

---

## Architecture — Patterns MVP

### Repository Pattern

```
Controller → Service → Repository → Model → DB
```

Le service ne connaît pas la DB — il demande au repository. Ça permet de swapper la source de données (MySQL → Redis → API externe) sans toucher au service.

### Event / Observer

```
Service → dispatch(TaskCreated)
                ↓
           NotificationListener
           ActivityLogListener
           EmailListener
```

Les listeners sont synchrones dans un premier temps (EVENT-001). La migration vers async (Queue Worker CI3) se fait sur signal de contention — EVENT-006.

---

## Roadmap — Phases

### Phase 1 — Fondation (ACTUEL)

| ID | Feature | Contenu | Status |
|----|---------|---------|--------|
| F001 | User OAuth2 + Auth | Auth CI3, token endpoint | ✅ Complété |
| SEC001 | Refresh token | TTL access/refresh configurable | ⏳ En cours |
| SEC002 | Logout / Revocation | POST /token/revoke | 🔜 TODO |
| SEC003 | Rate limiting OAuth2 | Protection brute-force | 🔜 TODO |
| OPS001 | Healthcheck | GET /health — statut DB | 🔜 TODO |
| AUTH-INT001 | Tests intégration OAuth2 | ≥1 test sans mock par flow | ⏳ En cours |
| CI001 | CI Workflow | GitHub Actions 3 niveaux | ✅ Complété |
| ARCH009 | Module Split BC | Organisation modules/`{bc}` | ✅ Complété |

### Phase 2 — MVP Perso

| ID | Feature | Contenu |
|----|---------|---------|
| TM02-MVP | Project perso | `project.user_id` (sans org) |
| TM03-MVP | Task enrichie | `due_date`, `is_completed`, `order_index`, `column_id` |
| TM03-F002 | Subtasks | `parent_task_id` FK nullable |
| TM06-MVP | Tag | CRUD + Task-Tag M2M |
| TM04-MVP | Comment | Lié à task, simple |
| TM07-MVP | State Machine CI3 | `todo → in_progress → in_review → done` |

Event refactoring après TM07-MVP :
```
TaskCreated · TaskStatusChanged → listener interne synchrone
```

### Phase 2.5 — Dépendances

| ID | Feature | Contenu |
|----|---------|---------|
| TM08 | TaskDependency | DAG + cycle detection |

→ **App perso complète avant PalaceWork**

### Phase 3 — PalaceWork (Knowledge Layer)

| ID | Feature | Contenu |
|----|---------|---------|
| PALACE-001 | MemPalace HTTP adapter | REST API, storage isolé par projet |
| PALACE-002 | UUID task ↔ palace link | Création wing/room auto à chaque TaskCreated |
| SESSION-CAPTURE-001 | Session Claude → palace | Extract JSONL → mine avec wing = task.uuid |
| TM03-ADV | Task module complet | API, tags sémantiques, milestone |
| TM06-ADV | Tags sémantiques | Tags → rooms cross-tasks |

### Phase 4+ — SaaS & Collaboration

| ID | Phase | Contenu |
|----|-------|---------|
| TM00-F002 | Pivot SaaS | Email verification |
| TM01-F001 | Pivot SaaS | Organization |
| ACT001 | Collaboration | Activity Feed |
| NOT01 | Collaboration | SSE notifications |
| VIZ001 | Visualisation | D3.js graph (standalone) |

---

## Décisions architecturales clés

### 1. HMVC Léger (modules Bounded Contexts)

Au lieu d'un monolithe CI3 flat, chaque module métier (Auth, Task, Project, Palace) vit dans son répertoire isolé :

```
application/modules/Auth/
├── controllers/
├── models/
├── libraries/
└── config/

application/modules/Task/
├── controllers/
└── ...
```

**Avantage** : isolation de contexte, réutilisable lors de la migration CI4.

### 2. Repository Pattern de jour 1

Service ne parle pas à ActiveRecord, passe par Repository. Quand la DB change ou qu'on ajoute un cache, c'est isolé.

### 3. Event Bus synchrone d'abord

`TaskCreated` → listeners synchrones (notification, log). Bascule vers async (Queue Worker) **sur signal**, pas de sur-engineering.
 Option A — DIY Event Bus (recommandé MVP)                                                                       
   
  <?php                                                                                                           
  // application/libraries/EventBus.php                                                                         
  class EventBus {
      private static $listeners = [];
                                                                                                                  
      public static function subscribe($event, $callback) {
          if (!isset(self::$listeners[$event])) {                                                                 
              self::$listeners[$event] = [];                                                                    
          }
          self::$listeners[$event][] = $callback;
      }
                                                                                                                  
      public static function dispatch($event, $data = []) {
          if (isset(self::$listeners[$event])) {                                                                  
              foreach (self::$listeners[$event] as $callback) {                                                 
                  call_user_func($callback, $data);
              }                                                                                                   
          }
      }                                                                                                           
  }                                                                                                             

  // Usage dans un Service :
  EventBus::dispatch('TaskCreated', ['task_id' => $task->id, 'user_id' => $task->user_id]);
         

### 4. PostgreSQL + pgvector (conditionnel SPIKE-PALACE-001)

PostgreSQL est la source de vérité (quoi). pgvector optionnel pour MemPalace embeddings (pourquoi). Permet une décision d'intégration différée.

### 5. Twig + jQuery, pas React

Frontend : rendu serveur CI3 (Twig) + jQuery pour l'interactif léger. Pas de build frontend complexe. D3.js standalone quand les graphs arrivent.

---

## Conventions de dev

### Commits

```
feat(task): add task creation endpoint
fix(auth): handle token expiration edge case
refactor(repository): extract db query logic
```

- Conventionnels (`feat:`, `fix:`, `refactor:`, `chore:`, `test:`, `docs:`)
- Jamais `Co-Authored-By`
- Jamais `.gitignore` committé

### Branches

- PRs vers `develop`
- `main` = production stable
- Feature branches : `feature/ci3/xxx`

### Tests

**Règle AUTH-INT001** : tout service mocké en smoke/e2e → ≥1 test intégration sur le vrai service.

```bash
make test           # PHPUnit complète
make test-unit      # Unitaires seulement (0 DB)
make test-int       # Intégration (DB requise)
```

### Plan avant implémentation

Tout feature :
1. Écrire le plan complet
2. Attendre validation
3. Coder

---

## Vérification & Maintenance

### `.memtags.yml` — Ground Truth de la stack

```yaml
project: ci3-tasks

stack_declared:
  - ci3, ci4, codeigniter, php, postgresql, pgvector
  - twig, jquery, docker, github-actions, mempalace
  - oauth2, bshaffer, phpunit, composer, makefile

stack_pending: []       # Tags trouvés mais non confirmés
stack_ignored:
  - react, typescript, tanstack  # Rejetés comme incorrects
```

### `memverify.py` — Audit de cohérence

```bash
# Vérifier que le palace ne contient pas de refs React/TypeScript
python3 scripts/palace/memverify.py

# Promouvoir un tag pending → declared
python3 scripts/palace/memverify.py --promote jwt

# Rejeter un tag erroné
python3 scripts/palace/memverify.py --dismiss react
```

### `extract_session.py` — Blog depuis sessions

```bash
# Générer un résumé blog d'une session
python3 scripts/palace/extract_session.py --markdown session.jsonl > blog.md

# Support chunk markers : <!-- chunk: skill --> ... <!-- /chunk -->
# Tags hors-scope exclus du résumé
```

---

## Prochaines étapes (ordre de priorité)

1. **SEC001 + SEC002** — Refresh token & logout (complète Fondation Auth)
2. **TM02-MVP → TM07-MVP** — MVP Perso (Tasks, State Machine, Tags, Comments)
3. **EVENT-001** — Event Bus CI3 + listeners synchrones
4. **ASYNC001** — Queue Worker CI3 (prérequis PalaceWork async)
5. **PALACE-001 → PALACE-002** — MemPalace HTTP adapter & linking
6. **Pivot SaaS** — Organisation, multi-tenant
7. **Phase 4** — Collaboration, Visualisation

---

## Ressources

- **Roadmap détaillée** → [`roadmaps.md`](../roadmaps.md)
- **Configuration** → [`CLAUDE.md`](../CLAUDE.md), [`AGENTS.md`](../AGENTS.md)
- **Stack vérification** → [`.memtags.yml`](../.memtags.yml)
- **MemPalace** → [`palace/`](../palace/)
- **Tests** → [`tests/`](../tests/)
