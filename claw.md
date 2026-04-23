# Structure des agents et skills dans Claw

## Arborescence

```
votre-projet/
├── .claw/
│   ├── agents/         # Fichiers .toml → listés via /agents
│   │   ├── architect.toml
│   │   ├── debugger.toml
│   │   └── reviewer.toml
│   ├── skills/         # Sous-dossiers avec SKILL.md → invocables via /skill-name
│   │   ├── plan/SKILL.md
│   │   ├── debug/SKILL.md
│   │   ├── git/SKILL.md
│   │   └── apex/
│   │       ├── core/       architect.md, builder.md, validator.md, reviewer.md, documenter.md
│   │       ├── orchestrators/  feature.md, subtask.md
│   │       └── planning/   decompose.md, research.md
│   └── CLAW.md         # Chargé dans le system prompt automatiquement
├── .claude/
│   ├── commands/git/   # cm.md, cp.md, pr.md
│   ├── memory/         # MEMORY.md + fichiers mémoire
│   ├── plans/          # Plans d'implémentation
│   ├── mempalace_identity.txt   # Identité du projet (chargée en contexte)
│   └── settings.local.json
├── AGENTS.md           # Référence skills + mapping problème→skill
├── CLAUDE.md           # Instructions Claude Code
└── claw.md             # Ce fichier — documentation de la structure
```

## Agents (.toml)

Métadonnée pure (nom, description, modèle). Apparaissent dans `/agents`.

```toml
name = "architect"
description = "Architecture planning"
model = "claude-sonnet-4-6"
```

## Skills (SKILL.md)

Un skill = un sous-dossier + SKILL.md. Définit le comportement réel.

```yaml
---
name: plan
description: Architecture planning with step-by-step breakdown
---

[instructions du skill]
```

Invocation : `claw /plan "ajouter un système de cache"`

## Workflow APEX

5 phases séquentielles pour les features complexes :

```
/apex:architect  →  /apex:builder  →  /apex:validator  →  /apex:reviewer  →  /apex:documenter
```

Ou en une commande : `/apex:feature`

## CLAW.md

Règles transversales chargées automatiquement dans le system prompt.
Ne pas y mettre les instructions spécifiques à un skill — elles vont dans `SKILL.md`.
