---
name: dead-code
description: "Eviter le code mort — règles YAGNI avant d'ajouter du code, checklist de détection, suppressions safe"
argument-hint: [detect|remove|prevent]
triggers:
  - code mort
  - dead code
  - yagni
  - inutilisé
  - jamais appelé
  - dispatché dans le vide
  - event sans listener
  - service sans consommateur
  - interface sans implémentation active
  - méthode jamais appelée
  - pattern copié
  - cohérence avec un autre BC
  - par précaution
  - au cas où
  - pour plus tard
---

# Eviter le code mort

---

## Règle fondamentale

> **Ne crée du code que s'il est utilisé maintenant.**

"Pour plus tard", "au cas où", "pour rester cohérent avec l'autre BC" → ce sont des justifications de code mort.

---

## Les 5 formes de code mort dans ce projet

### 1. Domain Event sans consommateur

```php
// ❌ Event dispatché, personne n'écoute
$this->eventDispatcher->dispatch(new ProjectCreatedEvent(...));
// → aucun Listener, Handler ou Subscriber dans tout le codebase
```

**Règle :** Créer un Domain Event uniquement si un Listener/Handler existe déjà ou est créé dans le même commit.

→ Voir skill `domain-events` pour la règle YAGNI complète.

---

### 2. Interface sans implémentation alternative

```php
// ❌ Interface créée "au cas où on voudrait changer l'implémentation"
interface SlugGeneratorInterface
{
    public function generate(string $name): string;
}
// → une seule implémentation, jamais swappée, jamais mockée dans les tests
```

**Règle :** Une interface se justifie par :
- Plusieurs implémentations existantes OU
- Un mock dans les tests OU
- Un boundary explicite entre couches (Domain → Infrastructure)

---

### 3. Méthode/propriété jamais appelée

```php
// ❌ Getter créé "par complétude" sur une entité
public function getArchivedAt(): ?\DateTimeImmutable
{
    return $this->archivedAt;
}
// → jamais utilisé dans Provider, Processor, ni tests
```

**Règle :** N'expose que ce qui est consommé. Un getter sans lecteur = bruit.

---

### 4. Pattern copié depuis un autre BC sans raison métier

```php
// ❌ OrganizationCreatedEvent existe → on crée ProjectCreatedEvent "pour être cohérent"
// → aucun side-effect, aucun handler, code structurellement identique mais fonctionnellement vide
```

**Règle :** La cohérence structurelle ne justifie pas du code mort. Chaque pattern doit avoir une raison métier propre.

---

### 5. TODO/FIXME sans ticket associé

```php
// ❌
// TODO: ajouter la validation du slug ici plus tard
```

**Règle :** Un TODO sans ticket Linear = intention jamais réalisée. Soit créer le ticket maintenant, soit supprimer le commentaire.

---

## Checklist avant d'ajouter du code

Avant tout nouveau fichier, classe, méthode ou event :

- [ ] **Qui appelle ce code aujourd'hui ?** (pas "demain", "bientôt", "peut-être")
- [ ] **Quel side-effect concret déclenche-t-il ?**
- [ ] **Est-ce que je le copie depuis un autre BC "par cohérence" ?**
- [ ] **Y a-t-il un test qui l'utilise ?** (si non, c'est souvent un signal)
- [ ] **Puis-je ajouter ce code dans un commit séparé quand le besoin est réel ?**

Si toutes les réponses pointent vers "pas maintenant" → ne pas créer le code.

---

## Détecter le code mort existant

### Domain Events sans listener

```bash
# Trouver tous les events dispatchés
grep -r "dispatch(new " backend/src --include="*.php" -l

# Trouver tous les listeners/handlers
find backend/src -name "*Listener*" -o -name "*Handler*" -o -name "*Subscriber*"

# Croiser manuellement : chaque event a-t-il un consommateur ?
```

### Méthodes jamais appelées

```bash
# PHPStan avec la règle "unused" (niveau 6+ détecte les private)
cd backend && vendor/bin/phpstan analyse src --level=6

# Recherche manuelle d'un symbole
grep -r "getArchivedAt\|setArchivedAt" backend/src backend/tests
```

### Interfaces avec une seule implémentation

```bash
grep -r "implements " backend/src --include="*.php" | grep "Interface" \
  | awk '{print $NF}' | sort | uniq -c | sort -rn
# Les interfaces avec count=1 sont candidates à la suppression
```

---

## Supprimer du code mort — procédure safe

1. **Vérifier qu'il n'est pas utilisé** : `grep -r "ClassName\|methodName" backend/src backend/tests`
2. **Supprimer dans un commit dédié** : `refactor: remove dead code — ProjectCreatedEvent (no consumer)`
3. **Ne pas laisser de trace** : pas de commentaire `// removed`, pas de `@deprecated` sans plan de migration

```bash
# Exemple de commit de nettoyage
git add -p  # sélectionner uniquement les suppressions
git commit -m "refactor: remove ProjectCreatedEvent — no listener, dispatched in void"
```

---

## Quand garder du code "sans consommateur immédiat"

Exceptions légitimes (rares) :

| Cas | Justification |
|-----|---------------|
| Hook documenté dans `AGENTS.md` ou ADR | Point d'extension explicitement annoncé |
| Interface de boundary Domain/Infrastructure | Nécessaire pour l'injection de dépendances et les mocks |
| Event dans une branche feature en cours | Le listener arrive dans le prochain commit de la même PR |

---

## Référence

- Skill `domain-events` — règle YAGNI pour les events spécifiquement
- Skill `ddd-cqrs-guide` — quand CQRS ajoute de la valeur vs quand c'est de l'over-engineering
- Principe YAGNI : _You Ain't Gonna Need It_ — n'implémenter que ce qui est nécessaire maintenant
