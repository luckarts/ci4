# Session — 2026-04-24 · `feature/backend/authentification-oauth2`

## PalaceWork — CI3 → CI4 Task Manager

> Task manager personnel et collaboratif où chaque tâche porte son palace de connaissance.

- **Backend**  : CodeIgniter 3 · PHP 8+ · PostgreSQL · OAuth2 (bshaffer) · State Machine PHP pure
- **Frontend** : React · TanStack Query · TypeScript
- **Infra**    : Docker Compose · GitHub Actions · pgvector (conditionnel)

**Architecture MVP** :
- Monorepo structure — backend (CI3→CI4), frontend (React), palace (MemPalace lib), docker
- Modules HMVC — Bounded Contexts (Auth, Task, Project, Shared, Palace, Org)
- Database first — PostgreSQL + MemPalace embeddings (L0/L1 wake-up context)
- Queue worker — Table DB + CLI workers PHP (ASYNC001)
- State Machine — Classe PHP pure (TaskStateMachine)

---

## Décisions architecturales clés

**Auth Strategy** : OAuth2 via bshaffer/oauth2-server-php avec JWT tokens
- Protégé rate limiting sur `POST /token` (SEC003)
- Pattern : `/auth/authorize`, `/auth/token`, `/auth/revoke`

**Data Model** : Dual-store linking
- PostgreSQL : le *quoi* (structured data)
- MemPalace : le *pourquoi* (knowledge context, embeddings)
- Clé logique : `task.uuid` relie les deux stores

**Testing Strategy** : Pyramide TDD (0 DB → mocks → intégration → e2e)
- Règle AUTH-INT001 : tout service mocké → ≥1 test intégration réel
- PHPUnit 9 + GitHub Actions pipeline

**Git Flow** : PRs vers `develop`, `main` = production stable
- Commits conventionnels (`feat:`, `fix:`, `refactor:...`)
- Jamais `Co-Authored-By`
- Jamais committer `.gitignore`

---

## Roadmap — 7 phases

| # | Phase | Contenu |
|---|-------|---------|
| 1 | **Fondation** | OAuth2, rate limiting, CI, module split (ARCH009) |
| 2 | **MVP Perso** | Tasks, State Machine CI3, tags, comments (TM03-MVP → TM07-MVP) |
| 3 | **Phase PalaceWork** | MemPalace HTTP adapter, Queue Worker, knowledge layer |
| 4 | **Pivot SaaS** | Multi-tenant, invitations, PostgreSQL RLS |
| 5 | **Collaboration** | Activity Feed, SSE notifications, sharing |
| 6 | **Visualisation** | Graph (D3.js / Cytoscape), knowledge browsing |
| 7 | **Migration CI4** | MIG001–MIG010 — apprentissage incrémental |

---

## Modules en cours (Bounded Contexts)

- ✅ `modules/Shared/` — Entities, repositories, shared kernel
- ⏳ `modules/Auth/` — OAuth2, rate limiting, token management
- ⏳ `modules/Task/` — State machine, task lifecycle
- ⏳ `modules/Project/` — Project grouping, access control
- 🔜 `modules/Palace/` — MemPalace HTTP adapter, embeddings sync
- 🔜 `modules/Org/` — Multi-tenant foundations

---

## Conventions à respecter

```
Plan avant implémentation :
  → Écrire le plan complet et attendre validation

Bash :
  → Une commande à la fois, pas de chaînage `&&`

Réponses :
  → Pas de résumé en fin de réponse
  → Opérations mémoire silencieuses

Tests :
  → Dès qu'un service est mocké, ≥1 test intégration réel (AUTH-INT001)
  → Signal d'alerte : tous les tests d'un flow mockent le même endpoint

Architecture & Karpathy Guidelines :
  → Prefer simple, readable code over clever abstractions
  → Minimize dependencies — every dependency is a liability
  → Delete code aggressively — the best code is no code
  → Prototypes over frameworks; understand primitives first
```

---

## Prochaines étapes (ordre priorité)

1. **Authentification OAuth2** (branche actuelle)
   - Implémenter le module `Auth` avec bshaffer
   - Tests intégration + e2e

2. **State Machine Task**
   - Implémenter TaskStateMachine (PHP pure)
   - Transitions : draft → open → done → archived

3. **MemPalace HTTP adapter**
   - Lier `task.uuid` ↔ palace drawer
   - Sync L0 context automatique

4. **Queue Worker**
   - Implémenter table DB + CLI worker
   - Event listeners (TaskCreated, TaskUpdated, etc.)

5. **Migration CI4** (phase apprentissage)
   - Évaluer le delta CI3 → CI4
   - Plan d'adoption incrémentale

---

*Session minée depuis MemPalace palace le 2026-04-24*
*Branch: feature/backend/authentification-oauth2*
*Projet: ci4 (125 fichiers minés, 912 drawers)*
