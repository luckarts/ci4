---
name: apex:milestone
description: "Integration checkpoint: Aggregate → Test → Validate → Commit"
category: apex/orchestrator
argument-hint: [milestone-id]
triggers:
  - apex milestone
  - integration checkpoint
---

# APEX Milestone Orchestrator

Validates that multiple completed tasks work together.

```
Architect(aggregate) → Validator(integration) → Reviewer → Documenter
```

Commit prefix: `milestone(scope):`.
