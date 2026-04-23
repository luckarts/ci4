# Roadmap — PalaceWork CI4 API

---

## Structure

```
ci4-api/
├── app/              # CI4 application
│   ├── Controllers/  # API endpoints
│   ├── Models/       # Data models
│   ├── Filters/      # Middleware (auth, rate limiting)
│   ├── Config/
│   └── Libraries/    # Shared logic
├── public/           # index.php — API entry point
├── palace/           # MemPalace lib integration
├── docker/           # Docker compose + configs
└── Makefile
```

---

## Stack technique

### Backend API
- CodeIgniter 4 · PHP 8.1+ · PostgreSQL
- Auth : OAuth2 / JWT
- Queue : table DB + workers PHP CLI (ASYNC001)
- State Machine : classe PHP pure (TaskStateMachine)
- Architecture : Namespaced Controllers + Models + Services (PSR-4)

### Frontend (external)
- Nuxt 3 (separate repository, consumes this API)
- Graph visualization via Nuxt client

### Infra
- Docker Compose · GitHub Actions (lint / test)
- pgvector (MemPalace embeddings — conditionnel SPIKE-PALACE-001)

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

## Pourquoi cette app existe

### Le problème

Quand on travaille sur un projet complexe — seul ou en équipe — on accumule des décisions, des alternatives rejetées, des contraintes techniques. Tout ça vit dans les conversations, les PR, les têtes des gens. Quand quelqu'un reprend le travail (le lendemain, dans 6 mois, une autre personne, une autre IA), le code est là mais le *pourquoi* a disparu. On refait les mêmes erreurs, on repasse par les mêmes alternatives déjà explorées.

### La réponse

**PalaceWork** est un task manager où chaque tâche porte son palace de connaissance. Deux stores indépendants liés par `task.uuid` :

- **PostgreSQL** — le *quoi* : titre, statut, assignee, colonnes, dates
- **MemPalace** — le *pourquoi* : décisions prises, contexte des sessions Claude, alternatives rejetées, contraintes découvertes

Quand un dev travaille sur une tâche avec Claude, la conversation est automatiquement indexée dans MemPalace liée à la tâche. L'onglet Notes de la tâche affiche ce contenu — comme un journal de bord alimenté sans saisie manuelle.

### Principe de résilience — Mode étudiant

Ce projet est conçu pour suivre l'avancement d'autres projets, sans dépendre d'une IA spécifique. Chaque feature développée laisse une trace complète : les commandes utilisées, les décisions prises, les tests qui prouvent que ça marche. C'est le "mode étudiant" — on peut relire le parcours et comprendre comment on en est arrivé là, pas seulement voir le résultat final. La migration CI3 → CI4 s'inscrit dans cette logique : comprendre les différences architecturales, pas seulement faire tourner le code.

---

> Ordre d'exécution optimal · chaque phase produit une app deployable et testable.
> Ref schéma DB → `docs/database-evolution.md`

---

## Fondation (parallèles)

| ID | Feature | Contenu |
|----|---------|---------|
| F001 | User OAuth2 + Auth | Auth CI3 (bshaffer/oauth2-server-php), token endpoint, AuthController |
| SEC001 | Refresh token | `refresh_token` endpoint, TTL access/refresh configurable, rotation de token |
| SEC002 | Logout / Revocation | `POST /token/revoke` — blacklist ou invalidation `jti` ; sécurité minimale sans ça |
| SEC003 | Rate limiting OAuth2 | Rate limiting CI3 (middleware ou library) sur `POST /token` — protection brute-force ; à poser en Fondation, pas en Phase 3 |
| OPS001 | Healthcheck | `GET /health` — statut DB + services critiques ; prérequis tout déploiement (Docker, k8s, load balancer) |
| AUTH-INT001 | Tests intégration OAuth2 | ≥1 test sans mock par flow Auth — credentials invalides + valides ; complément obligatoire aux tests smoke (règle : tout service mocké a ≥1 test intégration réel) |
| CI001 | CI Workflow | GitHub Actions 3 niveaux (lint / test / e2e) |
| ARCH009 | Module Split BC | Organisation en modules CI3 — `application/modules/{bc}/` isolé par BC, interfaces de contrat dans `modules/Shared/`, autoload par module ; critère done : chaque module charge ses dépendances de manière autonome, 0 import cross-module hors `modules/Shared/` |

---

## Outillage Dev — Prérequis Développement

| ID | Feature | Contenu |
|----|---------|---------|
| TEST-INFRA001 | Seeds CI3 | seeds de base (user, project) pour dev local et seed CI — actuellement les E2E créent tout à la volée, pas de seed stable ; org ajoutée aux seeds lors du Pivot SaaS |
| DOC001 | .env.example | documenter toutes les vars requises (`DATABASE_URL`, OAuth2 client/secret, `MAILER_DSN`...) ; bloquant pour tout onboarding |
| ARCH011 | Convention migrations globale | guide Expand/Contract pour breaking changes DB — à définir avant TM02-UPD ; évite les migrations destructives non orchestrées |
| SESSION-CAPTURE-POC | Bootstrap palace — capture active dès le MVP | poc-palace-blog en mode manuel dès le début du développement MVP — 3 sources de données réelles : (1) session poc-palace-blog déjà capturable, (2) sessions Claude de dev MVP capturées en temps réel, (3) ce projet (`PalaceWork-CI3`) utilisé comme projet de démo avec palace déjà riche au lancement SaaS ; zéro seed artificiel — knowledge base organique avant productisation SESSION-CAPTURE-001 |

---

## POC

| ID | Feature | Contenu |
|----|---------|---------|
| TM00-POC | User + OAuth2 | Auth CI3, token endpoint |
| TM03-POC | Task POC | title, status enum, user_id direct |
| TM04-POC | Comment POC | task_id direct, pas de polymorphisme |

---

## Spike — Prérequis MemPalace

| ID | Feature | Contenu |
|----|---------|---------|
| SPIKE-PALACE-001 | Compatibilité pgvector | Brancher `backends/base.py` MemPalace sur PostgreSQL + pgvector sur schéma minimal — go/no-go avant PALACE-001 ; fallback : jsonb + pg_trgm |

---

## MVP Perso

> App personnelle fonctionnelle et déployable — sans organisation multi-tenant.

| ID | Feature | Contenu |
|----|---------|---------|
| TM02-MVP | Project perso | `project.user_id` (sans org) |
| TM02-COL | BoardColumn | lié au project |
| ARCH012 | Pagination defaults | taille de page, type offset/cursor, config CI3 (`application/config/`) — à cadrer avant TM03 (volumes tâches élevés) |
| TM03-MVP | Task enrichie | `due_date`, `is_completed`, `order_index`, `column_id`, `assignee_id` (FK user nullable) |
| TM03-F002 | Subtasks | `parent_task_id` FK nullable (1 niveau) → materialized path si nesting illimité requis |
| TM06-MVP | Tag | CRUD + Task-Tag M2M |
| TM04-MVP | Comment | lié à task, simple |
| TM07-MVP | State Machine CI3 | `todo → in_progress → in_review → done` — transitions validées dans le Model CI3 |

```
┌─ [EVENT-001] refactoring — après TM07-MVP ────────────────────────┐
│  Event Bus CI3 en place, 0 consumer externe                        │
│  Events : TaskCreated · TaskStatusChanged (State Machine CI3)      │
│           SubtaskCompleted → recalcul % complétion parent          │
│  → listener interne synchrone dans module Task                     │
└───────────────────────────────────────────────────────────────────┘
```

→ **app perso fonctionnelle, déployable — API REST complète**

---

## Phase 2.5 — Dépendances

| ID | Feature | Contenu |
|----|---------|---------|
| TM08 | TaskDependency | DAG + cycle detection |

```
┌─ [EVENT-003] feature — avec TM08 ─────────────────────────────────┐
│  Events : TaskBlocked · TaskUnblocked                              │
│  → consumer : notifier assignee (synchrone)                       │
└───────────────────────────────────────────────────────────────────┘
```

→ **app perso complète avant PalaceWork**

---

## Infra Async — Prérequis PalaceWork

> ASYNC001 positionné ici car EVENT-PALACE-001 le requiert explicitement.

| ID | Feature | Contenu |
|----|---------|---------|
| ASYNC001 | Queue Worker CI3 | infrastructure queue DB/Redis + workers PHP CLI + DLQ — prérequis pour EVENT-PALACE-001 |

---

## Phase PalaceWork — Knowledge Layer (mono-tenant)

> MemPalace core : chaque task porte son palace de connaissance (wings/rooms/drawers).
> Deux stores indépendants liés par `task.uuid` — PostgreSQL (quoi) + MemPalace (pourquoi).
> Ref brief → `.forge/sessions/2026-04-20-taskmanager-claude-memepalace/07-brief-final.md`

### Infrastructure MemPalace — Prérequis

| ID | Feature | Contenu |
|----|---------|---------|
| PALACE-001 | MemPalace HTTP adapter | lib MIT v3.3.0 → API REST, storage isolé par projet — prérequis EVENT-PALACE-001 |
| PALACE-002 | UUID task ↔ palace link | Queue Worker CI3 async → création wing/room auto à chaque `TaskCreated` ; clé logique = `task.uuid` |
| SESSION-CAPTURE-001 | Session Claude → palace | pipeline poc-palace-blog productisé : extract JSONL session → mine MemPalace avec `wing = task.uuid` ; hook post-commit ou trigger manuel ; drawer "Session [date]" par session Claude — **après PALACE-002** |

### Module Task/ — Fondation

| ID | Feature | Contenu |
|----|---------|---------|
| TM03-ADV | Task module complet | roadmap API, column_id, tags comme nœuds sémantiques, milestone, ordre — **après TM03-MVP** |
| TM06-ADV | Tags sémantiques | tags CI3 → rooms cross-tasks dans MemPalace via Event Bus CI3 — **après TM06-MVP** |

```
┌─ [EVENT-PALACE-001] feature — avec TM03-ADV — ASYNC (Queue Worker CI3 + DLQ) ─┐
│  Events : TaskCreated → palace adapter crée wing/room MemPalace                 │
│           TaskTagged  → palace adapter crée/lie room cross-tasks                │
│  Bridge : Queue Worker CI3 async → MemPalace HTTP adapter                       │
│  Lien   : task.uuid = clé logique (pas FK SQL)                                  │
│  ⚠️  Requires PALACE-001/002 + ASYNC001                                         │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### MemPalace — Features Core

| ID | Feature | Contenu |
|----|---------|---------|
| PALACE-005 | Sync CLI auth | `mempalace pull/push` avec JWT — lecture locale zéro-token pour Claude |
| PALACE-006 | Palace snapshot on task close | snapshot immutable quand task → `done` ; dégel explicite "Reprendre cette task" crée un nouveau snapshot base |
| PALACE-007 | Agent diary par task | feature native MemPalace — journal automatique des sessions Claude par task (ce que Claude a cherché/enrichi) ; même RBAC que le palace |
| MULCH-001 | Mulch ↔ App sync | CLI bridge `php index.php cli palace mulch sync` : lecture JSONL mulch → flag palace stale sur `task.uuid` concerné ; inverse : `TaskStatusChanged(done)` → mulch record auto (type=decision, domain=task) — nécessite convention de nommage `task.uuid` dans les records mulch |

> ℹ️ **[mulch](https://github.com/jayminwest/mulch)** — outil CLI agent-side complémentaire à MemPalace. Quand Claude détecte qu'un drawer est périmé (divergence contexte/réalité), il écrit un record `failure` ou `decision` dans mulch → signal consommé par PALACE-008-b pour flaguer le drawer concerné. Positionné avec PALACE-005 : même couche CLI agent-side, zéro couplage SQL.

### API Endpoints — Core

| ID | Feature | Contenu |
|----|---------|---------|
| API-TASK | CRUD tasks | `GET /tasks`, `POST /tasks`, `PUT /tasks/{id}`, `DELETE /tasks/{id}` — statut, assignee, tags |
| API-PALACE | Palace endpoints | `GET /tasks/{id}/palace` — retourne wing/room/drawer structure JSON ; `POST /palace/mine` — queue worker indexation async |
| API-COMMENTS | Comments | `GET /tasks/{id}/comments`, `POST /tasks/{id}/comments` — liés à une tâche |
| API-GRAPH | Dependency graph | `GET /tasks/{id}/dependencies` — nœuds + edges pour visualisation client Nuxt |

→ **API REST complète exposant tasks + palace + dependencies**

---

## Tenant Isolation — Prérequis Pivot SaaS

> Positionné ici intentionnellement : l'isolation doit être correcte **avant** de lancer du multi-tenant en production.
> Le filtrage manuel par `WHERE org_id = ?` dans chaque query est error-prone. Recommandation : implémenter via PostgreSQL Row Level Security (RLS) — évaluation de chaque requête au niveau moteur.

| ID | Feature | Contenu |
|----|---------|---------|
| ARCH001 | Tenant Isolation | PostgreSQL RLS (recommandé) ou filtrage manuel CI3 Query Builder — choix à trancher avant TM01-F001 ; RLS complexifie les migrations mais garantit l'isolation au niveau moteur |
| USR001 | Soft delete utilisateur | `deleted_at` nullable, exclusion des queries — désactivation sans suppression de données ; prérequis RGPD + audit trail avant multi-tenant |
| DOC002 | CONTRIBUTING.md | guide onboarding dev — setup local, conventions, flow git, make commands ; requis avant d'ajouter des devs pour le Pivot SaaS |

```
┌─ [ARCH002] refactoring — sur signal concret ──────────────────────┐
│  Read Model CI3 Query Builder quand une query ActiveRecord         │
│  devient inconfortable                                             │
│  Signal probable : Activity Feed listings ou listings multi-org    │
└───────────────────────────────────────────────────────────────────┘
```

---

## Pivot SaaS

> Premier chemin vers le revenu — task manager collaboratif vendable avec le knowledge layer.

| ID | Feature | Contenu |
|----|---------|---------|
| TM00-F002 | Email verification | vérification email à l'inscription — token mailer, `is_verified` flag ; à trancher avant TM01-F003 Invitation |
| TM01-F001 | Organization | name, slug |
| TM01-F002 | OrganizationMember | OWNER / ADMIN / MEMBER |
| TM01-F003 | Invitation | email → rejoindre org |

```
┌─ [EVENT-002] feature — avec TM01-F003 ────────────────────────────┐
│  1ers consumers réels                                              │
│  Events : MemberInvited → PHPMailer / CI3 Email (synchrone)        │
│           MemberJoined · ProjectMemberAdded                       │
│                                                                    │
│  [EVENT-006-a] signal possible ici : email bloque la réponse API  │
│  → MemberInvited migre en async (Queue Worker CI3) si contention  │
└───────────────────────────────────────────────────────────────────┘
```

| ID | Feature | Contenu |
|----|---------|---------|
| TM02-UPD | Migration | `project.user_id → project.org_id` (Expand/Contract) |
| TM02-F002 | ProjectMember | qui voit quoi |

→ **task manager multi-tenant vendable avec knowledge layer**

---

## Phase PalaceWork — Extension Multi-tenant

> Couche multi-tenant par-dessus le core PalaceWork : isolation par org, RBAC, pgvector mutualisé.

| ID | Feature | Contenu |
|----|---------|---------|
| PALACE-003 | RBAC palace | filtrage JWT sur `pull` — server sert uniquement ce que le token autorise ; private drawers inclus uniquement dans le pull du owner (`user_id == requester`) |
| PALACE-004 | Private drawers | champ `private` sur drawer — jamais servi aux autres membres, inclus dans pull owner uniquement ; audit trail + politique rétention à définir avant livraison enterprise |
| PALACE-008 | Knowledge graph PostgreSQL server-side | knowledge graph SQLite → PostgreSQL côté serveur multi-tenant (tables dédiées + pgvector embeddings) ; SQLite conservé en mode local uniquement — **conditionnel au résultat de SPIKE-PALACE-001** |

### API Extensions

| ID | Feature | Contenu |
|----|---------|---------|
| API-SEARCH | Semantic search | `GET /search?q=...` — requête pgvector (PALACE-008) à travers tous les projets de l'org ; retourne Tasks/Drawers pertinents ; ex : "Pourquoi avons-nous choisi PostgreSQL ?" — **après PALACE-008** |

→ **API enterprise exposant palaces structurées par org + semantic search**

---

## Phase 3 — Collaboration

| ID | Feature | Contenu |
|----|---------|---------|
| TM04-ADV | Comment avancé | mentions, reactions — **après TM04-MVP** |
| ATT01 | Attachment | upload / download / delete |

```
┌─ [EVENT-004] refactoring — avant ACT001 ──────────────────────────┐
│  Retrofit tous les events précédents → ActivityFeedProjection      │
│  EVENT-001 : TaskCreated · TaskStatusChanged · SubtaskCompleted    │
│  EVENT-002 : MemberInvited · MemberJoined                         │
│  EVENT-003 : TaskBlocked · TaskUnblocked                          │
│  Nouveaux  : CommentCreated · CommentMentioned · TaskAssigned      │
│                                                                    │
│  [EVENT-006-b] signal possible ici : volume Activity Feed élevé   │
│  → writes ActivityFeed migrent en async si contention             │
└───────────────────────────────────────────────────────────────────┘
```

| ID | Feature | Contenu |
|----|---------|---------|
| ACT001 | Activity Feed | domain events → timeline |

```
┌─ [EVENT-005] feature — avec NOT01 ────────────────────────────────┐
│  Consumer SSE sur events existants (CommentMentioned, TaskAssigned)│
│  Pas de nouvel event — branche sur EVENT-004                      │
│                                                                    │
│  [EVENT-006-c] signal possible ici : SSE push timeout             │
│  → push SSE migre en async si saturation                          │
└───────────────────────────────────────────────────────────────────┘
```

| ID | Feature | Contenu |
|----|---------|---------|
| NOT01 | Notifications | SSE (Server-Sent Events) CI3 |
| PALACE-009 | Git webhook → auto-mine palace | commit/PR référençant `#task-uuid` → `mempalace mine` auto → drawer "Git activity" dans le palace task ; filtre : message > 100 chars ou PR description non vide ; ⚠️ traiter les commits comme sources potentielles de secrets/PII avant ingestion |
| PALACE-008-b | Staleness detection | Sur `TaskStatusChanged` ou commit référencé (PALACE-009), comparer drawer content vs mulch records du même domaine → flag "⚠️ palace potentiellement périmé" retourné en API ; **prérequis : MULCH-001 + PALACE-009** |
| PALACE-010 | Business-Aware PR Reviewer | Sur webhook PR GitHub/GitLab → Claude lit les drawers de la task liée (PALACE-009) → poste un commentaire sur la PR validant si le code répond aux exigences métier (pas à la syntaxe) ; ⚠️ **conditionnel** : activer uniquement si richesse moyenne des drawers validée ; nécessite accès en écriture API GitHub/GitLab — **après PALACE-009** |
| PALACE-011 | Release Notes Generator | À la fermeture d'un milestone (TM03-ADV) ou en batch sur `TaskStatusChanged(done)`, job async agrège les snapshots PALACE-006 des tasks concernées → génère changelog intelligible pour stakeholders non-techniques via Claude API ; **conditionnel** : qualité des snapshots doit être validée avant activation — **après PALACE-006 + TM03-ADV** |
| API-GRAPH | Knowledge graph endpoint | `GET /knowledge-graph` — [`graphify`](https://github.com/safishamsi/graphify) → knowledge graph depuis artefacts projet ; retourne nœuds (tasks/tags) + edges (dépendances/sémantique) pour visualisation client Nuxt |
| API-SIMILARITY | Cross-task similarity | `GET /tasks/{id}/similar` → graphify vector similarity → retourne "3 tasks avec contexte proche" — consommé par Nuxt pour suggestions intelligentes |

---

## Phase 3 — Infra

| ID | Feature | Contenu |
|----|---------|---------|
| CQRS001 | Command/Query pattern | Handler classes CI3 + Queue Worker — séparation commandes/queries sans bus externe |
| TM01-PH3 | IAM Advanced | Teams, rôles fins |
| OPS002 | Backup PostgreSQL | stratégie backup + restore — WAL archiving ou `pg_dump` schedulé ; prérequis avant déploiement SaaS |
| DOC003 | API docs maintenance | documentation manuelle ou OpenAPI via annotations — CI check diff contrat API pour détecter les régressions de contrat silencieuses |

```
┌─ [EVENT-006] refactoring — incrémental sur signal ────────────────┐
│  ASYNC001 configure l'infra une fois (queue table/Redis + workers) │
│  Chaque event migre indépendamment quand il cause un problème :    │
│                                                                    │
│  EVENT-006-a  MemberInvited      email I/O bloquant               │
│  EVENT-006-b  ActivityFeed writes volume élevé sous charge         │
│  EVENT-006-c  SSE push           saturation connexions             │
│  EVENT-006-d  exports            si feature export existe          │
└───────────────────────────────────────────────────────────────────┘
```

---

## Phase 4 — Graph & Visualization API

> Une fois la collaboration et l'infra stabilisées — feature distinctive PalaceWork exposée en API.

| ID | Feature | Contenu |
|----|---------|---------|
| API-VIZ | Knowledge Graph API | `GET /knowledge-graph` → nœuds (tasks + palaces) + edges (dépendances DAG + relations sémantiques MemPalace) ; consommé par Nuxt pour rendu D3.js / Cytoscape |
| API-VIZ-FILTER | Graph filtering & nav | `GET /knowledge-graph?tags=...&status=...&assignee=...` — zoom cluster · path highlighting entre tâches liées ; côté client Nuxt |

**Dépendances requises avant API-VIZ** :
- TM08 DAG + cycle detection (dépendances inter-tâches)
- MemPalace intégration (lien `task.uuid` → palace)
- API-SIMILARITY (cross-task relations)

```
┌─ [VIZ-ARCH] API graph endpoint ───────────────────────────────────┐
│  Backend : CI4 endpoint `/api/knowledge-graph` → nœuds + edges    │
│  Frontend : Nuxt consomme l'API, rendu côté client D3.js/Cytoscape│
│  CQRS read model (ARCH002) si le graph devient complexe            │
└───────────────────────────────────────────────────────────────────┘
```

---


---

## Catalogue des Domain Events

| ID | Type | Déclencheur | Consumer initial |
|----|------|-------------|-----------------|
| EVENT-001 | refactoring | après State Machine CI3 | listener interne module Task |
| EVENT-002 | feature | avec Invitation | PHPMailer / CI3 Email synchrone |
| EVENT-003 | feature | avec TaskDependency | notifier assignee sync |
| EVENT-004 | refactoring | avant Activity Feed | ActivityFeedProjection |
| EVENT-005 | feature | avec Notifications | SSE CI3 |
| EVENT-006-a/b/c/d | refactoring | sur signal contention | Queue Worker CI3 async |
| ARCH002 | refactoring | sur signal query complexe | CI3 Query Builder read model |
| EVENT-PALACE-001 | feature async | avec TM03-ADV (requires PALACE-001/002 + ASYNC001) | Queue Worker CI3 + DLQ → MemPalace adapter (TaskCreated → wing/room, TaskTagged → room cross-tasks) |
| SESSION-CAPTURE-001 | feature | Phase PalaceWork (après PALACE-002) | pipeline poc-palace-blog productisé → drawer session Claude dans palace task |
| EVENT-PALACE-009 | feature | Phase Collaboration | Git webhook → `mempalace mine` auto sur commit/PR référençant `#task-uuid` |
| EVENT-PALACE-010 | feature | Phase Collaboration (après PALACE-009) | PR webhook → Claude lit drawers → commentaire PR métier ; conditionnel richesse drawers |
| EVENT-PALACE-011 | feature | Phase Collaboration (après PALACE-006 + TM03-ADV) | milestone close ou `TaskStatusChanged(done)` batch → agrège snapshots → Release Notes Claude API |
