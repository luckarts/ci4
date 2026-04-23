---
name: github-actions-security
description: "Bonnes pratiques de sécurité GitHub Actions — permissions, SHA pinning, secrets, environments, branch protection"
triggers:
  - github actions security
  - workflow security
  - ci security
  - permissions github actions
  - sha pinning
  - secrets github
  - branch protection
  - environment protection
  - script injection
  - supply chain ci
  - bonnes pratiques ci
  - audit workflow
argument-hint: [permissions|sha-pinning|secrets|environments|branch-protection|injection]
---

# GitHub Actions — Bonnes Pratiques Sécurité

Référence pour ce projet. Appliquée lors du commit `ci(security): harden workflows`.

---

## 1. Principe de moindre privilège — `permissions:`

### Règle

Toujours déclarer `permissions: {}` au niveau workflow, puis surcharger au niveau job.

```yaml
# workflow level — tout à zéro par défaut
permissions: {}

jobs:
  mon-job:
    permissions:
      contents: read          # checkout uniquement
      issues: write           # seulement si le job crée des issues
      pull-requests: write    # seulement si le job écrit sur les PR
```

### Matrice rapide par cas d'usage

| Usage | Permissions minimales |
|---|---|
| Checkout + tests | `contents: read` |
| Créer une issue | `contents: read` + `issues: write` |
| Créer une PR | `contents: write` + `pull-requests: write` |
| Lire + commenter PR | `contents: read` + `pull-requests: write` |
| Deploy avec OIDC cloud | `contents: read` + `id-token: write` |
| Agrégateur (résultat only) | `{}` |

### Vérifier le paramètre global du repo

```bash
gh api repos/{owner}/{repo}/actions/permissions/workflow
# "default_workflow_permissions": "read"  ← correct
# "can_approve_pull_request_reviews": false  ← correct
```

---

## 2. SHA pinning — actions tierces

### Pourquoi

Un tag `@v4` est mutable — il peut être redirigé vers un commit malveillant (supply chain attack). Un SHA est immuable.

### Format

```yaml
# Mauvais
uses: actions/checkout@v4

# Bon
uses: actions/checkout@34e114876b0b11c390a56381ad16ebd13914f8d5  # v4
```

### SHAs actuels (ce projet)

| Action | SHA | Tag |
|---|---|---|
| `actions/checkout` | `34e114876b0b11c390a56381ad16ebd13914f8d5` | v4 |
| `actions/cache` | `0057852bfaa89a56745cba8c7296529d2fc39830` | v4 |
| `actions/upload-artifact` | `ea165f8d65b6e75b540449e92b4886f43607fa02` | v4 |
| `actions/github-script` | `f28e40c7f34bde8b3046d885e986cb6290c5673b` | v7 |
| `shivammathur/setup-php` | `44454db4f0199b8b9685a5d763dc37cbf79108e1` | v2 |
| `peter-evans/create-pull-request` | `4e1beaa7521e8b457b572c090b25bd3db56bf1c5` | v5 |

### Récupérer un SHA à jour

```bash
# Pour un tag annoté, il faut déréférencer
sha=$(gh api repos/{owner}/{action}/git/refs/tags/{tag} --jq '.object.sha')
type=$(gh api repos/{owner}/{action}/git/refs/tags/{tag} --jq '.object.type')
# Si type == "tag" (annoté), déréférencer :
sha=$(gh api repos/{owner}/{action}/git/tags/$sha --jq '.object.sha')
echo $sha
```

---

## 3. Injection de scripts — user inputs

### Risque

Interpoler `${{ github.event.inputs.X }}` directement dans un `run:` permet d'injecter du shell arbitraire.

```yaml
# DANGEREUX
run: php bin/phpunit --group ${{ github.event.inputs.group }}

# SAFE — passer par une variable d'environnement
env:
  GROUP: ${{ github.event.inputs.group }}
run: php bin/phpunit --group "$GROUP"
```

### Règle générale

Toute valeur externe (`inputs.*`, `github.event.pull_request.title`, `matrix.*` venant d'une source externe) doit transiter par `env:` avant d'être utilisée dans `run:`.

Sources sûres directement interpolables : `github.sha`, `github.ref`, valeurs constantes de la matrice issues du dépôt.

---

## 4. Secrets

### Règles

- Ne jamais afficher la valeur d'un secret dans les logs (`echo ${{ secrets.X }}` → masqué par GitHub mais éviter quand même)
- Ne jamais utiliser de fallback prévisible en production :
  ```yaml
  # Acceptable en test, risqué si le secret n'est pas configuré
  OAUTH_ENCRYPTION_KEY: ${{ secrets.OAUTH_ENCRYPTION_KEY }}
  # Faire échouer le workflow si le secret manque en production
  ```
- Préférer les secrets d'environnement (staging/production) plutôt que les secrets du repo pour un scope plus fin

### Vérifier les secrets configurés

```bash
gh secret list -R {owner}/{repo}
gh secret list -R {owner}/{repo} --env staging
gh secret list -R {owner}/{repo} --env production
```

---

## 5. Environments — approbation manuelle

### Configuration de ce projet

| Environment | Règle | Résultat |
|---|---|---|
| `staging` | `deployment_branch_policy: protected_branches` | Deploy uniquement depuis `main` |
| `production` | `wait_timer: 5` + `protected_branches` | Délai 5 min + branches protégées uniquement |

### Configurer via gh CLI

```bash
# Staging — branches protégées uniquement
gh api --method PUT repos/{owner}/{repo}/environments/staging \
  --input - <<'EOF'
{
  "deployment_branch_policy": {
    "protected_branches": true,
    "custom_branch_policies": false
  }
}
EOF

# Production — délai + reviewers manuels
gh api --method PUT repos/{owner}/{repo}/environments/production \
  --input - <<'EOF'
{
  "wait_timer": 5,
  "reviewers": [{"type": "User", "id": <user_id>}],
  "deployment_branch_policy": {
    "protected_branches": true,
    "custom_branch_policies": false
  }
}
EOF
```

---

## 6. Branch Protection — main

### Configuration de ce projet

```bash
gh api repos/{owner}/{repo}/branches/main/protection
```

| Règle | Valeur | Pourquoi |
|---|---|---|
| `enforce_admins` | `true` | L'admin ne contourne pas les règles |
| `dismiss_stale_reviews` | `true` | Un nouveau push invalide les approbations |
| `require_last_push_approval` | `true` | L'auteur du dernier commit ne peut pas auto-approuver |
| `required_conversation_resolution` | `true` | Tous les commentaires résolus avant merge |
| `allow_force_pushes` | `false` | Protège l'historique |
| `allow_deletions` | `false` | Protège la branche |

### Mettre à jour via gh CLI

```bash
gh api --method PUT repos/{owner}/{repo}/branches/main/protection \
  --input protection.json
```

---

## 7. Checklist audit rapide

```bash
# 1. Permissions par défaut du repo
gh api repos/{owner}/{repo}/actions/permissions/workflow

# 2. Branch protection main
gh api repos/{owner}/{repo}/branches/main/protection | jq '{
  enforce_admins: .enforce_admins.enabled,
  dismiss_stale: .required_pull_request_reviews.dismiss_stale_reviews,
  last_push_approval: .required_pull_request_reviews.require_last_push_approval,
  conversation_resolution: .required_conversation_resolution.enabled
}'

# 3. Environments configurés
gh api repos/{owner}/{repo}/environments | jq '.environments[] | {name, protection_rules}'

# 4. Secrets présents
gh secret list -R {owner}/{repo}

# 5. Actions utilisées dans les workflows — détecter les tags non pinnés
grep -r "uses:" .github/workflows/ | grep -v '#' | grep -E "@v[0-9]"
```

---

## 8. Risques restants non résolus

| Risque | Statut | Mitigation |
|---|---|---|
| `allowed_actions: "all"` | Ouvert (plan gratuit) | Restreindre aux publishers de confiance si passage Pro |
| `required_approving_review_count: 0` | Intentionnel (solo project) | Mettre à 1 si équipe |
| `can_admins_bypass: true` sur environments | Limitation GitHub Free | Upgrade pour désactiver |
| Commit signing | Non activé | Activer si besoin de non-répudiation |
