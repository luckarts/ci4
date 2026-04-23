---
name: test:unit-vo-decisions
description: "Guide de decision : Quand ecrire un test unitaire vs E2E, et quand creer un Value Object vs garder un type primitif"
argument-hint: [unit-test|e2e|value-object|primitive|analyse]
triggers:
  - unit test ou e2e
  - value object ou string
  - vo ou non
  - quand tester unitaire
  - unit ou integration
  - dois-je creer un VO
  - primitive obsession
  - decision test
  - quand un vo vaut
  - CustomFieldValueValidator
  - workflow transitions
  - materialized path
  - wip limit
---

# Guide de decision — Tests Unitaires vs E2E & Value Objects vs Primitifs

Deux questions recurrentes en Symfony/DDD. Reponse rapide et regles operationnelles.

---

## PARTIE 1 — Quand ecrire un Test Unitaire vs E2E

### La regle en une phrase

> **Switch, algorithme recursif, calcul, if imbriques avec cas limites → Unit Test.**
> **CRUD avec config Symfony/API Platform → E2E suffit.**

---

### Arbre de decision rapide

```
Ce code contient...
│
├─ un switch / des if imbriques avec cas limites ?   → UNIT TEST
├─ un algorithme recursif ?                          → UNIT TEST
├─ du calcul arithmetique ou de l'agregation ?       → UNIT TEST
├─ de la logique metier sans infrastructure ?        → UNIT TEST
│
├─ juste des attributs PHP / YAML de config ?        → E2E suffit
├─ un simple CRUD API Platform ?                     → E2E suffit
└─ un appel HTTP observable de l'exterieur ?         → E2E suffit
```

---

### Ce qui DOIT etre en Unit Test (par priorite)

#### Phase 3 — Task ⭐⭐⭐⭐⭐

| Quoi | Pourquoi unitaire |
|------|-------------------|
| **Workflow transitions Task** | Tester chaque transition valide/invalide : DRAFT→TODO, REVIEW→IN_PROGRESS (reject), *→CANCELLED. Une E2E par chemin serait trop lente et couteuse. |
| **Detection de dependance circulaire** | Si A bloque B bloque C bloque A → rejeter. Algorithme de graphe pur, logique a plusieurs branches. |
| **Materialized Path** | Calcul du champ `path` (/1/5/12/) lors de creation, deplacement, suppression d'une sous-tache. Regles precises, faciles a rater. |
| **WIP Limit** | Avant de deplacer une tache dans une colonne, verifier `count(tasks) < wipLimit`. Cas limite : `wipLimit = 0 = illimite ?` |

#### Phase 7 — Custom Fields / EAV ⭐⭐⭐⭐

| Quoi | Pourquoi unitaire |
|------|-------------------|
| **CustomFieldValueValidator** | Validation dynamique selon `fieldType` : NUMBER accepte uniquement des chiffres, DATE un format ISO, SELECT uniquement les options definies, URL le format... 7 types × N cas = trop pour l'E2E. |

#### Phase 5 — Time Tracking

| Quoi | Pourquoi unitaire |
|------|-------------------|
| **Chiffrement SalaryInfo** | Le service chiffrement/dechiffrement `dailyRate` avec `sodium_crypto_secretbox`. Tester que `encrypt(decrypt(x)) = x`, que le champ chiffre en base n'est pas lisible. |
| **Agregation de temps** | Calcul du total par projet/user/periode avec filtre `isBillable`. Logique arithmetique + filtres = facile a rater. |

#### Phase 6 — Compliance / Audit

| Quoi | Pourquoi unitaire |
|------|-------------------|
| **AuditLog EventSubscriber** | Verifier que le JSON `changes: {field, old, new}` est construit correctement. L'E2E verifie que l'entree existe, pas que le contenu est correct. |
| **Anonymisation RGPD** | Verifier que les bons champs sont nullifies, que les autres restent intacts. |

#### Phase 1 — IAM

| Quoi | Pourquoi unitaire |
|------|-------------------|
| **OrganizationInvitation** | Logique d'expiration : `isExpired()`, transition PENDING→ACCEPTED/EXPIRED. Token de generation. |

#### Voters — tous en unitaire

Les Voters ont une logique de permission multi-criteres :

| Voter | Logique |
|-------|---------|
| `OrganizationVoter` | Role dans l'org (OWNER/ADMIN/MEMBER/GUEST) |
| `ProjectVoter` | Croise role org ET permissions projet (CAN_EDIT, CAN_ADMIN...) |
| `TaskVoter` | 3 niveaux : Org → Project → assignation + visibilite |
| `TimeEntryVoter` | Auteur ou admin seulement |
| `SalaryVoter` | Uniquement ROLE_HR_MANAGER |

---

### Ce qui suffit en E2E

```
CRUD standard (Project, Team, Comment...)
Invitation flow HTTP
Upload fichiers
Filtres API Platform
Reactions, Attachments
Notifications in-app
WebSocket / Mercure
Reordering colonnes
```

---

### Ce qu'on ne teste PAS en unitaire

```php
// INUTILE — tu testes le framework Symfony, pas ton code
public function testEmailMustBeValid(): void
{
    $dto = new RegisterUserRequest();
    $dto->email = 'invalid';
    $violations = $this->validator->validate($dto);
    $this->assertCount(1, $violations); // ← teste Assert\Email, pas toi
}
```

**Regle DTO :** pas de tests unitaires sur les DTO. La validation par attributs Symfony (#[Assert\Email]) se verifie via le comportement HTTP observable (test integration/E2E → 422).

---

## PARTIE 2 — Value Object ou type primitif ?

### La question a se poser

> **"Est-ce que ce champ a des regles propres, ou est-ce juste du stockage ?"**

---

### Arbre de decision rapide

```
Ce champ...
│
├─ a ses propres regles metier complexes ?            → VO
├─ apparait dans plusieurs bounded contexts ?         → VO (source unique de verite)
├─ risque d'etre confondu avec un autre type ?        → VO (evite Primitive Obsession)
│
├─ est deja valide ailleurs (DTO + Assert) ?          → string suffit
├─ n'a aucune logique dans le domaine ?               → string suffit
└─ ajouterait complexite Doctrine sans gain ?         → string suffit
```

---

### Quand un VO vaut vraiment le coup

#### 1. Le type a ses propres regles metier complexes

```php
// VO justifie : Money a des regles (devise, operations, arrondi)
final class Money
{
    public function __construct(
        private readonly int $amount,    // en centimes
        private readonly string $currency
    ) {
        if ($amount < 0) throw new \DomainException('Montant negatif interdit');
        if (!in_array($currency, ['EUR', 'USD'])) throw new \DomainException('Devise invalide');
    }

    public function add(Money $other): self { ... }  // ← comportement = VO justifie
}
```

#### 2. Le meme type apparait a plusieurs endroits (source de verite unique)

```php
// Email utilise dans User, Invitation, Newsletter → VO justifie
final class Email
{
    public function __construct(private readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException("Email invalide : {$value}");
        }
    }
}
```

#### 3. Pour eviter la Primitive Obsession sur des types critiques

```php
// Sans VO — tu peux passer un userId au mauvais endroit sans erreur
function transfert(string $fromAccountId, string $toAccountId): void {}

// Avec VO — le type PHP t'empeche de melanger
function transfert(AccountId $from, AccountId $to): void {}
```

---

### Decisions par champ — projet OAuth2

| Champ | VO ou string | Pourquoi |
|-------|-------------|----------|
| `email` | **string** | Validation sur le DTO (Assert\Email). L'entite verifie juste non-vide. Pas de logique domaine. |
| `hashedPassword` | **string** | Hash opaque. Aucune logique dessus dans le domaine. |
| `firstName / lastName` | **string** | Simple stockage. |
| `id (UUID)` | **VO potentiel** | Utile pour typage fort (eviter de passer un UserId ou on attend un ProductId). Avec le type Doctrine UUID, le gain est limite. |

---

### Quand creer des VO dans CE projet

```php
// Si tu dois calculer des tarifs            → Money VO
// Si tu geres des roles complexes           → Role VO
// Si l'email sert dans plusieurs contexts   → Email VO
// Si un UserId doit etre type fort          → UserId VO
```

---

### Ce qu'un VO n'est PAS

> **Si tu retires le VO et que tu perds juste un `new Email()`, ce n'est pas un VO — c'est un wrapper inutile.**

```php
// Wrapper inutile (pas un vrai VO) — pas de logique, juste du wrapping
final class FirstName
{
    public function __construct(public readonly string $value) {}
    // ← aucune regle, aucun comportement → string suffit
}
```

---

### Responsabilites claires dans le projet

| Couche | Responsabilite |
|--------|---------------|
| **DTO** | Valide le format (email, notBlank, longueur password) via attributs |
| **Service** | Regles business (unicite email, hashage password) |
| **Entity** | Invariants internes (ROLE_USER par defaut, createdAt) |
| **VO** | Regles propres au type : validation + comportement metier |

---

## Checklist de decision combinee

### Pour un nouveau code, se demander :

**Tests :**
- [ ] Y a-t-il un switch, des if imbriques, un algo recursif ou du calcul ? → Unit Test
- [ ] C'est du CRUD / de la config Symfony ? → E2E suffit
- [ ] C'est un Voter ? → toujours Unit Test
- [ ] C'est la validation d'un DTO ? → pas de unit test, tester via HTTP (422)

**Value Objects :**
- [ ] Ce champ a-t-il des regles metier propres (pas juste un setter) ?
- [ ] Ce type apparait-il dans plusieurs endroits du domaine ?
- [ ] Y a-t-il un risque de confusion avec un autre type primitif ?
- [ ] Si non a tout → garder le type primitif (string, int, etc.)
