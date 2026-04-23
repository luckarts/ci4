# Multi-agent — PM / Tech Lead / Devil's Advocate

---

## 🟦 PM — Valeur & Marché

**Ce que les utilisateurs veulent vraiment :**
Pas "vérifier la mémoire" — ils veulent **avoir confiance dans ce que l'AI sait d'eux**.
Le job-to-be-done réel : *"Avant de commencer à coder avec mon AI, je sais qu'elle a les bonnes infos."*

**Différenciation clé :**
Tous les outils existants sont passifs (stockent et servent). Celui-ci est **actif et prophylactique**.
Comme un spellcheck, mais pour les faits.

**Moment d'entrée :**
- Gratuit : CLI open-source, intégration MemPalace native
- Payant : Dashboard SaaS multi-projets + API pour teams

**Métrique clé :** "Faits invalidés avant qu'ils causent un bug" → valeur directe mesurable.

---

## 🟩 Tech Lead — Faisabilité

**Ce qui est faisable immédiatement (sans LLM) :**
- Grep-based fact extractor : cherche les technos mentionnées dans les drawers
- Cross-check avec `composer.json`, `package.json`, `*.env`, `*.yaml`
- Pattern matching : "React" dans drawer + pas de React dans package.json → flag

**Ce qui nécessite un LLM (phase 2) :**
- Détection sémantique : "on utilise un ORM" alors que le code fait du SQL pur
- Résolution de contradictions entre sessions
- Classification `type: decision|techno|pattern`

**Stack technique réaliste :**
```
Phase 1 (MVP) : Python CLI · regex/grep · git log · JSON output
Phase 2       : Claude API (claude-haiku-4-5) · embeddings · confidence scoring
Phase 3       : SaaS wrapper · GitHub integration · webhook CI
```

**Intégration naturelle :** MemPalace est déjà en Python → même écosystème.
Le script `extract_session.py` existant est la base parfaite.

---

## 🟥 Devil's Advocate — Risques

**Risque 1 : Marché trop niche**
MemPalace a quelques centaines d'utilisateurs. Un outil de vérification *pour* MemPalace = niche de niche.
→ Contre : construire provider-agnostic (Mem0, Mulch, fichiers .md) élargit la cible.

**Risque 2 : Les faux positifs tuent la confiance**
Un outil qui crie "STALE" sur des faits corrects → utilisateur ignore tout.
→ Contre : threshold conservateur + "je ne sais pas" plutôt que "c'est faux".

**Risque 3 : MemPalace l'intègre nativement**
Si MemPalace sort une commande `verify` officielle, le produit est obsolète.
→ Contre : opportunité de contribuer à MemPalace ou de le positionner comme layer orthogonal (multi-provider).

**Risque 4 : Maintenance du modèle de vérification**
Les patterns de détection doivent évoluer avec les langages et frameworks.
→ Contre : fichier de règles extensible + communauté.

---

## Consensus

**Hypothèse retenue :** CLI Python open-source `memverify` —
vérification de faits stockés en mémoire AI contra le codebase réel,
avec confidence scoring et pre-session hook.

**Non retenu :** SaaS en phase 1 (marché pas encore assez large, risque de sur-engineering).
