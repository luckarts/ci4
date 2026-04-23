---
name: apex:validator
description: "Phase 3 - Quality gates with 3 depth levels (lint-only, full, integration)"
category: apex/core
argument-hint: [task-id] [--depth=lint-only|full|integration]
triggers:
  - apex validator
  - apex verify
  - run tests
---

# APEX VALIDATOR (Phase 3)

## Depth levels

| Level | Checks | Used by |
|-------|--------|---------|
| `lint-only` | PHPStan + GrumPHP blacklist | Subtasks |
| `full` | + unit tests | Features |
| `integration` | + E2E via Docker | Milestones + tasks with E2E files |

**Rule**: If `tests/E2E/**/*.php` were created/modified → force `depth=integration`.

Fix loop: max 3 iterations. On failure → back to BUILDER with exact error.
