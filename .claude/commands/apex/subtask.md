---
description: APEX subtask — Analyze → Execute → Commit a single step from an approved plan.
argument-hint: <subtask description>
---

Execute subtask: $ARGUMENTS

This subtask comes from an already-approved ARCHITECT plan.

1. **Analyze**: read the target file, identify exact impact
2. **Execute**: modify only what is specified for this subtask
3. **Verify**: static analysis + related unit tests
4. **Commit**: conventional message referencing the plan step

Constraints:
- Do not touch files outside the subtask scope
- If an unforeseen problem arises → report before improvising
- 1 subtask = 1 commit
