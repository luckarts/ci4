# Session Blog — DEVLOG + Commits Récents
**Date**: 2026-04-22 (après 21:03)  
**Branch**: `main`  
**Project**: CI3-tasks (PalaceWork)

---

## 🎯 Projet : PalaceWork

> **Task manager personnel et collaboratif** où chaque tâche porte son palace de connaissance.

### Stack
- **Backend**: CodeIgniter 3 · PHP 8+ · PostgreSQL 14+ · OAuth2 (league/oauth2-server)
- **Frontend**: Twig · jQuery · AJAX · D3.js (standalone)
- **DevOps**: Docker Compose · GitHub Actions · PHPUnit 9
- **Knowledge Layer**: MemPalace (MIT v3.3.0)
- **Vectorization**: pgvector (futur, SPIKE-PALACE-001)

### Architecture MVP
```
Controller → Service → Repository → Model → DB
Service → dispatch(TaskCreated) → [NotificationListener · ActivityLogListener · EmailListener]
```

---

## 📋 Décisions Clés (minées depuis DEVLOG)

### 1. HMVC Léger — Modules par Bounded Context
Au lieu d'un flat monolithe CI3, chaque domaine métier vit isolé :
```
application/modules/{Auth, Task, Project, Palace, Shared}/
  ├── controllers/
  ├── models/
  ├── libraries/
  └── config/
```
**Avantage**: Isolation de contexte, réutilisable lors migration CI4.

### 2. Repository Pattern de jour 1
Service ne parle pas à ActiveRecord — passe par Repository.
- Permet swap DB (MySQL → Redis → API externe) sans toucher Service
- Testabilité : mock Repository en unit tests

### 3. Event Bus Synchrone d'Abord
```php
TaskCreated → [NotificationListener, ActivityLogListener, EmailListener] (synchrone)
```
Async (Queue Worker CI3) **sur signal** de contention — pas de sur-engineering.

### 4. PostgreSQL + pgvector (conditionnel)
PostgreSQL = source de vérité (quoi).  
pgvector optionnel pour MemPalace embeddings (pourquoi).  
Décision d'intégration **différée**, testée via `SPIKE-PALACE-001`.

### 5. Twig + jQuery, pas React
Rendu serveur CI3 (Twig) + jQuery pour l'interactif léger.  
Pas de build frontend complexe. D3.js standalone pour graphs.

---

## ✅ Implémentation — Commit 20e0681

### `feat(db): add PostgreSQL config, users and OAuth2 schema migrations`
**Hash**: `20e06811d820341a1c63ec2da31716a64596cae2`  
**Date**: 2026-04-22 23:06:33 +0200

#### Fichiers ajoutés/modifiés
- `.env.example` + `.env.test` — PostgreSQL credentials
- `application/config/database.php` — driver `postgre`
- `application/config/migration.php` — migrations actives
- `application/controllers/Migrate.php` — CLI migration runner
- `application/migrations/001_create_users_table.php` — users table
- `application/migrations/002_create_oauth_tables.php` — OAuth2 tables (clients, access_tokens, refresh_tokens, scopes, auth_codes)
- `composer.json` — dépendances à jour

#### Dépendances
- `league/oauth2-server` 9.3.0
- `lcobucci/jwt` 5.6.0 (JWT signing)
- `defuse/php-encryption` (refresh token encryption)

---

## 🔜 Roadmap Phase 1 (Fondation)

| ID | Feature | Contenu | Status |
|----|---------|---------|--------|
| F001 | User OAuth2 + Auth | OAuth2 password grant, token endpoint | ✅ **Complété** |
| SEC001 | Refresh token | TTL access/refresh configurable | ⏳ En cours |
| SEC002 | Logout / Revocation | POST /token/revoke | 🔜 TODO |
| SEC003 | Rate limiting OAuth2 | Protection brute-force | 🔜 TODO |
| OPS001 | Healthcheck | GET /health — statut DB | 🔜 TODO |
| AUTH-INT001 | Tests intégration OAuth2 | ≥1 test sans mock par flow | ⏳ En cours |
| CI001 | CI Workflow | GitHub Actions 3 niveaux | ✅ Complété |
| ARCH009 | Module Split BC | Organisation modules/{bc} | ✅ Complété |

---

## 🔄 MemPalace — Vérification de cohérence

### Commandes exécutées (session DEVLOG)
```bash
# Récupérer l'identité du projet dans MemPalace
mempalace wing ci3

# Vérifier la structure du graphe
mempalace get_taxonomy

# Auditer les tags déclarés vs pending vs ignorés
mempalace status

# Chercher décisions/contexte
mempalace search "PalaceWork decisions roadmap current status"
```

### Tags `.memtags.yml` — Ground Truth
```yaml
project: ci3-tasks

stack_declared:
  - ci3, ci4, codeigniter, php, postgresql, pgvector
  - twig, jquery, docker, github-actions, mempalace
  - oauth2, bshaffer, phpunit, composer, makefile

stack_pending: []
stack_ignored:
  - react, typescript, tanstack
```

**Statut**: ✅ **Cohérent** — zéro référence React/TypeScript. Stack correctement déclarée.

---

## 📊 État de la base MemPalace

**Taille actuelle** (après DEVLOG + commits récents):
```
/home/luc/.mempalace/palace/chroma.sqlite3
```

**Wings minés**:
- `805fb57b-7179-4430-bd19-3132f3361166` (15 avril)
- `fe80db44-7ca6-41d9-8c7b-05aabe02d254` (15 avril)

**Sessions Claude capturées**: 15 JSONL (17:58 → 23:17 le 22 avril)

---

## 🎬 Prochaines étapes

### Immédiat (Phase 1 — Fondation)
1. **SEC001 + SEC002** — Refresh token & logout (complète Auth)
2. **AUTH-INT001** — Tests intégration OAuth2 (≥1 par flow, vraie DB)
3. **OPS001** — Healthcheck endpoint

### Court terme (Phase 2 — MVP Perso)
1. **Project** — CRUD perso (`project.user_id`, sans org)
2. **Task enrichie** — Colonnes, due_date, state machine (`todo → in_progress → in_review → done`)
3. **Tags + Comments** — Structure M2M

### Moyen terme (Phase 3 — PalaceWork)
1. **PALACE-001** — MemPalace HTTP adapter
2. **PALACE-002** — UUID task ↔ palace linking
3. **SESSION-CAPTURE-001** — Sessions Claude → mine auto

---

## 📝 Conventions de dev

### Commits
```
feat(task): add task creation endpoint
fix(auth): handle token expiration edge case
refactor(repository): extract db query logic
```
- Conventionnels (`feat:`, `fix:`, `refactor:`, `chore:`, `test:`, `docs:`)
- ⛔ **Jamais `Co-Authored-By`**
- ⛔ **Jamais `.gitignore` committé**

### Branches
- PRs vers `develop`
- `main` = production stable
- Feature: `feature/ci3/xxx`

### Tests
```bash
make test           # Tout
make test-unit      # Unitaires (0 DB)
make test-int       # Intégration (DB requise)
```

**Règle AUTH-INT001**: Service mocké en smoke/e2e → ≥1 test intégration sur vrai service.

---

## 🔗 Ressources

- **Plan détaillé** → [`plan_authentification.md`](../plan_authentification.md)
- **Roadmap** → [`roadmaps.md`](../roadmaps.md)
- **Config** → [`CLAUDE.md`](../CLAUDE.md), [`AGENTS.md`](../AGENTS.md)
- **Stack vérification** → [`.memtags.yml`](../.memtags.yml)
- **MemPalace** → [`palace/`](../palace/)
- **Tests** → [`tests/`](../tests/)

---

**Generated**: 2026-04-22 23:17  
**Sessions minées**: 15 (depuis init, avec MemPalace)
