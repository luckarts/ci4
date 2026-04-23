---
name: test:unit
description: "Tests unitaires PHPUnit pour Value Objects, Entites, Domain Events et Handlers CQRS"
argument-hint: [value-object|entity|handler|event]
triggers:
  - test unitaire
  - unit test
  - tester value object
  - tester handler
  - tester entite
  - phpunit unit
  - TestCase
---

# Tests Unitaires PHPUnit

Tests isoles sans infrastructure (pas de BDD, pas de HTTP). Testent la logique metier pure : Value Objects, Entites/Aggregats, Domain Events, Command Handlers.

## Conventions du projet

- Extends `PHPUnit\Framework\TestCase` (jamais WebTestCase)
- Attributs PHP 8 : `#[Group('prod')]`, `#[Group('unit')]`, `#[Group('value-object')]`
- DataProvider via `#[DataProvider('providerName')]`
- Namespace miroir : `App\User\Domain\ValueObject\Email` → `App\Tests\Unit\User\Domain\ValueObject\EmailTest`
- Suite PHPUnit : `--testsuite unit`

## Structure des fichiers

```
tests/
  Unit/
    User/
      Domain/
        ValueObject/
          EmailTest.php              # Value Object validation
          PasswordTest.php           # Hashing + regles complexite
          UserIdTest.php             # UUID generation
          UserNameTest.php           # Format validation
        Model/
          UserTest.php               # Aggregat (events, comportement)
        Event/
          UserRegisteredEventTest.php # Immutabilite, serialisation
    Auth/
      Application/
        Handler/
          RegisterUserHandlerTest.php # Handler CQRS avec mocks
```

## 1. Tester un Value Object

Pattern : named constructor, validation, egalite, DataProvider.

```php
declare(strict_types=1);

namespace App\Tests\Unit\User\Domain\ValueObject;

use App\User\Domain\Exception\InvalidUserException;
use App\User\Domain\ValueObject\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('prod')]
#[Group('unit')]
#[Group('value-object')]
final class EmailTest extends TestCase
{
    // Cas nominal
    public function testCanCreateEmailWithValidValue(): void
    {
        $email = Email::fromString('test@example.com');

        $this->assertInstanceOf(Email::class, $email);
    }

    // Cas d'erreur
    public function testThrowsExceptionForInvalidEmail(): void
    {
        $this->expectException(InvalidUserException::class);

        Email::fromString('not-an-email');
    }

    // Egalite (meme valeur = egaux)
    public function testTwoEmailsWithSameValueAreEqual(): void
    {
        $email1 = Email::fromString('test@example.com');
        $email2 = Email::fromString('test@example.com');

        $this->assertTrue($email1->equals($email2));
    }

    // Inegalite
    public function testTwoEmailsWithDifferentValuesAreNotEqual(): void
    {
        $email1 = Email::fromString('test1@example.com');
        $email2 = Email::fromString('test2@example.com');

        $this->assertFalse($email1->equals($email2));
    }

    // DataProvider : valeurs valides
    #[DataProvider('validEmailProvider')]
    public function testAcceptsValidEmails(string $validEmail): void
    {
        $email = Email::fromString($validEmail);

        $this->assertInstanceOf(Email::class, $email);
    }

    // DataProvider : valeurs invalides
    #[DataProvider('invalidEmailProvider')]
    public function testRejectsInvalidEmails(string $invalidEmail): void
    {
        $this->expectException(InvalidUserException::class);

        Email::fromString($invalidEmail);
    }

    /** @return array<int, array<int, string>> */
    public static function validEmailProvider(): array
    {
        return [
            ['test@example.com'],
            ['user.name@example.com'],
            ['user+tag@example.com'],
            ['user123@example-domain.com'],
        ];
    }

    /** @return array<int, array<int, string>> */
    public static function invalidEmailProvider(): array
    {
        return [
            ['not-an-email'],
            ['@example.com'],
            ['test@'],
            ['test.example.com'],
            [''],
            [' '],
        ];
    }
}
```

## 2. Tester un Value Object avec regles complexes (Password)

Pattern : regles de complexite, hashing, verification, reconstruction Doctrine.

```php
#[Group('prod')]
final class PasswordTest extends TestCase
{
    private const VALID_PASSWORD = 'StrongPassword123!';
    private Password $password;
    private PasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->hasher = new SymfonyPasswordHasher();
        $this->password = Password::fromPlain(self::VALID_PASSWORD, $this->hasher);
    }

    // Le password est hashe, pas stocke en clair
    public function testPasswordIsHashedAndNotStoredInPlainText(): void
    {
        $this->assertNotEquals(self::VALID_PASSWORD, $this->password->getHashedValue());
        $this->assertTrue($this->password->verify(self::VALID_PASSWORD, $this->hasher));
    }

    // Regles de complexite (une exception par regle)
    public function testThrowsExceptionForTooShortPassword(): void
    {
        $this->expectException(InvalidUserException::class);
        Password::fromPlain('123', $this->hasher);
    }

    public function testThrowsExceptionForPasswordWithoutUppercase(): void
    {
        $this->expectException(InvalidUserException::class);
        Password::fromPlain('lowercaseonly123!', $this->hasher);
    }

    // Reconstruction depuis Doctrine (fromHash)
    public function testCanVerifyPasswordAfterDoctrineReconstruction(): void
    {
        $original = Password::fromPlain('StrongPassword123!', $this->hasher);
        $reconstructed = Password::fromHash($original->getHashedValue());

        $this->assertTrue($reconstructed->verify('StrongPassword123!', $this->hasher));
        $this->assertFalse($reconstructed->verify('WrongPassword123!', $this->hasher));
    }
}
```

## 3. Tester un Aggregat (User)

Pattern : domain events, cycle de vie, comportement metier.

```php
#[Group('prod')]
final class UserTest extends TestCase
{
    private UserId $userId;
    private Email $email;
    private UserName $username;
    private Password $password;
    private PasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->hasher = new SymfonyPasswordHasher();
        $this->userId = UserId::generate();
        $this->email = Email::fromString('test@example.com');
        $this->username = UserName::fromString('john_doe');
        $this->password = Password::fromPlain('StrongPassword123!', $this->hasher);
    }

    // Verifier que la creation publie un domain event
    public function testUserPublishesRegisteredEventOnCreation(): void
    {
        $user = new User($this->userId, $this->email, $this->username, $this->password);

        $events = $user->getUncommittedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(UserRegisteredEvent::class, $events[0]);

        /** @var UserRegisteredEvent $event */
        $event = $events[0];
        $this->assertTrue($this->userId->equals($event->getUserId()));
        $this->assertTrue($this->email->equals($event->getEmail()));
    }

    // Verifier le commit des events
    public function testCanMarkEventsAsCommitted(): void
    {
        $user = new User($this->userId, $this->email, $this->username, $this->password);
        $this->assertCount(1, $user->getUncommittedEvents());

        $user->markEventsAsCommitted();

        $this->assertCount(0, $user->getUncommittedEvents());
    }

    // Verifier le comportement metier
    public function testCanVerifyPassword(): void
    {
        $user = new User($this->userId, $this->email, $this->username, $this->password);

        $this->assertTrue($user->verifyPassword('StrongPassword123!', $this->hasher));
        $this->assertFalse($user->verifyPassword('WrongPassword123!', $this->hasher));
    }
}
```

## 4. Tester un Domain Event

Pattern : immutabilite, serialisation, conversion event store.

```php
#[Group('prod')]
final class UserRegisteredEventTest extends TestCase
{
    // Chaque instance a un EventId unique (critique pour event store)
    public function testEventImmutability(): void
    {
        $event1 = new UserRegisteredEvent($this->userId, $this->username, $this->email);
        $event2 = new UserRegisteredEvent($this->userId, $this->username, $this->email);

        $this->assertFalse($event1->getEventId()->equals($event2->getEventId()));
        $this->assertTrue($event1->getUserId()->equals($event2->getUserId()));
    }

    // Round-trip serialisation (integrite des donnees)
    public function testEventSerializationForPersistence(): void
    {
        $event = new UserRegisteredEvent($this->userId, $this->username, $this->email);

        $unserialized = unserialize(serialize($event));

        $this->assertInstanceOf(UserRegisteredEvent::class, $unserialized);
        $this->assertTrue($this->userId->equals($unserialized->getUserId()));
        $this->assertTrue($event->getEventId()->equals($unserialized->getEventId()));
    }

    // Structure pour event store (toArray)
    public function testEventArrayConversionForEventStore(): void
    {
        $event = new UserRegisteredEvent($this->userId, $this->username, $this->email);
        $eventData = $event->toArray();

        $expectedKeys = ['eventId', 'userId', 'username', 'email', 'occurredOn'];
        $this->assertEquals($expectedKeys, array_keys($eventData));
    }
}
```

## 5. Tester un Command Handler (CQRS)

Pattern : mock du repository, verification du comportement, callback assertion.

```php
#[Group('prod')]
final class RegisterUserHandlerTest extends TestCase
{
    private RegisterUserHandler $handler;
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = new SymfonyPasswordHasher();
        $this->handler = new RegisterUserHandler($this->userRepository, $this->passwordHasher);
    }

    public function testSuccessfulUserRegistration(): void
    {
        $command = new UserRegisterCommand(
            email: 'test@example.com',
            password: 'StrongPassword123!',
            username: 'john_doe'
        );

        $capturedUser = null;
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) use (&$capturedUser) {
                $capturedUser = $user;

                // Verifier les Value Objects
                $this->assertEquals('test@example.com', $user->getEmail()->getValue());
                $this->assertEquals('john_doe', $user->getUsername()->getValue());
                $this->assertTrue($user->verifyPassword('StrongPassword123!', $this->passwordHasher));

                // Verifier le domain event
                $events = $user->getUncommittedEvents();
                $this->assertCount(1, $events);
                $this->assertInstanceOf(UserRegisteredEvent::class, $events[0]);

                return true;
            }));

        $user = ($this->handler)($command);

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->getUserId()->equals($capturedUser->getUserId()));
    }
}
```

## Checklist test unitaire

- [ ] Extends `TestCase` (pas `WebTestCase`)
- [ ] `#[Group('prod')]` pour les tests critiques
- [ ] DataProvider pour les cas multiples (valid/invalid)
- [ ] Un test par comportement (pas de mega-test)
- [ ] Mock des interfaces (repository, services externes)
- [ ] Verifier les domain events emis
- [ ] Verifier les exceptions avec `expectException()`
- [ ] Pas d'acces BDD, pas de HTTP, pas de filesystem

## Commandes

```bash
# Tous les tests unitaires
php bin/phpunit --testsuite unit

# Un groupe specifique
php bin/phpunit --group value-object
php bin/phpunit --group prod

# Un fichier specifique
php bin/phpunit tests/Unit/User/Domain/ValueObject/EmailTest.php
```
