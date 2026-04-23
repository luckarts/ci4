# Agent Skills — PalaceWork CI4 API

CodeIgniter 4 · REST API · PHP 8.1+ · PostgreSQL + pgvector

## Philosophie d'apprentissage

**Approche en 3 étapes :**

1. **D'abord fonctionnel** — Faire marcher le code, même "sale".
2. **Puis comprendre** — Identifier les code smells, lire les skills de patterns.
3. **Puis améliorer** — Refactorer en appliquant le bon pattern, dans un commit dédié.

**Règles :**
- Ne pas chercher à tout optimiser d'entrée. YAGNI.
- Chaque pattern appliqué = un commit avec message explicatif.
- Règle 80/20 : CRUD natif pour 80%, patterns pour les 20% à haute valeur.

---

## Skills disponibles

### Architecture & Décision

| Skill | Fichier | Usage |
|-------|---------|-------|
| `plan` | `.claw/skills/plan/SKILL.md` | Plan avant implémentation — étapes numérotées, risques |
| `debug` | `.claw/skills/debug/SKILL.md` | Root-cause analysis — lire avant supposer |
| `git` | `.claw/skills/git/SKILL.md` | Commits conventionnels, workflow PR, checklist |
| `ci3-architecture` | `.claw/skills/ci3-architecture/SKILL.md` | Architecture complète CI3 — Repository, DI, DTOs, design patterns, refactoring |

### Workflow APEX

| Skill | Fichier | Usage |
|-------|---------|-------|
| `apex:architect` | `.claw/skills/apex/core/architect.md` | Phase 1 — Design avec 5 artifacts (CoT, ToT, CoD, YAGNI, Patterns) |
| `apex:builder` | `.claw/skills/apex/core/builder.md` | Phase 2 — Implémentation suivant les artifacts ARCHITECT |
| `apex:validator` | `.claw/skills/apex/core/validator.md` | Phase 3 — Quality gates (lint, tests unit, E2E) |
| `apex:reviewer` | `.claw/skills/apex/core/reviewer.md` | Phase 4 — Review contre les décisions architecturales |
| `apex:documenter` | `.claw/skills/apex/core/documenter.md` | Phase 5 — Commit docs + reflection |
| `apex:feature` | `.claw/skills/apex/orchestrators/feature.md` | Orchestrateur complet — toutes les phases |
| `apex:subtask` | `.claw/skills/apex/orchestrators/subtask.md` | Sous-tâche issue d'un plan validé |
| `apex:decompose` | `.claw/skills/apex/planning/decompose.md` | Décomposer une feature en commits atomiques |
| `apex:research` | `.claw/skills/apex/planning/research.md` | Cartographier l'existant avant de planifier |

### Commandes Git (.claude/commands/)

| Commande | Fichier | Usage |
|----------|---------|-------|
| `/git:cm` | `.claude/commands/git/cm.md` | Stage + commit conventionnel (sans push) |
| `/git:cp` | `.claude/commands/git/cp.md` | Stage + commit + push |
| `/git:pr` | `.claude/commands/git/pr.md` | Créer une PR GitHub |

### Idéation & Brainstorming (global)

| Skill | Usage |
|-------|-------|
| `brainstorming` | Exploration créative avant une décision de conception ou d'architecture |
| `multi-agent-brainstorming` | Revue par plusieurs perspectives simulées — utile pour des choix structurants |

---

## Mapping problème → skill

| Problème | Skill recommandé |
|----------|-----------------|
| "Je veux implémenter une nouvelle feature" | `apex:architect` → `apex:feature` |
| "Je ne sais pas par où commencer" | `apex:research` → `apex:decompose` |
| "J'ai un bug et je ne comprends pas pourquoi" | `debug` |
| "Combien de commits pour cette feature ?" | `git` → `apex:decompose` |
| "Je veux refactorer avec un pattern" | `ci4-architecture` → `apex:architect` |
| "Quel pattern appliquer à ce problème ?" | `ci4-architecture` (mapping problème → pattern) |
| "Comment valider que mon implémentation est correcte ?" | `apex:validator` |
| "Je veux faire une PR" | `/git:pr` |
| "Je veux committer sans pousser" | `/git:cm` |
| "Je dois choisir entre plusieurs approches" | `brainstorming` → `apex:architect` |
| "Je veux challenger une décision avec plusieurs points de vue" | `multi-agent-brainstorming` |

---

## Ajouter un nouveau skill

1. Créer `.claw/skills/{nom}/SKILL.md` avec frontmatter `name` + `description`
2. Ajouter une entrée dans ce fichier (tableau Skills disponibles + mapping)
3. Commit : `docs(agents): add {nom} skill`
