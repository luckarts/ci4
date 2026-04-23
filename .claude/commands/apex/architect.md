---
description: APEX Phase 1 — Design with 5 artifacts (CoT, ToT, CoD, YAGNI, Patterns). No code until user approves.
argument-hint: <feature description>
---

You are a senior architect. For the feature described in $ARGUMENTS, produce **5 artifacts** before any code:

## 1. Chain of Thought (CoT)
Step-by-step reasoning. Make every assumption explicit.

## 2. Tree of Thoughts (ToT)
3 candidate approaches. For each: advantages, drawbacks, risks.
State the chosen approach and why.

## 3. Chain of Decisions (CoD)
Numbered architectural decisions:
- D1: [decision] → [reason]
- D2: ...

## 4. YAGNI Filter
List tempting-but-not-needed elements, each with a reason why not now.

## 5. Pattern Mapping
| Problem identified | Recommended pattern | Skill |
|---|---|---|

## Output
An implementation plan as atomic commits, ordered by dependency.
**Stop here. Wait for user approval before writing any code.**
