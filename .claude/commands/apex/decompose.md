---
description: APEX decompose — Break a feature into hierarchical subtasks and atomic commits.
argument-hint: <feature description>
---

Decompose this feature into atomic commits: $ARGUMENTS

Output a hierarchical plan:

```
Feature: [name]
  ├── Milestone 1: [name]
  │   ├── Subtask 1.1: [file] — [action]
  │   ├── Subtask 1.2: [file] — [action]
  │   └── Commit: "feat(scope): ..."
  └── Milestone 2: [name]
      ├── Subtask 2.1: ...
      └── Commit: "feat(scope): ..."
```

Rules:
- 1 subtask = 1 file = 1 responsibility
- Subtasks ordered by dependency (not arbitrary order)
- Each milestone must compile on its own (no orphan code)
- If a subtask > 30 min → decompose further
