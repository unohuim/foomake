CODEX TASK: Internal documentation sync for the current PR (PLAN ONLY)

You are working in this repository.

Before doing anything:

- Read and follow ALL documentation and standards in the docs/ folder.
- Assume docs/ is the authoritative internal documentation.

Definitions

- “The current PR” refers to the code changes present in the working tree at the time this task is run.
- Scope must be inferred ONLY from implemented code.
- Do NOT assume future work.

STRICT MODE

- Do NOT modify any files in this task.
- This task is PLAN ONLY.

Objective

1. Identify ALL documentation files under docs/ that should be updated to reflect the current PR.
2. Propose exactly what changes are required in each doc.

How to determine impacted docs
Scan the codebase for changes involving:

- Database schema (migrations, models, tenancy fields)
- Permissions / gates / roles
- Routes and domain modules
- Navigation / information architecture
- Frontend page modules and payload contracts
- Tenancy and scoping behavior

Required output format
Return a single structured list:

Docs Impact Plan

- <doc path>
  - Why this doc is impacted
  - Exact sections to change (headings or anchors if applicable)
  - Summary of changes (concise, factual)

Rules

- List ONLY docs that truly require changes.
- If NO docs require updates, explicitly say so.
- Do NOT include implementation text.
- Do NOT modify any files.
- Do NOT suggest improvements beyond the current PR.

Completion criteria

- Output is suitable for human approval.
- No files are changed.
- No assumptions beyond the current PR.

Docs to Ignore
Do not update, edit or touch the following docs;

- docs/AI_CHAT_BOOTSTRAP.md

STOP after producing the plan.
Wait for explicit human approval before proceeding.
