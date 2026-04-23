---
name: test:pyramid
description: "Pyramide de tests PHPUnit — décider quel niveau pour chaque cas (unit/integration/E2E). Éviter l'over-engineering E2E."
argument-hint: [voter|validation|constraint|ci]
triggers:
  - pyramide de tests
  - test pyramid
  - over-engineering tests
  - trop de tests e2e
  - où mettre ce test
  - quel niveau de test
  - test 422 unit ou e2e
  - test 403 unit ou e2e
  - voter unit test
  - tester voter
  - tester constraint
  - constraint unit test
  - smoke test ci
  - ordre tests ci
  - suite trop lente
  - tests lents
  - optimiser pipeline ci
  - réduire temps ci
---

# Pyramide de Tests — Où mettre chaque cas

## Règle d'or

Pousser au maximum vers le bas. Plus un test est bas dans la pyramide, plus il est rapide et stable.

```
E2E (Docker + HTTP + BDD) : ~500ms/test   → Happy path + smoke uniquement
Integration (BDD sans HTTP) : ~50ms/test  → Contraintes DB, cascades
Unit (aucune infra) : ~5ms/test           → Tout le reste
```

---

## État actuel du projet (référence)

```
E2E: 32 fichiers  ██████████████████████ 95%  ← trop chargé
Integration: 0                                 ← vide
Unit: 2 fichiers  █               5%
```

Chaque `Create*Test.php` fait en moyenne 7 tests dont 5 cas d'erreur en E2E.
Ces 5 cas d'erreur coûtent 5×500ms chacun au lieu de 5×5ms.

---

## Arbre de décision

```
Mon test vérifie...
│
├── Une règle de format / validation (email invalide, contenu trop court)
│   └── → UNIT : tester la classe Constraint ou le Value Object directement
│
├── Un refus d'accès (403, "seul le membre peut faire X")
│   └── → UNIT : tester le Voter avec mocks
│
├── Une contrainte base de données (email unique, cascade delete)
│   └── → INTEGRATION : vrai PostgreSQL, pas de HTTP
│
├── Un 401 "non authentifié"
│   └── → E2E (1 seul DataProvider centralisé dans AuthenticationTest)
│
└── Un parcours utilisateur réaliste (create → read → update → delete)
    └── → E2E : happy path + smoke
```

---

## 1. Tests unitaires — Voters (remplacent les 403 E2E)

Au lieu de faire un E2E avec 2 users pour tester le 403, tester le Voter directement.

```php
// tests/Unit/Project/Security/ProjectVoterTest.php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Security;

use App\Project\Infrastructure\Security\ProjectVoter;
use App\Project\Domain\Entity\Project;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

#[Group('unit')]
#[Group('voter')]
#[Group('project')]
final class ProjectVoterTest extends TestCase
{
    private ProjectVoter $voter;

    protected function setUp(): void
    {
        // Injecter les dépendances mockées du Voter
        $memberRepository = $this->createMock(ProjectMemberRepositoryInterface::class);
        $this->voter = new ProjectVoter($memberRepository);
    }

    #[Test]
    public function owner_can_edit_project(): void
    {
        $project = $this->buildProject(ownerId: 'user-1');
        $token   = $this->tokenForUser('user-1');

        $result = $this->voter->vote($token, $project, ['CAN_EDIT_PROJECT']);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    #[Test]
    public function non_member_cannot_edit_project(): void
    {
        $project = $this->buildProject(ownerId: 'user-1');
        $token   = $this->tokenForUser('user-99'); // étranger

        $result = $this->voter->vote($token, $project, ['CAN_EDIT_PROJECT']);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    #[Test]
    #[DataProvider('roleProvider')]
    public function member_role_determines_access(string $role, string $attribute, int $expected): void
    {
        // Tester la matrice complète en DataProvider
        $project = $this->buildProject(ownerId: 'user-owner');
        $this->mockMemberWithRole('user-1', $project->getId(), $role);
        $token = $this->tokenForUser('user-1');

        $result = $this->voter->vote($token, $project, [$attribute]);

        $this->assertSame($expected, $result);
    }

    public static function roleProvider(): array
    {
        return [
            'admin can delete'    => ['ROLE_ADMIN',  'CAN_DELETE_PROJECT', VoterInterface::ACCESS_GRANTED],
            'member cannot delete'=> ['ROLE_MEMBER', 'CAN_DELETE_PROJECT', VoterInterface::ACCESS_DENIED],
            'member can comment'  => ['ROLE_MEMBER', 'CAN_COMMENT',        VoterInterface::ACCESS_GRANTED],
        ];
    }

    // Helpers de construction
    private function buildProject(string $ownerId): Project { /* ... */ }
    private function tokenForUser(string $userId): TokenInterface { /* ... */ }
    private function mockMemberWithRole(string $userId, string $projectId, string $role): void { /* ... */ }
}
```

**Résultat** : toute la matrice de permissions en ~20ms. Zéro Docker, zéro HTTP.

---

## 2. Tests unitaires — Constraints/Validation (remplacent les 422 E2E)

Tester chaque règle de validation sur la classe directement, pas via HTTP.

```php
// tests/Unit/Task/Domain/ValueObject/CommentContentTest.php

#[Group('unit')]
#[Group('validation')]
final class CommentContentTest extends TestCase
{
    // Tester les règles de format/longueur avec DataProvider
    #[Test]
    #[DataProvider('invalidContentProvider')]
    public function rejects_invalid_content(string $content, string $expectedMessage): void
    {
        $this->expectException(InvalidCommentException::class);
        $this->expectExceptionMessageMatches($expectedMessage);

        CommentContent::fromString($content);
    }

    public static function invalidContentProvider(): array
    {
        return [
            'blank'    => ['',    '/blank|empty/i'],
            'too long' => [str_repeat('x', 5001), '/too long|length/i'],
            'spaces'   => ['   ', '/blank|empty/i'],
        ];
    }

    #[Test]
    public function accepts_valid_content(): void
    {
        $content = CommentContent::fromString('Valid comment.');
        $this->assertSame('Valid comment.', $content->getValue());
    }
}
```

**Alternative si pas de Value Object** — tester le Validator Symfony directement :

```php
use Symfony\Component\Validator\Validation;

final class CommentInputTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    #[Test]
    public function blank_content_fails_validation(): void
    {
        $input = new CommentInput();
        $input->content = '';

        $violations = $this->validator->validate($input);

        $this->assertCount(1, $violations);
        $this->assertSame('content', $violations[0]->getPropertyPath());
    }
}
```

---

## 3. Tests d'intégration — Contraintes DB (nouveau dossier à remplir)

```php
// tests/Integration/User/UniqueEmailTest.php

declare(strict_types=1);

namespace App\Tests\Integration\User;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
#[Group('user')]
final class UniqueEmailTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        // Pas de HTTP, pas de OAuth2 — accès direct Doctrine
    }

    #[Test]
    public function duplicate_email_throws_unique_constraint_violation(): void
    {
        $this->em->persist($this->buildUser('dup@example.com'));
        $this->em->flush();

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);

        $this->em->persist($this->buildUser('dup@example.com')); // même email
        $this->em->flush();
    }
}
```

---

## 4. Ce qui reste en E2E — Règle stricte

| Garder en E2E | Déplacer |
|---------------|---------|
| Happy path (201/200/204) | 422 validation → Unit |
| `#[Group('smoke')]` parcours critiques | 403 Voter → Unit |
| Isolation cross-org (404) | 401 unauthenticated → 1 DataProvider centralisé |
| WorkflowTest cross-BC | 404 simple not_found → Integration |

---

## 5. Ordre CI — Smoke en premier

Configurer la CI pour s'arrêter tôt si quelque chose est cassé :

```yaml
# .github/workflows/tests.yml
- name: Smoke (fail fast)
  run: php bin/phpunit --group=smoke

- name: Unit (ms)
  run: php bin/phpunit --testsuite=unit

- name: Integration (DB, no HTTP)
  run: php bin/phpunit --testsuite=integration

- name: E2E full (Docker)
  run: php bin/phpunit --testsuite=E2E
```

```bash
# Local : vérification rapide avant push
php bin/phpunit --group=smoke && php bin/phpunit --testsuite=unit
```

---

## Checklist avant d'écrire un test E2E

- [ ] Ce test vérifie-t-il une règle de format/validation ? → Unit
- [ ] Ce test vérifie-t-il un 403 (permissions) ? → Unit (Voter)
- [ ] Ce test vérifie-t-il une contrainte DB (unique, cascade) ? → Integration
- [ ] Ce test vérifie-t-il un 401 ? → DataProvider dans `AuthenticationTest`
- [ ] Ce test vérifie-t-il un vrai parcours utilisateur complet ? → E2E ✅

---

## Référence croisée

- `test:unit` → structure détaillée des tests unitaires
- `test:integration` → setup `KernelTestCase` + Doctrine
- `test:e2e` → parcours E2E avec `ApiTestHelper`
- `authorization` → structure des Voters (ce qu'on teste ici en unit)
- `api-validation` → contraintes de validation (ce qu'on teste en unit)
