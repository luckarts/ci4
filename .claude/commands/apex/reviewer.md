---
description: APEX Phase 4 — Code review against CoD decisions and quality checklist.
---

Review the current diff against the ARCHITECT plan's Chain of Decisions.

### Architectural conformance
- [ ] Every CoD decision is respected
- [ ] No unplanned pattern introduced
- [ ] Bounded context integrity maintained

### Code quality
- [ ] Explicit naming (no redundant comments)
- [ ] No magic strings / numbers
- [ ] Error handling at correct layer

### Tests
- [ ] Every mocked service has ≥1 integration test hitting the real service
- [ ] Error cases covered
- [ ] Specific assertions (not just `assertResponseIsSuccessful`)

### Security
- [ ] No secret in diff
- [ ] Access controlled by Voter or annotation
- [ ] Inputs validated at boundary

Output:
```
[PASS/FAIL] Architectural conformance
[PASS/FAIL] Code quality
[PASS/FAIL] Tests
[PASS/FAIL] Security

Blockers: [list if FAIL]
Non-blocking suggestions: [optional]
```
