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
