## Git

- Ne jamais ajouter `Co-Authored-By` dans les messages de commit.
- Ne pas committer `.gitignore`.

## Agents

Voir [AGENTS.md](AGENTS.md) pour les conventions des skills.

## Tests

Dès qu'un service est mocké dans les tests smoke/e2e, vérifier qu'il existe au moins
un test integration qui frappe le vrai service.
Le signal d'alerte : tous les tests d'un flow mockent le même endpoint.

## Workflow

- Plan avant implémentation : toujours écrire le plan complet et attendre validation.
- Bash : une commande à la fois, pas de chaînage `&&`.
- Pas de résumé en fin de réponse. Opérations mémoire silencieuses.

## Architecture & Context

Before exploring files, query MemPalace via CLI:
- `mempalace search <query>` — semantic search
- `mempalace wing <project-name>` — project context
- `mempalace get_taxonomy` — full structure tree

Only use Glob/Grep/Read if MemPalace returns nothing relevant.

## Karpathy Guidelines

- Prefer simple, readable code over clever abstractions
- Minimize dependencies — every dependency is a liability
- Write code you can fully understand and debug yourself
- Avoid premature optimization; profile before optimizing
- Flat is better than nested; short functions over long ones
- Delete code aggressively — the best code is no code
- When in doubt, don't add it; features have maintenance cost
- Prototypes over frameworks; understand primitives first

## End-of-Development Summary (MANDATORY)

After every development task, always end with:

**✅ Ce qui a été fait** — résumé de ce qui est complété et fonctionnel.

**⏳ Ce qui reste à faire** — prochaines étapes dans l'ordre, pour que l'utilisateur sache exactement où on en est dans le process.

## graphify

This project has a graphify knowledge graph at graphify-out/.

Rules:
- Before answering architecture or codebase questions, read graphify-out/GRAPH_REPORT.md for god nodes and community structure
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)
