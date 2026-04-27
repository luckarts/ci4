# PalaceWork — CI4 API

Task manager personnel et collaboratif où chaque tâche porte son palace de connaissance.
Backend **CodeIgniter 4** — API REST uniquement. Frontend consommé par une application Nuxt tierce.

 Hook PreCompact ajouté dans settings.local.json — pipe-testé et validé JSON                                   
  - make devlog ajouté au Makefile          
---

## Concept

Deux stores indépendants liés par `task.uuid` :

- **PostgreSQL** — le *quoi* : titre, statut, assignee, colonnes, dates
- **MemPalace** — le *pourquoi* : décisions prises, contexte des sessions Claude, alternatives rejetées

Quand un dev travaille sur une tâche avec Claude, la conversation est automatiquement indexée dans MemPalace. L'onglet Notes affiche ce contenu — journal de bord sans saisie manuelle.

---

## Structure

```
ci4-api/
├── app/
│   ├── Controllers/          # API endpoints
│   ├── Models/               # Eloquent-like (CI4)
│   ├── Filters/
│   ├── Config/
│   └── Libraries/
├── public/                   # index.php (API entry)
├── palace/                   # MemPalace lib
├── docker/                   # Docker Compose
├── tests/
│   ├── unit/                 # PHPUnit — 0 DB
│   └── integration/          # PHPUnit — vraie DB requise
└── Makefile
```

---

## Stack

| Couche | Technologie |
|--------|------------|
| Backend | CodeIgniter 4 · PHP 8.1+ |
| Base de données | PostgreSQL + pgvector |
| Auth | JWT / OAuth2 |
| Queue | Table DB + workers PHP CLI |
| API | REST JSON |
| Tests | PHPUnit 9+ |
| CI | GitHub Actions (lint / test) |
| Frontend | Nuxt (consommateur externe) |

---

## Prérequis

- PHP 8.0+
- PostgreSQL 14+
- Composer
- Docker + Docker Compose (optionnel)

---

## Installation

```bash
git clone <repo>
cd PalaceWork

# Backend
cp backend/application/config/database.php.example backend/application/config/database.php
# Renseigner les credentials PostgreSQL dans database.php
composer install

# Tests
vendor/bin/phpunit --configuration tests/phpunit.xml
```

---

## Commandes utiles

```bash
make test          # lance les tests PHPUnit
make test-unit     # tests unitaires uniquement (pas de DB)
make test-int      # tests intégration (DB requise)
make seed          # charge les seeds de développement
make worker        # démarre le queue worker CI3
```

---

## Roadmap

| Phase | Contenu |
|-------|---------|
| Fondation | OAuth2, rate limiting, CI, module split |
| MVP Perso | Tasks, State Machine, tags, comments |
| PalaceWork | MemPalace adapter, Queue Worker, knowledge layer |
| Pivot SaaS | Multi-tenant, invitations, PostgreSQL RLS |
| Collaboration | Activity Feed, SSE notifications |
| Visualisation | Graph D3.js / Cytoscape |
| Migration CI4 | Apprentissage incrémental CI3 → CI4 |

Détail complet → [`roadmaps.md`](roadmaps.md)

---

## Conventions

- **Commits** : conventionnels (`feat:`, `fix:`, `refactor:`...). Jamais de `Co-Authored-By`.
- **Branches** : PRs vers `develop`. `main` = production stable.
- **Tests** : tout service mocké en smoke/e2e → ≥1 test intégration sur le vrai service.
- **Plan avant implémentation** : plan complet écrit et validé avant tout code.
