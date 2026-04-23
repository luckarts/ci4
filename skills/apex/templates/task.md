# Task File Template (5-Phase)

Task files live in `.apex/tasks/feature_<slug>/`.

## Subtask template

```yaml
---
identifier: F001-S001
parent: F001
title: "Create entity schema"
type: subtask
workflow: [architect, builder, validator, documenter]
validatorDepth: lint-only
phase: pending
status: active
created: YYYY-MM-DD
---

# F001-S001: Create entity schema

## Context
[Brief description]

## Files
| File | Action |
|------|--------|
| src/BC/Domain/Entity/X.php | CREATE |

---

<architect>
<cot><!-- Chain of Thought analysis --></cot>
<tot><!-- Tree of Thought: 3+ alternatives --></tot>
<cod><!-- Chain of Draft: 3 iterations --></cod>
<yagni><!-- Explicit "not doing" list --></yagni>
<patterns><!-- Pattern selection rationale --></patterns>
</architect>

<builder><!-- Phase 2: implementation --></builder>
<validator><!-- Phase 3: lint-only --></validator>
<documenter><!-- Phase 5: commit + reflection --></documenter>
```

## Feature template

```yaml
---
identifier: F001-F001
parent: F001
title: "Implement POST endpoint"
type: feature
workflow: [architect, builder, validator, reviewer, documenter]
validatorDepth: full
status: active
created: YYYY-MM-DD
depends_on: [F001-S001]
---

# F001-F001: Implement POST endpoint

## Context
[Description — bounded context, business rules, constraints]

## Endpoints
| Method | URI | Auth | Voter |
|--------|-----|------|-------|
| POST | /api/resource | JWT | RESOURCE_EDIT |

## Acceptance Criteria
- [ ] AC-1: [Criterion]
- [ ] AC-N: PHPStan level 6 pass

## Security Verification
- [ ] Provider filters by ownership (cross-tenant impossible)
- [ ] `isGranted('VOTER_ATTR', $entity)` via dedicated Voter
- [ ] Non-owner → 403 | Unknown resource → 404

---

## Commits

### `feat(scope): description` ⏳
> ⚠️ Run tests before committing: `cd backend && make test-e2e`

| File | Action |
|------|--------|
| src/BC/Application/Service/MyService.php | CREATE |
| tests/E2E/BC/MyFeatureTest.php | CREATE |

---

<architect><cot/><tot/><cod/><yagni/><patterns/></architect>
<builder><!-- one commit at a time, tests before each commit --></builder>
<validator><!-- full: PHPStan + GrumPHP + unit + E2E --></validator>
<reviewer><!-- review against architect decisions --></reviewer>
<documenter><!-- commit + reflection + learning --></documenter>
```

## Commit rules

- One HTTP operation per commit (REST APIs: POST → GET → DELETE → PATCH)
- Always run `make test-e2e` before committing
- Mark ✅ in file after each successful commit
- Never `git add .` — always stage specific files

## Phase values

`pending` → `architect` → `builder` → `validator` → `reviewer` → `documenter` → `complete` | `rework`
