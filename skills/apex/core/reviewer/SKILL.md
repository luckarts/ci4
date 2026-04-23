---
name: apex:reviewer
description: "Phase 4 - Code review against architectural decisions, YAGNI, and patterns"
category: apex/core
argument-hint: [task-id]
triggers:
  - apex reviewer
  - code review
---

# APEX REVIEWER (Phase 4)

Review implementation against ARCHITECT artifacts:
- Decision compliance (ToT chosen approach)
- Pattern compliance (Pattern Selection)
- YAGNI enforcement (nothing from NOT-doing list)
- Quality: type safety, error handling, security

Verdict: `approve` → DOCUMENTER | `request-changes` → fix and re-submit.

Skipped for subtasks (lint-only is sufficient).
