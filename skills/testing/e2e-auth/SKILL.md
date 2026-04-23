---
name: test:e2e-auth
description: "Tester l'authentification et les permissions en E2E — 401 vs 403, route API Platform vs route Symfony classique (#[IsGranted])"
argument-hint: [401|403|isGranted|apiplatform|symfony-route]
triggers:
  - test 401
  - test 403
  - test permission e2e
  - test sans token
  - test auth e2e
  - IsGranted test
  - route symfony classique test
  - route api platform auth
  - test accès refusé
  - 401 vs 403
  - unauthenticated test
  - unauthorized test
  - forbidden test
  - test sécurité endpoint
  - matrice permissions test
---

# Tester l'authentification et les permissions en E2E

---

## 401 vs 403 — Référence rapide

| Code | Signification | Quand |
|------|--------------|-------|
| **401 Unauthorized** | Non authentifié | Pas de token, token invalide, token expiré |
| **403 Forbidden** | Authentifié mais droits insuffisants | Mauvais rôle, mauvais owner, ressource privée |

---

## Route API Platform vs Route Symfony classique

Ce projet utilise deux types de routes, avec un comportement d'authentification différent.

### Route API Platform (StatePovider / StateProcessor)

Protégée par le firewall OAuth2 stateless. Sans token → **401 automatique**.

```php
// Dans la Resource ou via security: "is_granted('ROLE_USER')"
// Le firewall OAuth2 intercepte avant même d'atteindre la ressource
```

```php
// Test : 401 sans token
$response = $this->apiRequest('GET', '/api/tasks');          // pas de token
$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());

$response = $this->apiRequest('POST', '/api/tasks/any/attachments'); // pas de token
$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
```

### Route Symfony classique avec `#[IsGranted]`

Route déclarée avec `#[Route]` dans un Controller Symfony (pas API Platform).
Exemple : `AttachmentDownloadController` → `/api/attachments/{id}/download`.

`#[IsGranted('ROLE_USER')]` est appliqué **après** que le firewall ait déterminé si l'utilisateur est authentifié.

- **Sans token** (anonyme) + firewall stateless OAuth2 → **401**
- **Avec token valide** mais sans le rôle → **403**

```php
// Test 401 : route Symfony classique sans token
$response = $this->apiRequest('GET', '/api/attachments/00000000-0000-0000-0000-000000000000/download');
$this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
```

**Important :** l'ID utilisé dans l'URL n'a pas besoin d'exister pour tester le 401.
Le firewall rejette la requête avant même d'appeler le controller.

---

## Pattern : Matrice de permissions complète

Le test de la matrice couvre les 3 acteurs dans l'ordre naturel (du plus restrictif au plus permissif).

```php
#[Test]
#[Group('e2e')]
#[Group('{bc}')]
public function permission_matrix(): void
{
    $this->createUser('{bc}-perm-owner@example.com', 'password123');
    $this->createUser('{bc}-perm-other@example.com', 'password123');

    $ownerToken = $this->getOAuth2Token('{bc}-perm-owner@example.com', 'password123');
    $otherToken = $this->getOAuth2Token('{bc}-perm-other@example.com', 'password123');

    // Setup : créer la ressource avec le owner
    $response = $this->apiRequest('POST', '/api/resource', $ownerToken, [...]);
    $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    $resourceId = json_decode($response->getContent(), true)['id'];

    // 1. Sans auth → 401
    $this->assertSame(
        Response::HTTP_UNAUTHORIZED,
        $this->apiRequest('DELETE', "/api/resource/{$resourceId}")->getStatusCode(),
    );

    // 2. Autre user authentifié → 403
    $this->assertSame(
        Response::HTTP_FORBIDDEN,
        $this->apiRequest('DELETE', "/api/resource/{$resourceId}", $otherToken)->getStatusCode(),
    );

    // 3. Owner → succès
    $this->assertSame(
        Response::HTTP_NO_CONTENT,
        $this->apiRequest('DELETE', "/api/resource/{$resourceId}", $ownerToken)->getStatusCode(),
    );
}
```

---

## Pattern : Test 401 isolé (route Symfony avec `#[IsGranted]`)

Quand on ajoute un endpoint sur un controller Symfony classique (pas API Platform),
tester séparément que `#[IsGranted]` est bien actif.

```php
#[Test]
#[Group('e2e')]
#[Group('{bc}')]
public function download_without_auth_returns_401(): void
{
    // Pas besoin de créer une ressource réelle :
    // le firewall rejette avant d'atteindre le controller
    $response = $this->apiRequest('GET', '/api/attachments/00000000-0000-0000-0000-000000000000/download');
    $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
}
```

---

## Pattern : Token invalide → 401

```php
#[Test]
#[Group('e2e')]
#[Group('{bc}')]
public function invalid_token_returns_401(): void
{
    // Simuler un token malformé / expiré
    $headers = ['HTTP_AUTHORIZATION' => 'Bearer invalid_fake_token_xyz'];
    $this->client->request('GET', '/api/tasks', [], [], $headers);

    $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
}
```

---

## Où placer les tests auth

| Type de test | Fichier recommandé |
|---|---|
| 401 pour une opération spécifique à une ressource | Dans le fichier de test de la ressource (`{Resource}Test.php`) |
| 403 owner vs autre user | Dans `Delete{Resource}Test.php` ou `{BC}WorkflowTest.php` (scénario 4) |
| Matrice complète (401 + 403 + 2xx) | Dans `{BC}WorkflowTest.php` (scénario 4 obligatoire) |
| 401 sur route Symfony classique (`#[IsGranted]`) | Dans le fichier de test qui couvre cette route |

**Règle :** ne pas créer un fichier `AuthenticationTest.php` global par BC. Concentrer les tests de permission dans le WorkflowTest (scénario 4) ou dans les fichiers de test de la ressource concernée.

---

## Checklist test d'authentification

- [ ] 401 testé avec `null` comme token (pas de header `Authorization`)
- [ ] 403 testé avec un token valide d'un autre utilisateur (pas un token invalide)
- [ ] Email unique par variante d'acteur (`{bc}-perm-owner@`, `{bc}-perm-other@`)
- [ ] Pour les routes Symfony classiques (`#[IsGranted]`) : confirmer que sans token → 401 (pas 302 ou 403)
- [ ] La ressource ciblée dans le test 401 n'a pas besoin d'exister (le firewall intercepte avant)
- [ ] Ordre des assertions dans la matrice : 401 → 403 → succès
