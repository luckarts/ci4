---
description: Stage, commit, and push the current branch following git governance rules.
---

1. Run `/review` to ensure lint/tests/security checks pass locally.
2. Review and stage changes with `git add` (avoid staging generated or secret files).
3. Craft a Conventional Commit message (types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert).
   - Never add AI attribution strings to commits.
4. Commit with `git commit` using the prepared message. If commitlint fails, fix and retry.
5. Push to origin: `git push origin $(git branch --show-current)`.
6. Trigger remote checks: `gh workflow run ci.yml --ref $(git branch --show-current)`
7. Wait for workflow to finish before opening a pull request.
