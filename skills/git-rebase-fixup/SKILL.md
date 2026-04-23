---
name: git-rebase-fixup
description: "Réécrire l'historique git — fixup commits + rebase autosquash, ou découpage d'un gros commit en commits atomiques"
argument-hint: [analyze|fixup|rebase|split]
triggers:
  - intégrer dans le commit
  - modifier un commit précédent
  - squash dans l'historique
  - réécrire l'historique
  - fixup commit
  - rebase autosquash
  - amend historique
  - modifier commit existant
  - intégrer lors de la création
  - découper un commit
  - trop de fichiers dans un commit
  - splitter un commit
  - un endpoint par commit
---

# Réécrire l'historique git

Ce skill couvre deux cas :
- **Cas A** — Intégrer des modifications dans des commits antérieurs (fixup + autosquash)
- **Cas B** — Découper un gros commit en commits atomiques (reset + recréation)

---

## CAS B — Découper un gros commit

### Quand l'utiliser

Un commit contient trop de fichiers (> 5) et doit être découpé en commits logiques.
Deux stratégies de découpage :

| Stratégie | Quand | Résultat |
|---|---|---|
| **Par couche** (domain / application / infra / tests) | Code sans endpoints clairs | Commits réguliers en taille |
| **Par endpoint** (un endpoint = un commit) | Feature API avec CRUD | Historique lisible fonctionnellement |

### Stratégie endpoint par commit (API Platform)

Structure recommandée :
```
C1  feat: domain foundation — entité, enum, exceptions, repository contract   [~5 fichiers]
C2  feat: infra foundation — doctrine repo, migration, config                  [~5 fichiers]
C3  feat: endpoint POST /resource — add                                        [service + processor + Resource DTO + mapping + voter]
C4  feat: endpoint GET  /resource — list                                       [provider + transformer]
C5  feat: endpoint PATCH /resource/{id} — update                               [service + processor]
C6  feat: endpoint DELETE /resource/{id} — remove                              [service + processor]
C7  feat: side effects sur d'autres endpoints (filtrage, suppression event...) [~4 fichiers]
C8  test: E2E                                                                  [2 fichiers]
```

> Le Resource DTO et le Voter vont dans le **premier endpoint** (POST), car ils sont partagés mais doivent exister avant les autres.

### Procédure

**Si le commit à découper est HEAD :**

```bash
git reset HEAD^
# Tous les fichiers retombent dans le working tree (unstaged / untracked)
```

**Si le commit est plus ancien :**

```bash
# N = position depuis HEAD (HEAD~1, HEAD~2...)
git reset HEAD~N
# Attention : les commits APRÈS lui restent — risque de conflits si les fichiers se chevauchent
```

Ensuite, créer les commits un par un :

```bash
# Un git add par fichier (chemins explicites)
git add src/...

# Commiter avec hooks désactivés (états intermédiaires ne passent pas forcément phpstan)
git -c core.hooksPath=/dev/null commit -m "feat(...): ..."
```

Répéter pour chaque groupe logique.

### Vérification après découpage

```bash
git log --oneline main..HEAD    # N commits attendus
git diff --stat                 # working tree vide
vendor/bin/phpstan analyse --memory-limit=-1   # état final propre
```

---

## CAS A — Intégrer des modifications dans des commits antérieurs

## Problème

Tu as des modifications dans le working tree que tu veux incorporer directement dans les commits qui ont créé ces fichiers, plutôt que d'ajouter un nouveau commit par-dessus.

---

## Méthode rapide — fixup + autosquash (cas simple)

Quand tu n'as qu'un seul fichier oublié à ajouter dans un commit précis :

```bash
# 1. Stager le fichier oublié
git add <fichier>

# 2. Créer un commit fixup ciblant le commit à amender
git commit --fixup=<sha-du-commit-cible>

# 3. Rebase autosquash — fusionne le fixup dans le bon commit automatiquement
GIT_SEQUENCE_EDITOR=true git -c core.hooksPath=/dev/null rebase -i --autosquash <sha-du-commit-cible>~1
```

Git place automatiquement le fixup après le commit cible et le squashe sans ouvrir l'éditeur (`GIT_SEQUENCE_EDITOR=true`).

---

## Méthode manuelle — rebase edit (cas alternatif)

Quand tu veux modifier un commit précédent sans passer par le fixup :

```bash
# 1. Lancer le rebase interactif juste avant le commit à modifier
git rebase -i <sha-du-commit-cible>~1

# Dans l'éditeur : remplacer "pick" par "edit" (ou "e") sur la ligne du commit cible.
# Sauvegarder et quitter — Git s'arrête sur ce commit.

# 2. Ajouter le fichier
git add <fichier>

# 3. Amender le commit
git commit --amend --no-edit

# 4. Terminer le rebase
git rebase --continue
```

---

---

## Règle n°1 — Trouver le bon commit cible

**TOUJOURS cibler le commit qui a modifié le fichier en DERNIER**, pas celui qui l'a créé.

```bash
# Trouver quel commit a touché chaque fichier en dernier
git log --oneline -- path/to/file.php | head -1
```

> ⚠️ **Piège fréquent** : un fichier introduit dans le commit A peut avoir été réécrit dans le commit B.
> Si tu cibles A mais que ta modification est basée sur la version B, le patch **ne s'appliquera pas** — les lignes de contexte ne correspondent pas → conflit silencieux, commits droppés.

### Exemple concret

```
Commit A  → crée OrganizationCollectionProvider (version simple)
Commit B  → réécrit OrganizationCollectionProvider (ajoute SecurityUser, filtrage)
Working tree → modifie OrganizationCollectionProvider (User → SecurityUser)
```

La modification du working tree est basée sur la version **B** → `--fixup=B`, pas `--fixup=A`.

---

## Étape 1 — Cartographier les fichiers → commits

```bash
# Pour chaque fichier modifié, trouver son dernier commit
for f in $(git diff --name-only); do
  echo "$f → $(git log --oneline -- $f | head -1)"
done
```

Résultat attendu : une table de correspondance `fichier → commit cible`.

---

## Étape 2 — Créer les fixup commits (par groupe de commit cible)

```bash
# Stager uniquement les fichiers d'un groupe
git add path/to/file1.php path/to/file2.php

# Créer le fixup commit (message auto "fixup! <message original>")
git commit --fixup=<SHA_commit_cible>
```

> ⚠️ Ne jamais utiliser `git add -A` ou `git add .` si la racine git est au-dessus du répertoire de travail (ex : `.git` à la racine, tu travailles dans `backend/`). Utiliser des **chemins explicites**.

Répéter pour chaque groupe.

---

## Étape 3 — Cas particulier : suppression de classe

Si tu veux supprimer une classe qui est encore **importée dans des commits ultérieurs**, il faut mettre la suppression dans le **dernier commit qui l'utilise** :

```bash
# Supprimer la classe
git rm src/Domain/Event/SomeEvent.php

# Stager aussi le fichier qui supprime l'import
git add src/Application/Service/SomeService.php

# Fixer le dernier commit qui les utilise tous les deux
git commit --fixup=<SHA_dernier_commit_qui_les_utilise>
```

> ⚠️ Si tu supprimes la classe dans le commit A mais que le commit B importe encore `SomeEvent`, phpstan échouera à l'état intermédiaire du rebase.

---

## Étape 4 — Rebase autosquash

```bash
# Calculer N = nombre de commits depuis la base de la branche
git log --oneline main..HEAD | wc -l

# Rebase avec autosquash — hooks désactivés pour éviter les échecs
# sur les états intermédiaires (pas le résultat final)
GIT_SEQUENCE_EDITOR=true git -c core.hooksPath=/dev/null rebase -i --autosquash HEAD~N
```

**Pourquoi désactiver les hooks ?**
- `GrumPHP` / `phpstan` s'exécute à chaque `git commit --amend` pendant le rebase
- L'état intermédiaire d'un commit (avant son fixup) peut ne pas passer phpstan
- Le résultat final (après squash) est correct — seul cet état compte
- Les hooks repasseront lors du prochain vrai commit

---

## Vérification après rebase

```bash
# 1. Nombre de commits correct (pas de commits droppés)
git log --oneline main..HEAD | wc -l

# 2. Working tree propre
git diff --stat

# 3. Aucune référence orpheline
grep -rn "ClassSupprimée" src/ tests/

# 4. Phpstan sur l'état final
vendor/bin/phpstan analyse --memory-limit=-1
```

---

## Checklist rapide

```
[ ] git log --oneline -- <file> | head -1  → commit cible identifié
[ ] Fichiers réécrit dans un commit ultérieur → cibler le commit ULTÉRIEUR
[ ] Suppression de classe → même fixup que la suppression de son import
[ ] git add avec chemins explicites (pas -A ni .)
[ ] git commit --fixup=SHA  (un fixup par commit cible)
[ ] git -c core.hooksPath=/dev/null rebase -i --autosquash HEAD~N
[ ] git log --oneline main..HEAD → N commits attendus présents
[ ] git diff --stat → vide
```

---

## Causes d'échec classiques

| Symptôme | Cause | Solution |
|---|---|---|
| Commits droppés après rebase | Conflit de patch (mauvais commit cible) | Identifier le dernier commit qui a touché le fichier |
| Rebase paused / bloqué | phpstan échoue sur état intermédiaire | `git -c core.hooksPath=/dev/null rebase ...` |
| Commit bloqué par GrumPHP/phpstan | État intermédiaire invalide lors d'un découpage | `git -c core.hooksPath=/dev/null commit ...` |
| Fichiers inattendus committés | `git add -A` avec `.git` à la racine | Utiliser des chemins explicites |
| `fatal: le chemin X ne correspond à aucun fichier` | Préfixe de chemin incorrect (CWD ≠ git root) | `git rev-parse --show-toplevel` pour vérifier |
| Rebase en cours résiduel | Rebase précédent non terminé | `git rebase --abort` avant de recommencer |
| Fichier supprimé non stageable via `git add` | Le fichier n'existe plus sur disque | `git status` — il est peut-être déjà stagé automatiquement |
