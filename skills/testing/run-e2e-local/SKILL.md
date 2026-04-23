---
name: test:run-e2e-local
description: "Lancer les tests E2E en local via Docker (PostgreSQL + PHP). Setup complet, commandes Makefile, troubleshooting."
argument-hint: [setup|e2e|smoke|reset|clean]
triggers:
  - lancer tests local
  - run e2e local
  - smoke test local
  - docker test
  - make test
  - tests en local
  - postgresql local
  - tests e2e docker
---

# Lancer les tests E2E en local (Docker)

Les tests E2E nécessitent une base PostgreSQL. En local, on utilise Docker via `docker-compose.test.yml`.

## Stack Docker

- `php:8.3-cli` — extensions pdo_pgsql, intl, zip, mbstring, yaml
- `postgres:16-alpine` — port **5433** (évite le conflit avec un PG local sur 5432)
- Env file : `backend/.env.docker.test` (à créer depuis `.env.docker.test.dist`)

## Prérequis

```bash
# 1. Créer le fichier d'env Docker (une seule fois)
cp backend/.env.docker.test.dist backend/.env.docker.test

# 2. Aller dans le dossier backend
cd backend
```

## Commandes Makefile

Toutes les commandes se lancent depuis `backend/` :

```bash
# Setup complet + tests E2E (premier lancement ou reset total)
make test

# Étapes séparées
make test-build       # Build les containers Docker
make test-up          # Démarre PostgreSQL (attend healthcheck)
make test-install     # Installe les dépendances Composer
make test-keys        # Génère les clés OAuth2 (var/oauth/*.pem)
make test-db          # Crée le schéma Doctrine
make test-db-reset    # Reset le schéma (drop + create)
make test-e2e         # Lance les tests E2E
make test-all         # Lance tous les tests (unit + integration + E2E)

# Nettoyage
make test-down        # Stop et supprime les containers
make test-clean       # Stop, supprime containers + volumes
```

## Workflow typique

### Premier lancement

```bash
cd backend
cp .env.docker.test.dist .env.docker.test   # une seule fois
make test                                   # build + up + install + keys + db + e2e
```

### Lancement courant (après le premier)

```bash
cd backend
make test-up          # démarre PostgreSQL
make test-e2e         # lance les tests
```

### Tests ciblés

```bash
# Un groupe spécifique
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/phpunit --group smoke

# Un fichier spécifique
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/phpunit tests/E2E/Organization/OrganizationTest.php

# Un BC spécifique
docker compose --env-file .env.docker.test -f docker-compose.test.yml \
  run --rm php bin/phpunit --group organization
```

## Variables d'environnement

Le fichier `.env.docker.test` contient :

```dotenv
POSTGRES_DB=app
POSTGRES_USER=app
POSTGRES_PASSWORD=test
```

Ces variables sont injectées dans `docker-compose.test.yml` et dans le container PHP via `DATABASE_URL`.

## Troubleshooting

### "Connection refused" sur la base de données

```bash
# Vérifier que le container database est healthy
docker compose --env-file .env.docker.test -f docker-compose.test.yml ps
# → database doit être "healthy"

# Relancer proprement
make test-down && make test-up
```

### "Schema does not exist" / erreurs Doctrine

```bash
make test-db-reset    # drop + recreate
```

### Clés OAuth2 manquantes ("var/oauth/private.pem not found")

```bash
make test-keys
```

### Composer out of date

```bash
make test-install
```

### Port 5433 déjà utilisé

```bash
# Voir quel process utilise le port
lsof -i :5433

# Modifier docker-compose.test.yml si nécessaire
# ports: - "5434:5432"  ← changer 5433 en 5434
```

### Reset total (tout recommencer)

```bash
make test-clean       # down -v (supprime aussi les volumes)
make test             # repart de zéro
```

## Structure des fichiers Docker

```
backend/
├── docker-compose.test.yml     # PostgreSQL + PHP (APP_ENV=test)
├── .env.docker.test            # Variables PG (gitignored)
├── .env.docker.test.dist       # Template à copier
├── Makefile                    # Toutes les commandes
└── docker/
    └── php/
        └── Dockerfile          # php:8.3-cli avec extensions Symfony
```
