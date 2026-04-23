# CLAW.md — my-project

## Règles absolues

- Toujours lire un fichier avant de le modifier
- Une seule modification à la fois (pas de chaînage `&&`)
- Plan avant implémentation — attendre validation avant de coder
- Jamais de `Co-Authored-By` dans les commits
- Répondre sans résumé en fin de réponse

## Stack

Symfony 7 · API Platform 4 · PHP 8.3 · PostgreSQL

## Vérification avant commit

Adapter selon le stack :
```bash
vendor/bin/phpstan analyse
php bin/phpunit --testsuite unit
```

## Workflow APEX

| Phase | Skill | Rôle |
|-------|-------|------|
| 1 | `apex:architect` | Design + 5 artifacts |
| 2 | `apex:builder` | Implémentation |
| 3 | `apex:validator` | Quality gates |
| 4 | `apex:reviewer` | Review CoD |
| 5 | `apex:documenter` | Commit + reflection |

Orchestrateur complet : `apex:feature`
