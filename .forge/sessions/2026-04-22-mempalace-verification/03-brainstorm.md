# Brainstorming — Vérification mémoire AI

## Analogies cross-domain

| Domaine | Mécanisme | Transposition |
|---------|-----------|---------------|
| **Link rot checker** | Crawl liens → flag 404 | Crawl drawers → flag facts obsolètes |
| **dbt freshness tests** | `updated_at` < threshold → warn | Drawer `mined_at` + git log → drift score |
| **Pact / contract testing** | Consommateur déclare ce qu'il attend du provider | L'agent déclare ce qu'il "croit" → vérifié contre la source |
| **Spellcheck** | Correction en temps réel | Fact-check en temps réel au moment du mine |
| **git blame** | Qui a changé quoi quand | Quelle session a introduit quel fait |
| **Vaccine booster** | Immunité qui décline → rappel | Mémoire qui se périme → rappel de vérification |

---

## Idées générées

### A — MemVerify CLI
`mempalace verify` ou outil standalone.
- Lit tous les drawers
- Pour chaque claim identifié (techno mentionnée, pattern décrit, décision prise)
- Cross-référence avec : `package.json`, `composer.json`, fichiers config, `git log`
- Sortie : rapport JSON/MD de confiance par drawer

### B — Confidence Score par Drawer
Chaque drawer reçoit un score de fraîcheur :
- `HIGH` : fact vérifié contre codebase actuel
- `MEDIUM` : fact non vérifiable (décision subjective)
- `LOW` : fact contredit par le codebase actuel
- `STALE` : drawer non mis à jour depuis X commits

Affiché dans le wake-up context : `[LOW] Frontend : React · TanStack`

### C — Contrat Mémoire (Memory Contract)
Fichier `.memcontracts.yml` à la racine du projet :
```yaml
contracts:
  - key: frontend_stack
    source: composer.json + package.json
    expected_pattern: "jquery"
    rooms: [code_main/general]
  - key: db_engine
    source: .env.example
    expected_pattern: "postgresql"
```
Le CI vérifie les contrats → fail si mémoire diverge.

### D — Session Verifier (pre-session hook)
Avant chaque session Claude Code :
- Hook `mempalace verify --quick`
- Affiche uniquement les **3 faits à haut risque** (score LOW ou STALE)
- Format compact : `⚠️ 3 faits à vérifier → mempalace verify --report`

### E — Mine avec annotation de confiance
Lors du `mempalace mine`, tagger chaque fait extrait avec :
- `type: techno|decision|pattern|constraint`
- `verifiable: true/false`
- `source_file: path/to/file` (si vérifiable)
Puis vérification incrémentale à chaque commit.

### F — Contradiction Detector inter-sessions
Deux sessions disent des choses opposées sur le même sujet.
Détecté par LLM : "Session A dit React, Session B dit jQuery → lequel est vrai ?"
Proposer résolution : garder le plus récent, ou créer un drawer "Décision finalisée".

### G — SaaS "Memory Audit"
Dashboard web :
- Connecte ton memory store (MemPalace, Mem0, Mulch)
- Connecte ton repo (GitHub/GitLab)
- Analyse hebdomadaire automatique
- Score de santé mémoire global
- Suggestions de corrections

---

## Idées retenues pour le multi-agent

**A** (CLI vérification), **B** (confidence score), **C** (contrat mémoire), **D** (pre-session hook)
→ Ces 4 peuvent être un seul produit cohérent.
