---
name: apex:builder
description: "Phase 2 - Pattern-aware implementation following ARCHITECT artifacts"
category: apex/core
argument-hint: [task-id]
triggers:
  - apex builder
  - apex execute
  - implement code
---

# APEX BUILDER (Phase 2)

Implement following ARCHITECT execution-strategy, one commit per `<commit>` boundary.

## Rules
- Read file before modifying
- Stage specific files only (never `git add .`)
- Use `<commit message="...">` verbatim when provided
- E2E sub-workflow: create → run → fix loop → merge into existing class → commit
- Respect YAGNI boundaries — nothing from the NOT-doing list
