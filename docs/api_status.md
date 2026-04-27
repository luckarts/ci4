# API Status — PalaceWork CI4

> Source de vérité de l'état des endpoints. Mis à jour en fin de chaque session.
> Mined by MemPalace → searchable via `mempalace search "endpoint status"`.

**Dernière mise à jour** : 2026-04-25 · `feature/backend/authentification-oauth2`

---

## Endpoints

### Auth

| Endpoint | Méthode | Auth | Status | Tests | Notes |
|----------|---------|------|--------|-------|-------|
| /auth/register | POST | — | ✅ | via helper | Utilisé dans ApiTestCase.getTokenPair() |
| /auth/token | POST | — | ✅ | 4 Feature | password grant OK |
| /auth/token | POST | — | ⚠️ | 1 Feature ❌ | **refresh grant** — "Token has been revoked" malgré revoked=false en DB. Cause : claim mismatch JWT ↔ DB identifier |
| /auth/revoke | POST | Bearer | ✅ | 2 Feature | access token + refresh token |

### Users

| Endpoint | Méthode | Auth | Status | Tests | Notes |
|----------|---------|------|--------|-------|-------|
| /users/{id} | GET | Bearer | ✅ | 4 Feature | ownership check, 401/403/404 |
| /users/{id}/profile | PUT | Bearer | ✅ | 5 Feature | validation first_name/last_name |
| /users/{id} | DELETE | Bearer | ✅ | 4 Feature + 2 Integration | cascade access_tokens + refresh_tokens |

---

## Fix critiques de la session

| Bug | Symptôme | Fix | Fichier |
|-----|----------|-----|---------|
| PostgreSQL boolean PDO | `isAccessTokenRevoked()` retournait `true` pour tous les tokens — 100% des endpoints protégés bloqués | Comparer avec `=== 't'` au lieu de cast implicite (`"f"` est truthy en PHP) | `app/Repositories/AccessTokenRepository.php` |
| RefreshTokenTrait conflit | PHP fatal — propriétés redéclarées | Supprimer l'import du trait, garder les implémentations directes dans l'entity | `app/Entities/RefreshTokenEntity.php` |
| Migration `password` vs `password_hash` | Tests passaient en isolation, requêtes réelles échouaient | Aligner le nom de colonne dans la migration de test | `tests/_support/Database/Migrations/` |

---

## Prochaine session

- [ ] **Priorité 1** — Debugger refresh token grant : identifier JWT claim vs DB identifier mismatch
- [ ] **Priorité 2** — Rate limiting SEC003 (brute-force protection sur POST /auth/token)
- [ ] **Priorité 3** — GET /health (OPS001)
- [ ] Tests intégration AUTH-INT001 (≥1 test sans mock par flow OAuth2)
- [ ] Ajouter Feature test pour POST /auth/register

---

## Métriques tests

| Suite | Count | Status |
|-------|-------|--------|
| Feature | 13 | ✅ (refresh grant skipped) |
| Integration | 4 | ✅ |
| Unit | 7 | ✅ |
| **Total** | **~24** | **✅ hors refresh grant** |
