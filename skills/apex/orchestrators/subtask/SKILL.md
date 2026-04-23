---
name: apex:subtask
description: "Execute technical subtask: Analyze → Execute → Commit"
category: apex/orchestrator
argument-hint: [task-id|description]
triggers:
  - apex subtask
  - technical task
---

# APEX Subtask Orchestrator

For structural/technical changes that don't need full E2E tests.

```
Architect → Builder → Validator(lint-only) → Documenter
```

Reviewer is skipped. Commit prefix: `chore(scope):` or `refactor(scope):`.
