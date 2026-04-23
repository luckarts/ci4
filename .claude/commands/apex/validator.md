---
description: APEX Phase 3 — Quality gates: lint → unit tests → integration/E2E.
---

Run quality gates in order. Stop at the first failure.

### Gate 1 — Static analysis
```bash
# Adapt for your stack
vendor/bin/phpstan analyse --level=6
```

### Gate 2 — Unit tests
```bash
php bin/phpunit --testsuite unit
```

### Gate 3 — Integration + E2E
```bash
make test-e2e
```

Report:
- [ ] Static analysis: PASS/FAIL
- [ ] Unit tests: PASS/FAIL (X/Y passed)
- [ ] E2E smoke: PASS/FAIL

On failure: return to apex:builder with the exact error.
