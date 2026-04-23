# Discover — Gap analysis & Hypothèse SaaS

## Gap analysis

| Gap | Douleur | Adressé par |
|-----|---------|-------------|
| Aucun outil ne vérifie la fraîcheur des facts mémoire AI | L'AI code en React dans un projet jQuery | **Notre hypothèse** |
| Pas de confidence score sur les drawers MemPalace | On ne sait pas quelles infos faire confiance | **Notre hypothèse** |
| Pas de hook pre-session | Problème découvert pendant le code, pas avant | **Notre hypothèse** |
| Pas de contrat mémoire déclaratif | Drift silencieux, invisible jusqu'au bug | **Notre hypothèse** |

---

## Hypothèse SaaS

**MemVerify** — *"Le spellcheck pour la mémoire de ton AI"*

Un CLI Python open-source qui :
1. Lit les drawers d'un memory store (MemPalace, Mulch, fichiers `.md`)
2. Extrait les claims vérifiables (technos, configs, patterns architecturaux)
3. Les cross-référence contre le codebase réel (`package.json`, `composer.json`, `.env`, AST)
4. Produit un rapport de confiance : `HIGH / MEDIUM / LOW / STALE`
5. Intégrable comme pre-session hook Claude Code ou step CI/CD

---

## Scoring

| Critère | Score | Commentaire |
|---------|-------|-------------|
| Douleur réelle | 4/5 | Vécue en direct par l'utilisateur |
| Différenciation | 5/5 | Rien d'équivalent |
| Faisabilité MVP | 4/5 | Python + grep + git log suffisent pour la phase 1 |
| Taille marché | 2/5 | Niche aujourd'hui — croissant |
| Monétisable | 3/5 | Open-source + SaaS teams en phase 2 |

**Score total : 18/20** ✅ → on continue

---

## Ce qui rend ce produit non substituable

Aucun outil ne fait le pont entre **ce que l'AI croit** et **ce que le code dit**.
Les memory stores sont passifs. Les IDEs ne lisent pas la mémoire AI.
MemVerify est le chaînon manquant.
