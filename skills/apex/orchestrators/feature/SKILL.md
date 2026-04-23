---
name: apex:feature
description: "Feature implementation: Architect → Builder → Validator → Reviewer → Documenter"
category: apex/orchestrator
argument-hint: [feature-file|feature-id]
triggers:
  - apex feature
  - implement feature
---

# APEX Feature Orchestrator

Full 5-phase workflow for user-facing features.

```
Architect → Builder → Validator(full) → Reviewer → Documenter
```

Rules:
- Tests MANDATORY before each commit (make test-e2e)
- Never `git add .` — always stage specific files
- No Co-Authored-By
- Commit prefix: `feat(scope):`
