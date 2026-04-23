---
name: apex:documenter
description: "Phase 5 - Git commit with reflection, learning capture, and pattern trust updates"
category: apex/core
argument-hint: [task-id]
triggers:
  - apex documenter
  - apex commit
  - finalize task
---

# APEX DOCUMENTER (Phase 5)

1. Stage specific files → conventional commit with task ID + patterns
2. Reflection: what went well, what to improve, surprises
3. Pattern trust updates → `.apex/LEARNING.md`
4. Learning gate: if score ≥ 0.85 AND usage ≥ 3 → auto-invoke `apex:learn`

Do NOT commit if VALIDATOR failed or REVIEWER requested changes.
