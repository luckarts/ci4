#!/usr/bin/env bash
# init-project.sh — Initialize a new project with .claude/, .claw/, CLAUDE.md, AGENTS.md
# Usage: bash init-project.sh [project-name] [stack]
# Example: bash init-project.sh "my-api" "Symfony/PHP"

set -euo pipefail

PROJECT_NAME="${1:-my-project}"
STACK="${2:-Symfony 7 · API Platform 4 · PHP 8.3 · PostgreSQL}"
ROOT="$(pwd)"

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}✓${NC} $1"; }
info() { echo -e "${BLUE}→${NC} $1"; }
warn() { echo -e "${YELLOW}!${NC} $1"; }

echo ""
echo "  Initializing: ${PROJECT_NAME}"
echo "  Stack: ${STACK}"
echo "  Root: ${ROOT}"
echo ""

# ─── .claude/ ─────────────────────────────────────────────────────────────────

info "Creating .claude/ structure..."
mkdir -p "${ROOT}/.claude/commands/git"
mkdir -p "${ROOT}/.claude/commands/apex"
mkdir -p "${ROOT}/.claude/plans"
mkdir -p "${ROOT}/.claude/memory"

# ── mempalace_identity.txt ────────────────────────────────────────────────────
cat > "${ROOT}/.claude/mempalace_identity.txt" << IDENTITY
# PROJET : ${PROJECT_NAME}

## Vision globale

[Décris l'objectif du projet — ex: Application fullstack de gestion de X avec architecture DDD/Hexagonale]

---

## Stack technique

### Backend
- ${STACK}
- Architecture : DDD / Hexagonal — Bounded Contexts dans \`src/{BC}/{Domain,Application,Infrastructure}\`

### Frontend
- [ex: Nuxt 3 · Vue 3 · Pinia · Tailwind CSS · TypeScript strict]

---

## Bounded Contexts existants

| BC | Statut |
|---|---|
| \`Shared/\` | ✅ |
| \`User/\` | ⏳ En cours |

---

## Roadmap — grandes phases

1. **Phase 0** : Fondation — stack, CI, BCs de base
2. **Phase 1** : Auth — OAuth2 / JWT
3. **Phase 2** : Domaine métier principal
4. **Phase 3** : Collaboration — multi-tenant, invitations
5. **Phase 4** : Temps réel — SSE / Mercure

---

## Conventions clés

- **Git flow** : PRs vers \`develop\` (pas \`main\`). \`main\` = production stable.
- **Tests** : E2E via Docker Compose (\`make test\`). Tout service mocké → ≥1 test integration réel.
- **Commits** : conventionnels. Jamais \`Co-Authored-By\`. Jamais committer \`.gitignore\`.
- **Plan avant implémentation** : toujours écrire le plan complet et attendre validation.
- **Bash** : une commande à la fois, pas de chaînage \`&&\`.
- **Réponses** : pas de résumé en fin de réponse. Opérations mémoire silencieuses.
IDENTITY
log ".claude/mempalace_identity.txt"

# ── git commands ──────────────────────────────────────────────────────────────
cat > "${ROOT}/.claude/commands/git/cm.md" << 'CMD'
---
description: Stage working tree changes and create a Conventional Commit (no push).
---

1. Run `git status --short` to review pending changes.
2. For each file, open a diff (`git diff -- path/to/file`) and ensure no secrets or credentials are present.
3. Stage the files intentionally (`git add path/to/file`). Avoid `git add .` unless every change was reviewed.
4. Generate a Conventional Commit message (types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert).
   - Commit subject ≤ 72 chars.
   - Scope uses kebab-case (e.g., `feat(auth): ...`).
5. Run `git commit` and paste the generated message.
6. Show the resulting commit (`git log -1 --stat`) and keep the commit hash handy.
7. **Do not push** in this command. Use `git/cp.md` when ready to publish.
CMD
log ".claude/commands/git/cm.md"

cat > "${ROOT}/.claude/commands/git/cp.md" << 'CMD'
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
CMD
log ".claude/commands/git/cp.md"

cat > "${ROOT}/.claude/commands/git/pr.md" << 'CMD'
---
description: Create a pull request from the current branch.
argument-hint: [target-branch]
---

## Variables

TARGET_BRANCH: $1 (defaults to `develop`)
SOURCE_BRANCH: current branch (`git branch --show-current`)

## Workflow

1. Ensure `/review` and `/security-scan` have passed locally.
2. Confirm CI workflow succeeded for `SOURCE_BRANCH`.
3. Create the PR using GitHub CLI:
   ```bash
   gh pr create \
     --base "$TARGET_BRANCH" \
     --head "$SOURCE_BRANCH" \
     --title "<Conventional PR title>" \
     --body-file .github/pull_request_template.md
   ```
   If no template exists, provide a summary with Context, Testing, and Security results.
4. Add labels: `gh pr edit --add-label "status: in-review"`.
5. Share the PR link and ensure at least one human approval before merge.
CMD
log ".claude/commands/git/pr.md"

# ── apex commands ─────────────────────────────────────────────────────────────
mkdir -p "${ROOT}/.claude/commands/apex"

cat > "${ROOT}/.claude/commands/apex/architect.md" << 'CMD'
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
CMD

cat > "${ROOT}/.claude/commands/apex/builder.md" << 'CMD'
---
description: APEX Phase 2 — Implement following ARCHITECT artifacts, one commit per step.
argument-hint: <step number or "all">
---

Pre-requisite: ARCHITECT artifacts are approved.

Implement step $ARGUMENTS from the ARCHITECT plan:

1. Read the target file(s) before modifying anything
2. Apply only what the plan specifies for this step — no scope creep
3. Follow the CoD decisions exactly
4. Apply the pattern identified in the Pattern Mapping
5. Run: static analysis + unit tests for affected files
6. Commit: `type(scope): description` — reference the plan step

If an unexpected obstacle arises: **stop and report**, do not improvise.
CMD

cat > "${ROOT}/.claude/commands/apex/validator.md" << 'CMD'
---
description: APEX Phase 3 — Quality gates: lint → unit tests → integration/E2E.
---

Run quality gates in order. Stop at the first failure.

### Gate 1 — Static analysis
```bash
# Adapt for your stack
vendor/bin/phpstan analyse --level=6
```

### Gate 2 — Unit tests
```bash
php bin/phpunit --testsuite unit
```

### Gate 3 — Integration + E2E
```bash
make test-e2e
```

Report:
- [ ] Static analysis: PASS/FAIL
- [ ] Unit tests: PASS/FAIL (X/Y passed)
- [ ] E2E smoke: PASS/FAIL

On failure: return to apex:builder with the exact error.
CMD

cat > "${ROOT}/.claude/commands/apex/reviewer.md" << 'CMD'
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
CMD

cat > "${ROOT}/.claude/commands/apex/documenter.md" << 'CMD'
---
description: APEX Phase 5 — Documentation commit + reflection on decisions and learnings.
---

After Phase 4 is approved:

1. Update `AGENTS.md` if a new pattern was applied
2. Update `memory/` if an important architectural decision was made
3. Create a documentation commit:

```
docs(architecture): document decisions from [feature]

Patterns applied: [list]
Decisions retained: [D1, D2, ...]
Learned: [1 line — what was surprising or non-obvious]
```

4. Answer these reflection questions (save to `memory/`, not in code):
   - What went differently from the plan?
   - Which decision was hardest?
   - What would you do differently next time?
CMD

cat > "${ROOT}/.claude/commands/apex/feature.md" << 'CMD'
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
CMD

cat > "${ROOT}/.claude/commands/apex/subtask.md" << 'CMD'
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
CMD

cat > "${ROOT}/.claude/commands/apex/decompose.md" << 'CMD'
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
CMD

cat > "${ROOT}/.claude/commands/apex/research.md" << 'CMD'
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
CMD

log ".claude/commands/apex/ (architect, builder, validator, reviewer, documenter, feature, subtask, decompose, research)"

# ── settings.local.json ───────────────────────────────────────────────────────
cat > "${ROOT}/.claude/settings.local.json" << 'JSON'
{
  "permissions": {
    "allow": [
      "Bash(git status*)",
      "Bash(git diff*)",
      "Bash(git log*)",
      "Bash(git add*)",
      "Bash(git commit*)",
      "Bash(git push*)",
      "Bash(git branch*)",
      "Bash(git stash*)"
    ]
  }
}
JSON
log ".claude/settings.local.json"

# ── MEMORY.md ─────────────────────────────────────────────────────────────────
cat > "${ROOT}/.claude/memory/MEMORY.md" << 'MEMO'
# Project Memory — ${PROJECT_NAME}

> Add memory files here. Each entry: `- [Title](file.md) — one-line hook`

## Feedback

## Project Context

## References
MEMO
log ".claude/memory/MEMORY.md"


# ─── .claw/ ───────────────────────────────────────────────────────────────────

info "Creating .claw/ structure..."
mkdir -p "${ROOT}/.claw/agents"
mkdir -p "${ROOT}/.claw/commands"

# ── agents ────────────────────────────────────────────────────────────────────
cat > "${ROOT}/.claw/agents/architect.toml" << 'TOML'
name = "architect"
description = "Architecture planning — DDD/Hexagonal, API design, bounded contexts"
model = "claude-sonnet-4-6"
TOML

cat > "${ROOT}/.claw/agents/debugger.toml" << 'TOML'
name = "debugger"
description = "Systematic root-cause analysis — reads before assuming"
model = "claude-sonnet-4-6"
TOML

cat > "${ROOT}/.claw/agents/reviewer.toml" << 'TOML'
name = "reviewer"
description = "Code review against architectural decisions and quality gates"
model = "claude-sonnet-4-6"
TOML
log ".claw/agents/ (architect, debugger, reviewer)"


# ── CLAW.md ───────────────────────────────────────────────────────────────────
cat > "${ROOT}/.claw/CLAW.md" << CLAWMD
# CLAW.md — ${PROJECT_NAME}

## Règles absolues

- Toujours lire un fichier avant de le modifier
- Une seule modification à la fois (pas de chaînage \`&&\`)
- Plan avant implémentation — attendre validation avant de coder
- Jamais de \`Co-Authored-By\` dans les commits
- Répondre sans résumé en fin de réponse

## Stack

${STACK}

## Vérification avant commit

Adapter selon le stack :
\`\`\`bash
vendor/bin/phpstan analyse
php bin/phpunit --testsuite unit
\`\`\`

## Workflow APEX

| Phase | Skill | Rôle |
|-------|-------|------|
| 1 | \`apex:architect\` | Design + 5 artifacts |
| 2 | \`apex:builder\` | Implémentation |
| 3 | \`apex:validator\` | Quality gates |
| 4 | \`apex:reviewer\` | Review CoD |
| 5 | \`apex:documenter\` | Commit + reflection |

Orchestrateur complet : \`apex:feature\`
CLAWMD
log ".claw/CLAW.md"


# ─── skills/ ──────────────────────────────────────────────────────────────────

info "Creating skills/ (apex)..."

# Si le dossier skills/ existe à côté du script → on le copie tel quel
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_SKILLS="${SCRIPT_DIR}/../skills"

if [ -d "${SOURCE_SKILLS}/apex" ]; then
  cp -r "${SOURCE_SKILLS}" "${ROOT}/skills"
  log "skills/ (copied from ${SOURCE_SKILLS})"
else
  # Stubs minimaux — à remplacer avec les vrais SKILL.md du projet de référence
  mkdir -p "${ROOT}/skills/apex/core/architect"
  mkdir -p "${ROOT}/skills/apex/core/builder"
  mkdir -p "${ROOT}/skills/apex/core/validator"
  mkdir -p "${ROOT}/skills/apex/core/reviewer"
  mkdir -p "${ROOT}/skills/apex/core/documenter"
  mkdir -p "${ROOT}/skills/apex/orchestrators/feature"
  mkdir -p "${ROOT}/skills/apex/orchestrators/subtask"
  mkdir -p "${ROOT}/skills/apex/orchestrators/milestone"
  mkdir -p "${ROOT}/skills/apex/planning/research"
  mkdir -p "${ROOT}/skills/apex/planning/plan"
  mkdir -p "${ROOT}/skills/apex/planning/decompose"
  mkdir -p "${ROOT}/skills/apex/config"
  mkdir -p "${ROOT}/skills/apex/agents"
  mkdir -p "${ROOT}/skills/apex/templates"

  # README
  cp /dev/stdin "${ROOT}/skills/apex/README.md" << 'SKILLREADME'
# APEX Skills

Workflow: Research → Plan → [Decompose] → For each task: Subtask | Feature | Milestone

| Skill | Workflow |
|-------|----------|
| `/apex:subtask` | Analyze → Execute → Commit |
| `/apex:feature` | Analyze → Execute → Verify → Test → Commit |
| `/apex:milestone` | Aggregate → Test → Validate → Commit |

See full docs in each SKILL.md file.
SKILLREADME

  # config stub
  cat > "${ROOT}/skills/apex/config/SKILL.md" << 'SKILL'
---
name: apex:config
description: Configure APEX workflow rules and behaviors
category: apex/config
argument-hint: [show|set|reset]
---

Configuration for the APEX workflow. Location: `.apex/config.json`

Usage:
- `/apex:config show` — show current config
- `/apex:config set testPolicy strict`
- `/apex:config reset`
SKILL

  # architect stub
  cat > "${ROOT}/skills/apex/core/architect/SKILL.md" << 'SKILL'
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
SKILL

  # builder stub
  cat > "${ROOT}/skills/apex/core/builder/SKILL.md" << 'SKILL'
---
name: apex:builder
description: "Phase 2 - Pattern-aware implementation following ARCHITECT artifacts"
category: apex/core
argument-hint: [task-id]
triggers:
  - apex builder
  - apex execute
  - implement code
---

# APEX BUILDER (Phase 2)

Implement following ARCHITECT execution-strategy, one commit per `<commit>` boundary.

## Rules
- Read file before modifying
- Stage specific files only (never `git add .`)
- Use `<commit message="...">` verbatim when provided
- E2E sub-workflow: create → run → fix loop → merge into existing class → commit
- Respect YAGNI boundaries — nothing from the NOT-doing list
SKILL

  # validator stub
  cat > "${ROOT}/skills/apex/core/validator/SKILL.md" << 'SKILL'
---
name: apex:validator
description: "Phase 3 - Quality gates with 3 depth levels (lint-only, full, integration)"
category: apex/core
argument-hint: [task-id] [--depth=lint-only|full|integration]
triggers:
  - apex validator
  - apex verify
  - run tests
---

# APEX VALIDATOR (Phase 3)

## Depth levels

| Level | Checks | Used by |
|-------|--------|---------|
| `lint-only` | PHPStan + GrumPHP blacklist | Subtasks |
| `full` | + unit tests | Features |
| `integration` | + E2E via Docker | Milestones + tasks with E2E files |

**Rule**: If `tests/E2E/**/*.php` were created/modified → force `depth=integration`.

Fix loop: max 3 iterations. On failure → back to BUILDER with exact error.
SKILL

  # reviewer stub
  cat > "${ROOT}/skills/apex/core/reviewer/SKILL.md" << 'SKILL'
---
name: apex:reviewer
description: "Phase 4 - Code review against architectural decisions, YAGNI, and patterns"
category: apex/core
argument-hint: [task-id]
triggers:
  - apex reviewer
  - code review
---

# APEX REVIEWER (Phase 4)

Review implementation against ARCHITECT artifacts:
- Decision compliance (ToT chosen approach)
- Pattern compliance (Pattern Selection)
- YAGNI enforcement (nothing from NOT-doing list)
- Quality: type safety, error handling, security

Verdict: `approve` → DOCUMENTER | `request-changes` → fix and re-submit.

Skipped for subtasks (lint-only is sufficient).
SKILL

  # documenter stub
  cat > "${ROOT}/skills/apex/core/documenter/SKILL.md" << 'SKILL'
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
SKILL

  # feature orchestrator stub
  cat > "${ROOT}/skills/apex/orchestrators/feature/SKILL.md" << 'SKILL'
---
name: apex:feature
description: "Feature implementation: Architect → Builder → Validator → Reviewer → Documenter"
category: apex/orchestrator
argument-hint: [feature-file|feature-id]
triggers:
  - apex feature
  - implement feature
---

# APEX Feature Orchestrator

Full 5-phase workflow for user-facing features.

```
Architect → Builder → Validator(full) → Reviewer → Documenter
```

Rules:
- Tests MANDATORY before each commit (make test-e2e)
- Never `git add .` — always stage specific files
- No Co-Authored-By
- Commit prefix: `feat(scope):`
SKILL

  # subtask orchestrator stub
  cat > "${ROOT}/skills/apex/orchestrators/subtask/SKILL.md" << 'SKILL'
---
name: apex:subtask
description: "Execute technical subtask: Analyze → Execute → Commit"
category: apex/orchestrator
argument-hint: [task-id|description]
triggers:
  - apex subtask
  - technical task
---

# APEX Subtask Orchestrator

For structural/technical changes that don't need full E2E tests.

```
Architect → Builder → Validator(lint-only) → Documenter
```

Reviewer is skipped. Commit prefix: `chore(scope):` or `refactor(scope):`.
SKILL

  # milestone orchestrator stub
  cat > "${ROOT}/skills/apex/orchestrators/milestone/SKILL.md" << 'SKILL'
---
name: apex:milestone
description: "Integration checkpoint: Aggregate → Test → Validate → Commit"
category: apex/orchestrator
argument-hint: [milestone-id]
triggers:
  - apex milestone
  - integration checkpoint
---

# APEX Milestone Orchestrator

Validates that multiple completed tasks work together.

```
Architect(aggregate) → Validator(integration) → Reviewer → Documenter
```

Commit prefix: `milestone(scope):`.
SKILL

  # planning stubs
  cat > "${ROOT}/skills/apex/planning/research/SKILL.md" << 'SKILL'
---
name: apex:research
description: Intelligence gathering — explore codebase before planning
category: apex/planning
argument-hint: <area>
triggers:
  - apex research
  - gather context
---

# APEX Research

Before planning, map the existing state.

1. Read files related to the target domain
2. Identify patterns already in use
3. List constraints (lint, tests, conventions)
4. Spot regression risks

Output: current state, constraints, regression risks, recommendation.
SKILL

  cat > "${ROOT}/skills/apex/planning/plan/SKILL.md" << 'SKILL'
---
name: apex:plan
description: Architecture planning with 5 high-level artifacts
category: apex/planning
argument-hint: <feature>
triggers:
  - apex plan
  - plan feature
---

# APEX Plan

High-level planning before decomposition.

Produce:
1. CoT — current state and requirements
2. ToT — 3 architectural approaches, decision
3. CoD — 3 drafts, selected
4. YAGNI — explicit NOT-doing list
5. Pattern mapping — patterns to apply

Output: feature decomposition ready for `apex:decompose`.
SKILL

  cat > "${ROOT}/skills/apex/planning/decompose/SKILL.md" << 'SKILL'
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
SKILL

  # ── agents ────────────────────────────────────────────────────────────────
  cat > "${ROOT}/skills/apex/agents/intelligence-gatherer.md" << 'AGENT'
# Intelligence Gatherer Agent

Agent specialized in codebase research and context gathering for APEX workflows.

## Role

Performs deep codebase exploration to gather context for the ARCHITECT phase.
Uses Glob, Grep, and Read tools to understand existing patterns, conventions, and code structure.

## Invocation

```
Task(subagent_type="Explore", prompt="<research query>")
```

## Tools Used

| Tool | Purpose |
|------|---------|
| `Glob` | Find files by pattern |
| `Grep` | Search code content |
| `Read` | Read file contents |

## Output Format

```markdown
## Research Results

### Files Found
| File | Relevance | Pattern |
|------|-----------|---------|
| path/to/file.php | High | Provider pattern |

### Patterns Discovered
| Pattern | Confidence | Location |
|---------|------------|----------|
| Pattern name | 0.9 | file:line |

### Conventions
- Convention 1 (from .apex/CONVENTIONS.md)
- Convention 2 (from codebase analysis)

### Dependencies
- Module A depends on Module B
```

## Usage in ARCHITECT Phase

1. **CoT**: Uses gathered context for step-by-step reasoning
2. **ToT**: Uses patterns to evaluate alternative approaches
3. **CoD**: Uses conventions to refine draft iterations
4. **YAGNI**: Uses scope analysis to define "not doing" list
5. **Patterns**: Uses discovered patterns for selection rationale
AGENT

  cat > "${ROOT}/skills/apex/agents/quality-reviewer.md" << 'AGENT'
# Quality Reviewer Agent

Agent specialized in code review against architectural decisions for the REVIEWER phase.

## Role

Reviews implemented code against ARCHITECT phase decisions.
Ensures compliance with chosen patterns, YAGNI declarations, and architectural rationale.

## Review Checklist

```markdown
#### Decision Compliance
- [ ] Implementation matches chosen approach from ToT
- [ ] Scope matches CoD final draft

#### Pattern Compliance
- [ ] Selected patterns correctly applied
- [ ] Usage consistent with codebase conventions

#### YAGNI Enforcement
- [ ] No features beyond declared scope
- [ ] No premature abstractions

#### Quality Gates
- [ ] Type safety maintained
- [ ] No obvious security issues
- [ ] No dead code introduced
```

## Output Format

```xml
<reviewer>
  <decision-compliance status="pass|fail"/>
  <pattern-compliance status="pass|fail"/>
  <yagni-enforcement status="pass|fail"/>
  <quality><type-safety findings="N"/><security findings="N"/></quality>
  <verdict>approve|request-changes</verdict>
  <summary>Brief summary</summary>
</reviewer>
```

## When Used

- **Feature** workflow: after VALIDATOR(full), before DOCUMENTER
- **Milestone** workflow: after VALIDATOR(integration), before DOCUMENTER
- **Skipped** for Subtask workflows
AGENT

  cat > "${ROOT}/skills/apex/agents/test-validator.md" << 'AGENT'
# Test Validator Agent

Agent specialized in test execution and validation for the VALIDATOR phase.

## Test Strategy by Depth

### lint-only (Subtasks)
No tests. Agent not invoked.

### full (Features)

```bash
php bin/phpunit --testsuite unit
php -d memory_limit=512M vendor/bin/phpstan analyse --no-progress
vendor/bin/grumphp run --tasks=git_blacklist
```

### integration (Milestones + tasks with E2E files)

```bash
make test-up    # start Docker if needed
make test-e2e   # run E2E via Docker
```

## Output Format

```xml
<test-results depth="full|integration">
  <execution>
    <total>N</total><passed>N</passed><failed>N</failed>
  </execution>
  <failures>
    <failure test="name">
      <diagnosis>Why it failed</diagnosis>
      <suggested-fix>How to fix</suggested-fix>
    </failure>
  </failures>
</test-results>
```

## Error Handling

- **Test Failure**: diagnose reason, suggest fix, report to VALIDATOR for fix loop
- **DB not in sync**: `make test-db-reset` then retry
- **Docker not started**: `make test-up` then retry
AGENT

  # ── templates ─────────────────────────────────────────────────────────────
  cat > "${ROOT}/skills/apex/templates/task.md" << 'TMPL'
# Task File Template (5-Phase)

Task files live in `.apex/tasks/feature_<slug>/`.

## Subtask template

```yaml
---
identifier: F001-S001
parent: F001
title: "Create entity schema"
type: subtask
workflow: [architect, builder, validator, documenter]
validatorDepth: lint-only
phase: pending
status: active
created: YYYY-MM-DD
---

# F001-S001: Create entity schema

## Context
[Brief description]

## Files
| File | Action |
|------|--------|
| src/BC/Domain/Entity/X.php | CREATE |

---

<architect>
<cot><!-- Chain of Thought analysis --></cot>
<tot><!-- Tree of Thought: 3+ alternatives --></tot>
<cod><!-- Chain of Draft: 3 iterations --></cod>
<yagni><!-- Explicit "not doing" list --></yagni>
<patterns><!-- Pattern selection rationale --></patterns>
</architect>

<builder><!-- Phase 2: implementation --></builder>
<validator><!-- Phase 3: lint-only --></validator>
<documenter><!-- Phase 5: commit + reflection --></documenter>
```

## Feature template

```yaml
---
identifier: F001-F001
parent: F001
title: "Implement POST endpoint"
type: feature
workflow: [architect, builder, validator, reviewer, documenter]
validatorDepth: full
status: active
created: YYYY-MM-DD
depends_on: [F001-S001]
---

# F001-F001: Implement POST endpoint

## Context
[Description — bounded context, business rules, constraints]

## Endpoints
| Method | URI | Auth | Voter |
|--------|-----|------|-------|
| POST | /api/resource | JWT | RESOURCE_EDIT |

## Acceptance Criteria
- [ ] AC-1: [Criterion]
- [ ] AC-N: PHPStan level 6 pass

## Security Verification
- [ ] Provider filters by ownership (cross-tenant impossible)
- [ ] `isGranted('VOTER_ATTR', $entity)` via dedicated Voter
- [ ] Non-owner → 403 | Unknown resource → 404

---

## Commits

### `feat(scope): description` ⏳
> ⚠️ Run tests before committing: `cd backend && make test-e2e`

| File | Action |
|------|--------|
| src/BC/Application/Service/MyService.php | CREATE |
| tests/E2E/BC/MyFeatureTest.php | CREATE |

---

<architect><cot/><tot/><cod/><yagni/><patterns/></architect>
<builder><!-- one commit at a time, tests before each commit --></builder>
<validator><!-- full: PHPStan + GrumPHP + unit + E2E --></validator>
<reviewer><!-- review against architect decisions --></reviewer>
<documenter><!-- commit + reflection + learning --></documenter>
```

## Commit rules

- One HTTP operation per commit (REST APIs: POST → GET → DELETE → PATCH)
- Always run `make test-e2e` before committing
- Mark ✅ in file after each successful commit
- Never `git add .` — always stage specific files

## Phase values

`pending` → `architect` → `builder` → `validator` → `reviewer` → `documenter` → `complete` | `rework`
TMPL

  log "skills/apex/ (stubs created — copy real SKILL.md files from reference project)"
  warn "Stubs only. Copy full SKILL.md files from your reference project for production use."
fi


# ─── CLAUDE.md ────────────────────────────────────────────────────────────────

info "Creating CLAUDE.md..."
cat > "${ROOT}/CLAUDE.md" << CLAUDEMD
## Git

- Ne jamais ajouter \`Co-Authored-By\` dans les messages de commit.
- Ne pas committer \`.gitignore\`.

## Agents

Voir [AGENTS.md](AGENTS.md) pour les conventions des skills.

## Tests

Dès qu'un service est mocké dans les tests smoke/e2e, vérifier qu'il existe au moins
un test integration qui frappe le vrai service.
Le signal d'alerte : tous les tests d'un flow mockent le même endpoint.

## Workflow

- Plan avant implémentation : toujours écrire le plan complet et attendre validation.
- Bash : une commande à la fois, pas de chaînage \`&&\`.
- Pas de résumé en fin de réponse. Opérations mémoire silencieuses.
CLAUDEMD
log "CLAUDE.md"


# ─── claw.md ──────────────────────────────────────────────────────────────────

info "Creating claw.md..."
cat > "${ROOT}/claw.md" << 'CLAWDOC'
# Structure des agents et skills dans Claw

## Arborescence

```
votre-projet/
├── .claw/
│   ├── agents/         # Fichiers .toml → listés via /agents
│   │   ├── architect.toml
│   │   ├── debugger.toml
│   │   └── reviewer.toml
│   ├── skills/         # Sous-dossiers avec SKILL.md → invocables via /skill-name
│   │   ├── plan/SKILL.md
│   │   ├── debug/SKILL.md
│   │   ├── git/SKILL.md
│   │   └── apex/
│   │       ├── core/       architect.md, builder.md, validator.md, reviewer.md, documenter.md
│   │       ├── orchestrators/  feature.md, subtask.md
│   │       └── planning/   decompose.md, research.md
│   └── CLAW.md         # Chargé dans le system prompt automatiquement
├── .claude/
│   ├── commands/git/   # cm.md, cp.md, pr.md
│   ├── memory/         # MEMORY.md + fichiers mémoire
│   ├── plans/          # Plans d'implémentation
│   ├── mempalace_identity.txt   # Identité du projet (chargée en contexte)
│   └── settings.local.json
├── AGENTS.md           # Référence skills + mapping problème→skill
├── CLAUDE.md           # Instructions Claude Code
└── claw.md             # Ce fichier — documentation de la structure
```

## Agents (.toml)

Métadonnée pure (nom, description, modèle). Apparaissent dans `/agents`.

```toml
name = "architect"
description = "Architecture planning"
model = "claude-sonnet-4-6"
```

## Skills (SKILL.md)

Un skill = un sous-dossier + SKILL.md. Définit le comportement réel.

```yaml
---
name: plan
description: Architecture planning with step-by-step breakdown
---

[instructions du skill]
```

Invocation : `claw /plan "ajouter un système de cache"`

## Workflow APEX

5 phases séquentielles pour les features complexes :

```
/apex:architect  →  /apex:builder  →  /apex:validator  →  /apex:reviewer  →  /apex:documenter
```

Ou en une commande : `/apex:feature`

## CLAW.md

Règles transversales chargées automatiquement dans le system prompt.
Ne pas y mettre les instructions spécifiques à un skill — elles vont dans `SKILL.md`.
CLAWDOC
log "claw.md"


# ─── AGENTS.md ────────────────────────────────────────────────────────────────

info "Creating AGENTS.md..."
cat > "${ROOT}/AGENTS.md" << AGENTSMD
# Agent Skills — ${PROJECT_NAME}

${STACK}

## Philosophie d'apprentissage

**Approche en 3 étapes :**

1. **D'abord fonctionnel** — Faire marcher le code, même "sale".
2. **Puis comprendre** — Identifier les code smells, lire les skills de patterns.
3. **Puis améliorer** — Refactorer en appliquant le bon pattern, dans un commit dédié.

**Règles :**
- Ne pas chercher à tout optimiser d'entrée. YAGNI.
- Chaque pattern appliqué = un commit avec message explicatif.
- Règle 80/20 : CRUD natif pour 80%, patterns pour les 20% à haute valeur.

---

## Skills disponibles

### Architecture & Décision

| Skill | Fichier | Usage |
|-------|---------|-------|
| \`plan\` | \`.claw/skills/plan/SKILL.md\` | Plan avant implémentation — étapes numérotées, risques |
| \`debug\` | \`.claw/skills/debug/SKILL.md\` | Root-cause analysis — lire avant supposer |
| \`git\` | \`.claw/skills/git/SKILL.md\` | Commits conventionnels, workflow PR, checklist |

### Workflow APEX

| Skill | Fichier | Usage |
|-------|---------|-------|
| \`apex:architect\` | \`.claw/skills/apex/core/architect.md\` | Phase 1 — Design avec 5 artifacts (CoT, ToT, CoD, YAGNI, Patterns) |
| \`apex:builder\` | \`.claw/skills/apex/core/builder.md\` | Phase 2 — Implémentation suivant les artifacts ARCHITECT |
| \`apex:validator\` | \`.claw/skills/apex/core/validator.md\` | Phase 3 — Quality gates (lint, tests unit, E2E) |
| \`apex:reviewer\` | \`.claw/skills/apex/core/reviewer.md\` | Phase 4 — Review contre les décisions architecturales |
| \`apex:documenter\` | \`.claw/skills/apex/core/documenter.md\` | Phase 5 — Commit docs + reflection |
| \`apex:feature\` | \`.claw/skills/apex/orchestrators/feature.md\` | Orchestrateur complet — toutes les phases |
| \`apex:subtask\` | \`.claw/skills/apex/orchestrators/subtask.md\` | Sous-tâche issue d'un plan validé |
| \`apex:decompose\` | \`.claw/skills/apex/planning/decompose.md\` | Décomposer une feature en commits atomiques |
| \`apex:research\` | \`.claw/skills/apex/planning/research.md\` | Cartographier l'existant avant de planifier |

### Commandes Git (.claude/commands/)

| Commande | Fichier | Usage |
|----------|---------|-------|
| \`/git:cm\` | \`.claude/commands/git/cm.md\` | Stage + commit conventionnel (sans push) |
| \`/git:cp\` | \`.claude/commands/git/cp.md\` | Stage + commit + push |
| \`/git:pr\` | \`.claude/commands/git/pr.md\` | Créer une PR GitHub |

### Idéation & Brainstorming (global)

| Skill | Usage |
|-------|-------|
| \`brainstorming\` | Exploration créative avant une décision de conception ou d'architecture |
| \`multi-agent-brainstorming\` | Revue par plusieurs perspectives simulées — utile pour des choix structurants |

---

## Mapping problème → skill

| Problème | Skill recommandé |
|----------|-----------------|
| "Je veux implémenter une nouvelle feature" | \`apex:architect\` → \`apex:feature\` |
| "Je ne sais pas par où commencer" | \`apex:research\` → \`apex:decompose\` |
| "J'ai un bug et je ne comprends pas pourquoi" | \`debug\` |
| "Combien de commits pour cette feature ?" | \`git\` → \`apex:decompose\` |
| "Je veux refactorer avec un pattern" | \`apex:architect\` (arbre de décision patterns) |
| "Comment valider que mon implémentation est correcte ?" | \`apex:validator\` |
| "Je veux faire une PR" | \`/git:pr\` |
| "Je veux committer sans pousser" | \`/git:cm\` |
| "Je dois choisir entre plusieurs approches" | \`brainstorming\` → \`apex:architect\` |
| "Je veux challenger une décision avec plusieurs points de vue" | \`multi-agent-brainstorming\` |

---

## Ajouter un nouveau skill

1. Créer \`.claw/skills/{nom}/SKILL.md\` avec frontmatter \`name\` + \`description\`
2. Ajouter une entrée dans ce fichier (tableau Skills disponibles + mapping)
3. Commit : \`docs(agents): add {nom} skill\`
AGENTSMD
log "AGENTS.md"


# ─── Résumé ───────────────────────────────────────────────────────────────────

echo ""
echo "  Done. Files created:"
echo ""
echo "  .claude/"
echo "  ├── commands/git/  cm.md, cp.md, pr.md"
echo "  ├── commands/apex/ architect.md, builder.md, validator.md,"
echo "  │                  reviewer.md, documenter.md, feature.md,"
echo "  │                  subtask.md, decompose.md, research.md"
echo "  ├── memory/MEMORY.md"
echo "  ├── mempalace_identity.txt"
echo "  └── settings.local.json"
echo ""
echo "  .claw/"
echo "  ├── agents/ (architect, debugger, reviewer)"
echo "  └── CLAW.md"
echo ""
echo "  skills/"
echo "  ├── apex/core/          architect, builder, validator, reviewer, documenter"
echo "  ├── apex/orchestrators/ feature, subtask, milestone"
echo "  ├── apex/planning/      research, plan, decompose"
echo "  ├── apex/agents/        intelligence-gatherer, quality-reviewer, test-validator"
echo "  ├── apex/templates/     task.md"
echo "  └── apex/config/SKILL.md"
echo ""
echo "  CLAUDE.md"
echo "  claw.md"
echo "  AGENTS.md"
echo ""
warn "Edit .claude/mempalace_identity.txt to describe your specific project."
warn "Edit .claw/CLAW.md to update the verification commands for your stack."
echo ""
