# Brain Map — Vérification mémoire AI

**Input** : "Comment vérifier que ce qui est stocké dans MemPalace est correct / à jour ?"
**Exemple concret** : wake-up context affiche "React · TanStack" alors que le projet tourne sur jQuery

---

## Ce qui existe aujourd'hui

### Outils de stockage mémoire AI
| Outil | Type | Vérification native |
|-------|------|---------------------|
| MemPalace | Local CLI / vector store | ❌ aucune |
| Mem0 | SaaS / SDK | ❌ aucune |
| LangChain Memory | Framework | ❌ aucune |
| Obsidian | PKM | ❌ manuelle |
| Notion AI | SaaS | ❌ manuelle |

### Outils de détection de dérive (domaines connexes)
| Outil | Domaine | Transposable ? |
|-------|---------|----------------|
| dbt freshness tests | Data pipeline | ✅ concept direct |
| Pact (contract testing) | API contracts | ✅ idée de "contrat mémoire" |
| Link rot checkers | Web archiving | ✅ analogie exacte |
| OpenLineage | Data lineage | ✅ tracer l'origine d'un fait |
| git blame / log | Codebase | ✅ déjà disponible |

### Ce qui existe dans PalaceWork roadmap
- **PALACE-008-b** : Staleness detection (drawer content vs mulch records → flag)
- **MULCH-001** : mulch records comme signal de péremption
→ Le problème est déjà reconnu dans la roadmap, mais pas encore résolu au niveau de la **vérification proactive**

---

## Le vrai problème

MemPalace mine des conversations passées. Ces conversations contiennent des faits **vrais au moment de la session** mais potentiellement **faux aujourd'hui** :
- Stack tech qui change
- Décisions réversées
- Modules renommés / supprimés
- Architecture pivotée

Le wake-up context est lu sans filtre de fraîcheur. L'AI le prend pour argent comptant.

---

## Zones saturées ?

**Non.** Aucun outil ne fait de la vérification proactive de mémoire AI contra la réalité du codebase.
Le gap est réel, le marché sous-adressé.
