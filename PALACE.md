# 🏛️ PalaceWork CI4 API — Palace de Connaissance

Structure MemPalace organisée en **wings** (espaces), **rooms** (topics), **drawers** (notes) pour documenter les décisions architecturales, patterns, et conventions du projet.

---

## 📍 Navigation

### [Wing] Architecture API
**Fondation technique — CI4 structure, patterns, conventions de code**

- [Room] **CI4 Setup & Structure**
  - Drawer: Structure du projet
  - Drawer: Stratégie de routage
  
- [Room] **Design Patterns**
  - Drawer: Repository Pattern
  - Drawer: Service Layer

---

### [Wing] MemPalace Integration
**Stockage du pourquoi — intégration avec MemPalace, indexation, sync**

- [Room] **Architecture MemPalace**
  - Drawer: Lien task ↔ palace (clé logique `task.uuid`)
  - Drawer: Stratégie d'indexation (commits, sessions Claude, tags)
  
- [Room] **Features MemPalace**
  - Drawer: Snapshots immuables (PALACE-006)
  - Drawer: RBAC & Private Drawers (PALACE-003/004)

---

### [Wing] Auth & Security
**Authentification, autorisations, rate limiting**

- [Room] **OAuth2 & JWT**
  - Drawer: Flow OAuth2 (F001) — `POST /token`
  - Drawer: Refresh Token (SEC001) — rotation tokens
  - Drawer: Logout & Revocation (SEC002) — blacklist/jti
  
- [Room] **Rate Limiting**
  - Drawer: Rate Limiting OAuth2 (SEC003) — protection brute-force `POST /token`

---

### [Wing] Data Model
**Base de données, entities, migrations, évolution schéma**

- [Room] **Schéma DB**
  - Drawer: Table users (uuid, soft delete, email verification)
  - Drawer: Table tasks (state machine, dépendances)
  - Drawer: Stratégie Migrations — Expand/Contract (ARCH011)

---

### [Wing] Queue & Events
**Architecture asynchrone — event bus, queue workers, DLQ**

- [Room] **Event Bus CI4**
  - Drawer: System d'événements — CI4 Events native
  - Drawer: Events → MemPalace (EVENT-PALACE-001) — async indexation

---

### [Wing] Testing Strategy
**Approche TDD, tests unitaires, intégration, conventions**

- [Room] **Unit Tests**
  - Drawer: Philosophie — 0 DB, logique métier isolée
  
- [Room] **Integration Tests**
  - Drawer: Philosophie — vraie DB, workflows entiers
  - Signal d'alerte : tous les tests mockent le même endpoint → créer test intégration
  
- [Room] **Infrastructure Testing**
  - Drawer: Seeds (TEST-INFRA001) — base de données stable

---

## 🔗 Lien vers la roadmap

👉 [`roadmaps.md`](roadmaps.md) — ordre d'exécution des features, phases, événements domaine

---

## 📝 Comment utiliser ce palace

1. **Avant une session de dev** — lire le drawer correspondant à ta feature
   - Ex : implémentation Auth → lire [Wing] Auth & Security → [Room] OAuth2 & JWT
   
2. **En découvrant une décision architecturale** — l'ajouter en drawer
   - Format : titre clair, contenu texte/code/SQL
   
3. **En refactorant** — mettre à jour le drawer correspondant
   - Raison du refactoring, nouvelle structure, tradeoffs

4. **Indexation MemPalace** — automatique via webhook Git (PALACE-009) + sessions Claude (SESSION-CAPTURE-001)
   - Ce palace seed = base organique avant productisation

---

## 🎯 Principes

- **Karpathy mindset** — code simple, lisible, compréhensible
- **Zero nonsense** — chaque drawer a un WHY clair, pas de documentation morte
- **Learning mode** — chaque commit laisse une trace complète du parcours, pas juste le résultat

---

Source: `palace_structure.json` (format JSON structuré)
