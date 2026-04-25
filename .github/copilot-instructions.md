## graphify

Before answering architecture or codebase questions, read `graphify-out/GRAPH_REPORT.md` if it exists.
If `graphify-out/wiki/index.md` exists, navigate it for deep questions.
Type `/graphify` in Copilot Chat to build or update the knowledge graph.

### Graph-First Query Workflow

You have access to a Graphify knowledge graph at `graphify-out`.

For every question:

1. First run:
	`graphify query "<user question>" --graph graphify-out/graph.json`
2. Use ONLY the returned graph context to answer.
3. If graph context is insufficient, then (and only then) read specific files.

Rules:

- Do NOT scan the entire codebase.
- Do NOT load full files unless absolutely necessary.
- Prefer relationships, dependencies, and paths from the graph.
- Cite source files mentioned in the graph output when possible.
