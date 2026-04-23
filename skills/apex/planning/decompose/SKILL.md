---
name: apex:decompose
description: Break feature into hierarchical subtasks, features, and milestones
category: apex/planning
argument-hint: <feature>
triggers:
  - apex decompose
  - break down feature
---

# APEX Decompose

Break a feature into atomic tasks with clear types.

Output:
```
Feature: [name]
├── [SUBTASK] Create schema          → chore commit
├── [FEATURE] POST endpoint          → feat commit + tests
├── [FEATURE] GET endpoint           → feat commit + tests
└── [MILESTONE] Integration          → milestone commit
```

Rules:
- 1 HTTP operation per commit (REST APIs)
- Subtask = no behavior change, no E2E
- Feature = behavior change, E2E mandatory
- Milestone = integration of multiple tasks
