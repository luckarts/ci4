---
description: APEX research — Explore codebase before planning. Map existing patterns and constraints.
argument-hint: <area to investigate>
---

Before planning, map the existing state for: $ARGUMENTS

1. Read files related to the target domain
2. Identify patterns already in use (don't reinvent)
3. List constraints (linting rules, test coverage, conventions)
4. Spot regression risks

Output:
```
Current state:
- [existing file]: [role]
- [pattern in use]: [where]

Identified constraints:
- [technical constraint]

Regression risks:
- [risk] → [affected file]

Recommendation:
[proposed approach in 2-3 sentences]
```
