# Plan : Pre-commit hooks PHP (syntax, PHPStan, namespace)

## Contexte

Repo git à `/home/luc/Documents/ci4`, backend CodeIgniter 4 dans `backend/`.
PHPStan absent des dépendances — à installer.

## Approche retenue

Script shell simple dans `.git/hooks/pre-commit` (actif immédiatement) + copie versionnée dans `scripts/hooks/pre-commit` pour partage d'équipe.

Évite la dépendance externe `pre-commit` (Python).

## Étapes

### 1. Ajouter PHPStan à composer.json (backend/)
```
"phpstan/phpstan": "^2.0"
```

### 2. Créer backend/phpstan.neon
- Level 5 (bon compromis CI4)
- Paths: app/
- Exclure vendor/, writable/

### 3. Créer le hook pre-commit

Logique du hook `.git/hooks/pre-commit` :
1. Récupère les fichiers `.php` stagés (`git diff --cached --name-only --diff-filter=ACMR`)
2. **Syntax PHP** : `php -l` sur chaque fichier
3. **Namespace check** : compare le namespace déclaré dans le fichier avec le chemin relatif attendu (PSR-4 : `App\` → `backend/app/`)
4. **PHPStan** : `./vendor/bin/phpstan analyse` sur les fichiers stagés (si dans backend/)

Le hook échoue (`exit 1`) si une erreur est trouvée, affiche le fichier fautif.

### 4. Versionner dans scripts/hooks/pre-commit

Même script, avec note d'installation :
```
cp scripts/hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## Fichiers à créer/modifier

| Fichier | Action |
|---|---|
| `backend/composer.json` | Ajouter phpstan en require-dev |
| `backend/phpstan.neon` | Créer la config |
| `.git/hooks/pre-commit` | Créer le hook actif |
| `scripts/hooks/pre-commit` | Copie versionnée |
