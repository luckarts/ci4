---
name: git-reorder-commits
description: "Réordonner des commits sur une branche feature — via rebase interactif (méthode principale) ou cherry-pick"
argument-hint: [analyze|reorder]
triggers:
  - réordonner des commits
  - mauvais ordre de commits
  - commit dans le mauvais ordre
  - déplacer un commit
  - insérer un commit avant
  - commit aurait dû être avant
  - changer l'ordre des commits
  - historique dans le mauvais ordre
  - inverser deux commits
---

# Réordonner des commits

**Méthode principale** : `git rebase -i` — édition directe dans l'éditeur.
**Méthode alternative** : branche temporaire + cherry-pick (si conflits complexes).

---

## Quand l'utiliser

- Un commit a été créé trop tard (ex: `Dockerfile.e2e` après les tests E2E)
- L'ordre logique de l'historique est incorrect
- La branche n'est **pas encore poussée** (ou force-push accepté)

---

## Méthode principale — rebase interactif

### Étape 1 — Identifier les commits à réordonner

```bash
git log --oneline <base-commit>..HEAD
```

Exemple de sortie :
```
602f49d middleware
4590d90 feat(refacto): migrate SignupCard...
fcde46f feat: foundation
```

### Étape 2 — Lancer le rebase interactif

```bash
git rebase -i <base-commit>
# Exemple : git rebase -i fcde46f
```

Git ouvre l'éditeur avec la liste des commits (du plus ancien au plus récent) :

```
pick 4590d90 feat(refacto): migrate SignupCard...
pick 602f49d middleware
```

### Étape 3 — Réordonner les lignes

Couper-coller les lignes dans l'ordre voulu. Exemple — inverser les deux :

```
pick 602f49d middleware
pick 4590d90 feat(refacto): migrate SignupCard...
```

Sauvegarder et quitter. Git rejoue les commits dans le nouvel ordre.

### En cas de conflit

```bash
# Résoudre le conflit dans les fichiers concernés
git add <fichier-en-conflit>
git rebase --continue
# Ou annuler tout
git rebase --abort
```

---

## Méthode alternative — branche temp + cherry-pick

Utile quand les conflits sont complexes ou le rebase interactif peu pratique.

```bash
# 1. Créer une branche temp depuis la base
git branch temp-reorder <base-commit>
git checkout temp-reorder

# 2. Cherry-pick dans l'ordre voulu
git cherry-pick <SHA-C1>
git cherry-pick <SHA-C2>
# ...

# 3. Vérifier l'ordre
git log --oneline <base-commit>..HEAD

# 4. Réinitialiser la branche feature
git checkout <ta-branche-feature>
git reset --hard temp-reorder

# 5. Nettoyer
git branch -d temp-reorder
```

---

## Vérification finale

```bash
git log --oneline <base-commit>..HEAD   # ordre correct
git status --short                      # working tree propre
```

---

## Causes d'échec classiques

| Symptôme | Cause | Solution |
|---|---|---|
| Conflit pendant le rebase | Deux commits modifient le même fichier | Résoudre, `git add`, `git rebase --continue` |
| SHA inconnu | Mauvais hash copié | `git log --oneline` pour récupérer le bon |
| Branche déjà poussée | Historique public | `git push --force-with-lease` après confirmation |
| Commits perdus | Rebase annulé involontairement | `git reflog` pour retrouver les commits orphelins |

---

## Checklist rapide

```
[ ] git log --oneline <base>..HEAD  → SHAs identifiés + ordre cible défini
[ ] git rebase -i <base>
[ ] Dans l'éditeur : réordonner les lignes pick
[ ] Sauvegarder et quitter
[ ] Conflits éventuels résolus + git rebase --continue
[ ] git log → ordre vérifié
[ ] git status --short → propre
```
