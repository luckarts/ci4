---
name: apex:architect
description: "Phase 1 - Design with 5 mandatory artifacts (CoT, ToT, CoD, YAGNI, Patterns)"
category: apex/core
argument-hint: [task-id|description]
triggers:
  - apex architect
  - apex analyze
  - design task
---

# APEX ARCHITECT (Phase 1)

Produce 5 artifacts before any implementation:

1. **Chain of Thought (CoT)** — step-by-step analysis of current state, requirements, constraints
2. **Tree of Thought (ToT)** — 3+ candidate approaches, pros/cons, decision
3. **Chain of Draft (CoD)** — 3 progressive drafts → selected draft
4. **YAGNI Declaration** — explicit list of what we are NOT doing, with rationale
5. **Pattern Selection** — pattern chosen with confidence score and rationale

Output: `<execution-strategy>` with `<step>` and `<commit>` boundaries.

**Stop after artifacts. Wait for user approval before implementation.**

## Rules
- ONE HTTP OPERATION PER COMMIT for REST APIs
- Doctrine migrations: always `make migration`, never hand-write SQL
- After any behavior-adding step: create test → run → fix loop → merge → commit
