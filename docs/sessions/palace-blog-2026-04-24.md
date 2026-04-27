# Palace Blog — 2026-04-24 · `feature/backend/authentification-oauth2`

**Commit** : 841c18d

---

## 📖 Vision du Projet

PalaceWork est un task manager personnel et collaboratif où chaque tâche porte son palace de connaissance.

**Caractéristiques clés** :
- Dual-store : PostgreSQL (le *quoi*) + MemPalace (le *pourquoi*)
- Architecture modulaire HMVC avec Bounded Contexts
- OAuth2 + JWT authentication
- State Machine pour lifecycle des tâches
- Queue worker asynchrone
- Knowledge graph intégré (pgvector embeddings)

---

## 🏗️ Architecture

### Stack Technique

**Confirmé dans le palace** :
- ✅ bshaffer
- ✅ ci3
- ✅ ci4
- ✅ codeigniter
- ✅ d3
- ✅ docker
- ✅ jwt
- ✅ mempalace
- ✅ oauth2
- ✅ pgvector
- ✅ php
- ✅ phpunit
- ✅ postgresql

**Déclaré mais absent du palace** :
- ❌ composer
- ❌ github-actions
- ❌ jquery
- ❌ makefile
- ❌ twig


### Monorepo Structure

```
PalaceWork/
├── backend/           # CodeIgniter 3 & 4
│   ├── app/
│   ├── application/modules/
│   │   ├── Auth/
│   │   ├── Task/
│   │   ├── Project/
│   │   ├── Shared/
│   │   ├── Palace/
│   │   └── Org/
│   └── tests/
├── frontend/          # React + TanStack Query
├── palace/            # MemPalace lib (MIT v3.3.0)
├── docker/            # Docker Compose setup
├── scripts/palace/    # Mining & verification tools
└── Makefile
```

---

## 🎯 Modules (Bounded Contexts)

| Module | Status | Role |
|--------|--------|------|
| **Shared** | ✅ Ready | Base entities, repositories, kernel |
| **Auth** | ⏳ In Progress | OAuth2, JWT, rate limiting (current branch) |
| **Task** | ⏳ In Progress | State Machine, lifecycle |
| **Project** | ⏳ In Progress | Grouping, access control |
| **Palace** | 🔜 Next | MemPalace HTTP adapter, sync |
| **Org** | 🔜 Later | Multi-tenant, RLS |

---

## 🚀 Roadmap (7 phases)

1. **Fondation** — OAuth2, rate limiting, CI, module split
2. **MVP Perso** — Tasks, State Machine, tags, comments
3. **Phase PalaceWork** — MemPalace adapter, Queue Worker, knowledge layer
4. **Pivot SaaS** — Multi-tenant, invitations, PostgreSQL RLS
5. **Collaboration** — Activity Feed, SSE notifications, sharing
6. **Visualisation** — Graph (D3.js / Cytoscape), knowledge browsing
7. **Migration CI4** — Apprentissage incrémental CI3→CI4

---

## ✅ Conventions Clés

### Git & Commits
- PRs vers `develop` (pas `main`)
- Commits conventionnels : `feat:`, `fix:`, `refactor:`
- **Jamais** `Co-Authored-By`
- **Jamais** committer `.gitignore`

### Code & Architecture
- Plan avant implémentation (attendre validation)
- Une commande bash à la fois (pas de `&&`)
- Karpathy guidelines : simple > clever, minimal deps
- Delete code aggressively

### Testing (Pyramide TDD)
- Unit tests : 0 DB (mocks)
- Integration tests : vraie DB PostgreSQL
- E2E tests : full stack
- **Règle AUTH-INT001** : tout service mocké → ≥1 test intégration réel

### Réponses & Mémoire
- Pas de résumé en fin de réponse
- Opérations mémoire silencieuses

---

## 📊 Métriques Palace

| Métrique | Valeur |
|----------|--------|
| Fichiers minés | 125 |
| Drawers créés | 912 |
| Rooms | 6 (skills, backend, general, testing, documentation, scripts) |
| Technologies détectées | 13 ✅ + 5 ❌ |
| Wake-up context | ~1393 tokens (L0+L1) |

---

## 🔧 Prochaines étapes

**Branche actuelle** : `feature/backend/authentification-oauth2`

### Immédiat
1. ✅ **Authentication OAuth2** (en cours)
   - Implémenter `/auth/authorize`, `/auth/token`, `/auth/revoke`
   - Rate limiting SEC003
   - Tests intégration AUTH-INT001

2. **State Machine Task**
   - Transitions : draft → open → done → archived
   - Events : TaskCreated, TaskUpdated, TaskCompleted

3. **MemPalace HTTP Adapter**
   - Lien automatique task.uuid ↔ palace drawer
   - Embeddings sync

### Phase 2
- Queue Worker implémentation
- Migration CI3 → CI4 strategy
- Frontend React setup

---

## 📝 Utilisation du Palace

**Avant de coder** :
```bash
mempalace search "ce que tu cherches"
```

**Exemples** :
- `mempalace search "authentication"` — OAuth2 pattern
- `mempalace search "state machine"` — Task lifecycle
- `mempalace search "testing"` — Pyramide TDD
- `mempalace search "migrations"` — Schema evolution

---

*Blog généré automatiquement par `scripts/palace/blog_generator.py`*
*Branch: feature/backend/authentification-oauth2 · Commit: 841c18d*
*Date: 2026-04-24*
