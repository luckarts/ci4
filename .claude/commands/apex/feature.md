---
description: APEX full orchestrator — runs all 5 phases for a complete feature.
argument-hint: <feature description>
---

Full APEX workflow for: $ARGUMENTS

Execute phases in sequence. Each phase must be validated before moving to the next.

```
Phase 1 → /apex:architect "$ARGUMENTS"
           ↓ (wait for user approval)
Phase 2 → /apex:builder (step by step)
           ↓ (each step committed)
Phase 3 → /apex:validator
           ↓ (all gates green)
Phase 4 → /apex:reviewer
           ↓ (no blockers)
Phase 5 → /apex:documenter
```

Use this for features that involve:
- A new Bounded Context
- A new architectural pattern
- More than 3 files modified

For small fixes: use `/git:cm` directly.
