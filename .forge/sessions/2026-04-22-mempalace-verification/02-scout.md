# Scout — Opportunités marché

## Signaux de douleur

### Signal 1 — "Les agents oublient et se souviennent mal"
MemPalace, Mulch, Mem0 adressent "l'oubli". Personne n'adresse **le souvenir incorrect**.
> "Agents start every session from zero" → résolu par MemPalace
> "Agents démarrent avec des fausses informations" → **non résolu**

### Signal 2 — La douleur vécue (cas concret de l'utilisateur)
- Wake-up context : "Frontend : React · TanStack · TypeScript"
- Réalité : Twig + jQuery
- Conséquence : l'AI génère du React dans un projet jQuery
- Détection : **manuelle, par hasard, après coup**

### Signal 3 — Croissance de l'adoption des memory stores
Mulch, MemPalace, Mem0, LangChain Memory → adoption croissante
Plus les devs utilisent ces outils, plus le problème de dérive s'amplifie.
Aujourd'hui niche, demain standard.

---

## Segments cibles

| Segment | Douleur | Taille |
|---------|---------|--------|
| Développeurs solo avec AI assistant | Fausses infos dans wake-up context → mauvais code | Petit mais growing fast |
| Équipes utilisant des agents AI | Plusieurs agents avec mémoires divergentes | Moyen |
| Dev rels / consultants | Projets multiples, contextes qui se mélangent | Niche |

**Segment primaire** : développeur solo qui utilise Claude Code / Cursor + un memory store (MemPalace, Mem0, Mulch).

---

## Opportunité

Aucun outil ne fait :
1. **Cross-référencement** mémoire stockée ↔ réalité codebase
2. **Freshness scoring** par drawer/room
3. **Rapport de confiance** avant session : "3 faits potentiellement périmés"
4. **Correction assistée** : "Ce drawer dit X, le code dit Y — lequel garder ?"

→ Gap réel, non adressé, moment favorable (adoption memory stores en hausse)
