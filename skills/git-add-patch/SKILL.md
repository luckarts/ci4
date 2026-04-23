---
name: git-add-patch
description: "Staging partiel — ajouter seulement certaines lignes d'un fichier via git add -p (hunks interactifs, split, edit)"
argument-hint: [patch|split|edit|staged]
triggers:
  - git add -p
  - staging partiel
  - ajouter seulement certaines lignes
  - accepter seulement certains changements
  - stager une partie d'un fichier
  - stager seulement une ligne
  - hunk
  - patch interactif
  - git add --patch
  - staging sélectif
  - ne stager qu'une partie
  - accepter seulement le hunk
---

# Staging partiel — git add -p

`git add -p` (ou `--patch`) permet de stager **seulement certaines lignes** d'un fichier, même si plusieurs modifications coexistent.

---

## Commande de base

```bash
# Toutes les modifications du fichier, hunk par hunk
git add -p frontend/components/ui/index.ts

# Plusieurs fichiers ciblés
git add -p frontend/components/ui/index.ts frontend/assets/styles/main.css
```

---

## Commandes interactives (répondre à chaque hunk `?`)

| Touche | Action |
|--------|--------|
| `y`    | **Yes** — stager ce hunk |
| `n`    | **No** — ignorer ce hunk |
| `s`    | **Split** — découper le hunk en sous-hunks plus petits |
| `e`    | **Edit** — éditer manuellement les lignes à stager |
| `q`    | **Quit** — arrêter (les hunks déjà acceptés restent stagés) |
| `?`    | Afficher l'aide |

> **Astuce** : taper `?` puis Entrée affiche toutes les options disponibles.

---

## Cas 1 — Un seul changement dans le hunk → `y` ou `n`

```
@@ -1,5 +1,6 @@
 export { default as AppNavItem } from './AppNavItem.vue'
+export { default as UserMenu } from './UserMenu.vue'
 export { default as Icon } from './Icon.vue'

Stage this hunk [y,n,q,a,d,e,?]? y
```

---

## Cas 2 — Plusieurs changements dans un hunk → `s` pour splitter

Si Git regroupe plusieurs lignes dans un seul hunk, tenter `s` pour le découper :

```
Stage this hunk [y,n,q,a,d,e,?]? s
Split into 2 hunks.
```

Si le split n'est pas possible (lignes trop proches), utiliser `e`.

---

## Cas 3 — Édition manuelle du hunk → `e`

Quand `s` ne peut pas découper davantage :

```
Stage this hunk [y,n,q,a,d,e,?]? e
```

L'éditeur s'ouvre avec le diff brut. Pour **exclure une ligne ajoutée** :
- Supprimer la ligne `+xxx` → la modification n'est pas stagée
- Ne **jamais** supprimer les lignes de contexte (sans `+` ni `-`)
- Ne **jamais** modifier les lignes `-` (supprimées) → les laisser telles quelles

### Exemple — Ne stager que `UserMenu` parmi plusieurs exports ajoutés

Diff brut dans l'éditeur :
```diff
 export { default as AppNavItem } from './AppNavItem.vue'
+export { default as UserMenu } from './UserMenu.vue'
+export { default as ThemeToggle } from './ThemeToggle.vue'
 export { default as Icon } from './Icon.vue'
```

Pour ne stager **que** `UserMenu` : supprimer la ligne `+export { default as ThemeToggle }`.

Résultat après sauvegarde :
```diff
 export { default as AppNavItem } from './AppNavItem.vue'
+export { default as UserMenu } from './UserMenu.vue'
 export { default as Icon } from './Icon.vue'
```

---

## Vérifier ce qui est stagé vs non-stagé

```bash
# Ce qui sera dans le prochain commit
git diff --staged

# Ce qui reste dans le working tree (pas encore stagé)
git diff

# Vue combinée — tout d'un coup
git status
```

---

## Compléter le staging après -p

Après avoir stagé les hunks voulus, stager les autres fichiers normalement :

```bash
# Autres fichiers à ajouter en entier
git add frontend/components/ui/UserMenu.vue

# Puis committer
git commit -m "feat(ui): add UserMenu component"
```

---

## Checklist

```
[ ] git add -p <fichier>          → mode patch interactif
[ ] y/n pour les hunks simples
[ ] s si le hunk peut être splitté
[ ] e pour éditer manuellement (supprimer les lignes + à exclure)
[ ] git diff --staged             → vérifier que seul le bon code est stagé
[ ] git diff                      → vérifier que le reste n'est PAS stagé
[ ] git commit -m "..."           → committer
```

---

## Causes d'erreur classiques

| Symptôme | Cause | Solution |
|----------|-------|----------|
| `s` ne split pas | Lignes trop proches (< 3 lignes de contexte) | Utiliser `e` à la place |
| Éditeur s'ouvre vide | Variable `$EDITOR` non définie | `export EDITOR=nano` puis relancer |
| Hunk accepté par erreur | `y` tapé sur le mauvais hunk | `git restore --staged <fichier>` et recommencer |
| Toutes les lignes stagées | Suppression d'une ligne `-` dans `e` | Ne jamais toucher aux lignes `-` dans l'éditeur |
