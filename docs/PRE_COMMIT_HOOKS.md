# Pre-commit Hooks

Le projet utilise des hooks de pre-commit pour vérifier la qualité du code PHP avant chaque commit.

## Ce qu'il vérifie

1. **Syntaxe PHP** : Chaque fichier est vérifié avec `php -l`
2. **Namespaces** : Les namespaces doivent correspondre à la structure de répertoires (PSR-4)
3. **PHPStan** : Analyse statique du code avec PHPStan level 5

## Installation

Le hook est automatiquement actif dans `.git/hooks/pre-commit`.

Si vous avez besoin de le réinstaller :

```bash
cp scripts/hooks/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
```

## Structure de namespaces attendue

Pour les fichiers dans `backend/app/` :

- `backend/app/Controllers/Home.php` → `namespace App\Controllers;`
- `backend/app/Models/UserModel.php` → `namespace App\Models;`
- `backend/app/Database/Migrations/Migration.php` → `namespace App\Database\Migrations;`

## Contourner le hook

En cas de besoin (déconseillé) :

```bash
git commit --no-verify
```

## PHPStan Configuration

La configuration se trouve dans `backend/phpstan.neon`.

Pour lancer PHPStan manuellement :

```bash
cd backend
./vendor/bin/phpstan analyse app/
```
