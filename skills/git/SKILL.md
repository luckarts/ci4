---
name: git
description: Workflow git complet — commits conventionnels, branches, hooks GrumPHP, PR
triggers:
  - git commit
  - commit message
  - conventional commit
  - message de commit
  - commit conventionnel
  - workflow git
  - git workflow
  - grumphp
  - hook git
  - pull request
  - créer un PR
  - git push
  - committer
argument-hint: [commit|branch|pr|hooks]
---

# Workflow Git — Symfony API OAuth2 Asana

## Commits conventionnels

### Format

```
<type>(<scope>): <description courte>

[corps optionnel]

[footer optionnel]
```

> **Pas de `Co-Authored-By`** dans les messages de commit (convention projet).

### Types

| Type | Usage |
|------|-------|
| `feat` | Nouvelle fonctionnalité |
| `fix` | Correction de bug |
| `test` | Ajout ou modification de tests uniquement |
| `refactor` | Refactoring sans changement de comportement |
| `chore` | Tâches techniques (config, docker, dépendances) |
| `ci` | Modifications CI/CD |
| `docs` | Documentation uniquement |
| `perf` | Amélioration de performance |

### Scopes du projet

| Scope | Bounded Context |
|-------|----------------|
| `user` | BC User |
| `organization` | BC Organization |
| `project` | BC Project |
| `team` | BC Team |
| `task` | BC Task |
| `auth` | BC Auth / OAuth2 |
| `security` | Voters, ACL |
| `ci` | GitHub Actions |
| `shared` | Kernel, SharedKernel |

### Exemples

```
feat(organization): ajoute le endpoint PATCH /organizations/{id}
fix(team): corrige la validation du rôle member en Provider
test(project): E2E smoke tests CRUD + sécurité
refactor(shared): extrait AbstractApiTestCase en trait
chore(ci): matrice E2E auto-découverte par répertoires
```

---

## Branches

Format : `<type>/<scope>/<description-kebab-case>`

Le segment `<scope>` différencie les branches **frontend** et **backend** :

| Scope | Usage |
|-------|-------|
| `frontend` | Travail dans `frontend/` (Vue, Nuxt, composants) |
| `backend` | Travail dans `backend/` (Symfony, API Platform, BC) |

```bash
git checkout -b feature/frontend/ui-lib-atoms
git checkout -b feature/backend/task-api
git checkout -b fix/frontend/avatar-overflow
git checkout -b fix/backend/organization-voter
git checkout -b test/backend/e2e-project-api
```

---

## Workflow complet

```bash
# 1. Créer la branche depuis main
git checkout main && git pull
git checkout -b feature/mon-sujet

# 2. Développer + tester localement
# (GrumPHP bloque le commit si PHPStan/lint échoue)

# 3. Stager les fichiers pertinents (jamais git add -A)
git add src/MonBC/...
git add tests/E2E/MonBC/...

# 4. Committer
git commit -m "feat(mon-bc): description courte"

# 5. Pousser et créer la PR
git push -u origin feature/mon-sujet
gh pr create --title "feat(mon-bc): ..." --base main
```

---

## Hooks GrumPHP

GrumPHP s'exécute automatiquement au `git commit` :

| Hook | Ce qu'il vérifie |
|------|-----------------|
| `phpstan` | Analyse statique level 6 |
| `blacklist` | Mots interdits (`var_dump`, `dd()`, `die(`) |

Si GrumPHP bloque :

```bash
# Voir l'erreur précise
vendor/bin/grumphp run

# Corriger, re-stager, recommitter (NOUVEAU commit, pas --amend)
git add src/...
git commit -m "fix(scope): corrige l'erreur PHPStan"
```

> **Ne jamais utiliser `--no-verify`** pour contourner les hooks.

---

## Checklist avant PR

- [ ] `vendor/bin/phpstan analyse` passe (level 6)
- [ ] `vendor/bin/php-cs-fixer fix --dry-run` propre
- [ ] Tests unitaires verts localement (`vendor/bin/phpunit --group unit`)
- [ ] Message de commit au format conventionnel
- [ ] Branche à jour avec `main` (`git rebase main`)
- [ ] Pas de fichiers de debug commités

---

## Commandes utiles

```bash
# Voir ce qui sera commité
git diff --staged

# Annuler le dernier commit (garde les modifications)
git reset --soft HEAD~1

# Rebaser sur main
git fetch origin && git rebase origin/main

# Créer une PR avec gh CLI
gh pr create --title "feat(scope): ..." --base main

# Voir le statut CI
gh pr checks
```
