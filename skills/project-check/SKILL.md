---
name: project-check
description: >
  Audit de compréhension et gap analysis du projet. Utilise ce skill quand tu veux vérifier que Claude comprend bien l'app avant de planifier, détecter des tasks oubliées dans la roadmap, ou identifier des problèmes de séquençage et de bootstrap. Déclenche sur : "est-ce que tu as bien compris le projet", "vérifie si j'ai oublié des tasks", "check la roadmap", "gap analysis", "audit du projet", "tu comprends bien l'app ?", "est-ce que l'ordre est logique", "j'ai oublié quelque chose", "problème dans la roadmap", "c'est juste une couche en plus".
---

# Project Check — Audit de compréhension + Gap Analysis

Ce skill se déroule en deux phases séquentielles et ne peut pas être raccourci.

---

## Phase 1 — Compréhension du projet

### Étape 1 : Charger le contexte

Avant de parler, lis silencieusement dans cet ordre :
1. `memory/MEMORY.md` (index mémoire projet)
2. `backend/docs/roadmap.md`
3. `CLAUDE.md`

Si ces fichiers sont absents, lis ce qui est disponible (architecture.md, README, etc.) et note ce qui manque.

Ne résume pas ces lectures à voix haute — tu les utilises comme base.

### Étape 2 : Présenter ta compréhension

Structure ta présentation en 4 blocs courts. Chaque bloc = 3 à 6 bullets max. Sois précis et factuel, pas générique.

```
## Ce que j'ai compris de l'app

**Produit**
- [Nom, positionnement, pour qui]
- [Valeur principale / differentiateur]

**Architecture technique**
- [Stack, pattern DDD/hexagonal, BCs existants]
- [Bounded Contexts : état actuel de chacun]
- [Prochaine(s) étape(s) identifiées]

**Ce que je ne suis pas sûr de comprendre**
- [Zones floues, hypothèses, questions ouvertes]
```

Ne mentionne pas les branches git, les commits récents, ni ce qui a déjà été développé — présente le projet comme s'il partait de zéro, uniquement à partir de la roadmap et du contexte produit/architecture.

Termine avec :

> "Est-ce que cette lecture est correcte ? Corrige ce qui est faux ou incomplet avant qu'on passe à la gap analysis."

**Ne passe pas à la Phase 2 avant confirmation explicite.**

---

## Phase 2 — Gap Analysis

### Étape 1 : Charger la roadmap en détail

Lis (ou relis) `backend/docs/roadmap.md` en entier. Identifie :
- Les features planifiées et leur statut (done / en cours / todo)
- Les BCs mentionnés et leur couverture
- Les tâches transversales (tests, CI, migrations, sécurité, docs)

### Étape 2 : Identifier les gaps

Compare la roadmap avec ce que tu sais du projet. Cherche :

**A. Tasks techniques implicites non listées**
- Migrations de base de données nécessaires
- Tests manquants (unit / integration / e2e / workflow)
- PHPStan / qualité / CI à brancher sur les nouveaux BCs
- Seeds / fixtures pour les nouveaux BCs

**B. Features "oubliées" dans un BC**
- Exemple : BC créé mais sans soft delete, sans pagination, sans events de domaine, sans voter
- Comparer chaque BC existant contre le pattern standard du projet

**C. Dépendances inter-BCs non planifiées**
- Si une feature B dépend de A, est-ce que A est complet ?
- Workflow cross-entités documenté ?

**D. Tâches de fin de feature**
- WorkflowTest.php créé ?
- Mapping config ajouté dans `MappingConfigLoader` ?
- `config/services.yaml` importé ?
- Entrée ajoutée dans `api_platform.yaml` et `doctrine.yaml` ?

**E. Sujets absents de la roadmap**
- Sécurité / OWASP checks
- Monitoring / observabilité
- Documentation API
- Onboarding (README, CONTRIBUTING)
- Rate limiting / quotas si SaaS

**F. Problèmes de séquençage et bootstrap**

C'est la catégorie la plus difficile à détecter — elle demande de raisonner sur les dépendances implicites, pas seulement les dépendances déclarées.

Pour chaque phase, pose-toi ces questions :

1. **"Juste une couche"** — Si une phase est décrite comme "juste X en plus", est-ce que le core existe vraiment avant elle ? Exemple : "le SaaS c'est juste une couche multi-tenant" → le core produit doit être livrable en mono-tenant avant la phase SaaS.

2. **Bootstrap de données** — Est-ce qu'une feature a besoin de données existantes pour être utile au lancement ? Si oui, quand ces données commencent-elles à être produites ? Exemple : un knowledge base qui nécessite des sessions capturées → la capture doit tourner *pendant* le développement, pas après.

3. **Prérequis déplacés trop loin** — Est-ce qu'une infrastructure (async, auth, cache) est positionnée après une phase qui en a besoin ? Cherche les features qui disent "requiert X" et vérifie que X est bien avant elles dans la roadmap.

4. **Valeur incrémentale** — À chaque fin de phase, est-ce que l'app est réellement déployable et utile sans les phases suivantes ? Si non, les phases sont peut-être mal découpées.

5. **Dépendances circulaires** — Feature A attend Feature B, qui elle-même dépend de quelque chose qui vient après A.

### Étape 3 : Présenter les gaps

Structure ta réponse ainsi :

```
## Gap Analysis — Tasks potentiellement manquantes

### 🔴 Critique (bloquant ou risqué si oublié)
- [ ] [task] — [pourquoi c'est critique]

### 🟡 Important (à planifier prochainement)
- [ ] [task] — [contexte]

### 🟢 Nice-to-have (non urgent)
- [ ] [task] — [contexte]

### ❓ Questions ouvertes
- [Ce que tu n'as pas pu déterminer et qui nécessite une décision]
```

Ensuite demande :

> "Est-ce que certains de ces items sont déjà en cours ou volontairement exclus du scope ? Je peux affiner."

---

## Règles du skill

- **Une phase à la fois** : ne fais pas la gap analysis avant validation de la compréhension
- **Pas d'implémentation** : ce skill ne produit que des constats et des listes — jamais de code
- **Factuel** : cite les fichiers et les BCs réels, pas des généralités
- **Complet mais concis** : si tu es incertain, dis-le explicitement dans "zones floues"
