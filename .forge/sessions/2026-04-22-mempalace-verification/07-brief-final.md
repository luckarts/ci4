# Brief SaaS Final — MemVerify

**Session** : `2026-04-22-mempalace-verification`
**Date** : 2026-04-22

---

## L'idée

**MemVerify** — Le spellcheck pour la mémoire de ton AI.

Un CLI Python qui lit ce que ton agent "croit" (drawers MemPalace, fichiers Mulch, notes Mem0),
extrait les claims vérifiables (stack technique, patterns, configs),
et les confronte au codebase réel.
Résultat : un rapport de confiance avant chaque session — pas après le bug.

---

## Le segment

**Développeurs solo utilisant Claude Code / Cursor + un memory store (MemPalace, Mulch).**

Douleur précise : l'AI démarre une session avec des faits périmés ou incorrects, génère du code dans le mauvais langage/framework, et la correction est découverte après coup par hasard.

Pourquoi maintenant : l'adoption des memory stores AI explose en 2025-2026. Plus de mémoire = plus de drift potentiel. La douleur est proportionnelle à l'adoption.

---

## La différenciation

Tous les memory stores sont **passifs** — ils stockent et servent, sans jamais questionner la validité de ce qu'ils ont stocké.

MemVerify est **actif** : il compare ce que la mémoire dit avec ce que le code prouve.
C'est la seule couche qui fait le pont entre le cerveau de l'AI et la réalité du repo.

---

## Assembly Map (résumé)

| Composant | Couvert par | Effort |
|-----------|-------------|--------|
| Lecture drawers MemPalace | `mempalace` CLI existant | XS |
| Parsing sessions JSONL | `extract_session.py` existant | XS |
| Scanner `package.json` / `composer.json` | Python stdlib | S |
| Confidence scorer (règles) | À construire | M |
| Rapport Markdown | Pattern existant | XS |
| Pre-session hook Claude Code | Docs hooks | XS |
| Contrat mémoire `.memcontracts.yml` | À construire | S |

**Score d'assemblage** : ~65% couvert par briques existantes
**Gaps à construire** : fact extractor, confidence scorer, contrat YAML

---

## Chemin vers le premier run utile

```bash
# Jour 1-3 : MVP CLI
memverify ./palace

# Output :
# ✅ [HIGH]  db_engine     : "postgresql" — confirmé dans .env.example
# ✅ [HIGH]  auth_lib      : "bshaffer/oauth2" — confirmé dans composer.json
# ⚠️  [LOW]   frontend_tech : "React · TanStack" — jQuery trouvé, pas React
# ❓  [MEDIUM] state_machine : "PHP pure" — non vérifiable automatiquement
```

**Projet pilote idéal** : PalaceWork CI3 — palace déjà riche, bug React/jQuery déjà vécu.

---

## Prochaines étapes

→ Étendre `extract_session.py` avec un `--verify` mode sur le palace local
→ Prototyper le fact extractor regex (technos connues : frameworks PHP, JS, DB)
→ Tester sur le palace `palace_work` de ce projet
