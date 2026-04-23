---
name: value-objects
description: "Guide de decision Value Object vs primitive - Quand creer un VO, quand garder un string/int"
argument-hint: [analyse|decision|champ]
triggers:
  - value object
  - vo ou string
  - primitive obsession
  - quand creer un vo
  - embeddable
  - money
  - email vo
  - slug vo
  - typed id
---

# Value Objects — VO ou primitive ?

Regle unique : **un VO se justifie quand il porte de la logique ou du comportement, pas juste des donnees.**

Si tu retires le VO et que tu perds seulement un `new Email('...')`, c'est un wrapper inutile. Garde le primitive.

---

## La question a se poser

> "Est-ce que ce champ a des regles propres, ou est-ce juste du stockage ?"

---

## Quand un VO vaut le coup

Cree un VO quand **AU MOINS un** de ces cas s'applique :

### 1. Le type a ses propres regles metier

```php
// VO justifie : Money a des regles (devise, operations, arrondi)
final class Money
{
    public function __construct(
        private readonly int $amount,     // en centimes
        private readonly string $currency
    ) {
        if ($amount < 0) throw new \DomainException('Montant negatif interdit');
        if (!in_array($currency, ['EUR', 'USD'])) throw new \DomainException('Devise inconnue');
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) throw new \DomainException('Devises differentes');
        return new self($this->amount + $other->amount, $this->currency);
    }
}
```

### 2. Le meme type apparait dans plusieurs Bounded Contexts

```php
// Email utilise dans User, Invitation, Newsletter → une seule source de verite
final class Email
{
    public function __construct(private readonly string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException("Email invalide : {$value}");
        }
    }

    public function toString(): string { return $this->value; }
}
```

### 3. Pour typer fortement et eviter la Primitive Obsession

```php
// Sans VO — on peut inverser les arguments sans erreur PHP
function transfer(string $fromAccountId, string $toAccountId): void {}

// Avec VO — le type-checker bloque le melange
function transfer(AccountId $from, AccountId $to): void {}
```

---

## Quand garder le primitive (string/int/bool)

| Champ | Type recommande | Pourquoi |
|-------|-----------------|---------|
| `email` dans entite | `string` | Validation faite par `#[Assert\Email]` sur le DTO. Pas de logique domaine. |
| `hashedPassword` | `string` | Hash opaque — aucune logique a y mettre. |
| `firstName` / `lastName` | `string` | Simple stockage, pas de regle. |
| `id` (UUID Doctrine) | `string` / UUID natif | Le type Doctrine gere deja la conversion. VO utile seulement si confusion entre types d'IDs. |
| `slug` simple | `string` | VO utile seulement si le slug apparait dans plusieurs BCs avec les memes contraintes. |

**Regle :** la validation de format (email RFC, longueur, pattern) appartient au DTO / couche HTTP. L'entite ne la duplique pas.

---

## Responsabilites par couche

```
DTO (Resource)   → valide le format  (#[Assert\Email], #[Assert\NotBlank], longueur)
Service          → regles business   (unicite email, hashage password, slug disponible)
Entity           → invariants internes (ROLE_USER par defaut, createdAt auto, relations)
Value Object     → regles du type lui-meme (devise valide, montant positif, format slug)
```

---

## Mapping Doctrine pour les VOs

### Option A — Embeddable (champ simple)

```php
#[ORM\Embeddable]
final class Money
{
    #[ORM\Column(type: 'integer')]
    private int $amount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;
}

// Dans l'entite
#[ORM\Embedded(class: Money::class)]
private Money $price;
```

### Option B — Custom Type Doctrine (VO → colonne scalaire)

```php
// Type Doctrine : Email → varchar
class EmailType extends Type
{
    public function convertToPHPValue($value, AbstractPlatform $platform): Email
    {
        return new Email($value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        return (string) $value;
    }
}
```

---

## VOs pertinents pour ce projet (task manager)

| VO | Quand le creer | Benefice |
|----|----------------|---------|
| `Money` | Quand budget ou estimation en centimes arrive | Operations, arrondi, multi-devise |
| `Email` | Quand l'email est utilise dans User + Invitation + Notification | Une seule validation |
| `Slug` | Quand slug dans Organization + Project + Team avec memes regles | Regex centralisee |
| `HexColor` | Tags, colonnes kanban | Validation format `#RRGGBB` |
| `Url` | Logo, webhook | Validation RFC |
| `UserId` / `ProjectId` | Si confusion entre IDs de types differents devient risque reel | Type safety |

---

## Ce qu'on ne teste PAS avec les VOs

```php
// Inutile — tu testes Assert\Email de Symfony, pas ton code
public function testEmailMustBeValid(): void
{
    $dto = new RegisterUserRequest();
    $dto->email = 'invalid';
    $violations = $this->validator->validate($dto);
    $this->assertCount(1, $violations);
}
```

**Ce qu'on teste :** le comportement HTTP (422 si donnees invalides) en test d'integration, pas la config des assertions.

Si tu as un VO avec logique propre (`Money::add()`), **celui-la** se teste en test unitaire.

---

## Arbre de decision

```
Nouveau champ a modeliser
│
├─ Est-ce que ce champ a des regles metier propres ?
│  └─ NON → primitive (string / int / bool)
│
├─ Ce type apparait-il dans plusieurs BCs avec les memes contraintes ?
│  └─ OUI → VO (une seule source de verite)
│
├─ Y a-t-il du comportement (calculs, operations, comparaisons) ?
│  └─ OUI → VO
│
├─ Y a-t-il un risque de confusion entre deux IDs du meme type ?
│  └─ OUI → VO (typed ID)
│
└─ Sinon → primitive, avancer
```

---

## Reference

- `VO_or_NOT.md` — analyse detaillee par champ pour le projet
- `Sylius.md` — point 13 : primitive obsession dans Organization (Slug, Money, Url)
- `ddd.md` — guide DDD pur vs services classiques
